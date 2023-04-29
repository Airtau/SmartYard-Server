const modules = {};
const moduleLoadQueue = [];
const loadingProgress = new ldBar("#loadingProgress");

var lastHash = false;
var currentPage = false;
var mainSidebarFirst = true;
var mainSidebarGroup = false;
var config = false;
var lang = false;
var myself = false;
var available = false;
var badge = false;
var currentModule = false;

function hashChange() {
    let [ route, params, hash ] = hashParse();

    if (hash !== lastHash) {
        lastHash = hash;

        loadingStart();

        setTimeout(() => {
            currentPage = route;

            let r = route.split(".");

            if ($(".sidebar .withibleOnlyWhenActive[target!='?#" + route + "']").length) {
                $(".sidebar .withibleOnlyWhenActive[target!='?#" + route + "']").hide();
            } else {
                $(".sidebar .withibleOnlyWhenActive[target!='?#" + r[0] + "']").hide();
            }

            if ($(".sidebar .withibleOnlyWhenActive[target='?#" + route + "']").length) {
                $(".sidebar .withibleOnlyWhenActive[target='?#" + route + "']").show();
            } else {
                $(".sidebar .withibleOnlyWhenActive[target='?#" + r[0] + "']").show();
            }

            if ($(".sidebar .nav-item a[href!='?#" + route + "']").length) {
                $(".sidebar .nav-item a[href!='?#" + route + "']").removeClass('active');
            } else {
                $(".sidebar .nav-item a[href!='?#" + r[0] + "']").removeClass('active');
            }

            if ($(".sidebar .nav-item a[href='?#" + route + "']").length) {
                $(".sidebar .nav-item a[href='?#" + route + "']").addClass('active');
            } else {
                $(".sidebar .nav-item a[href='?#" + r[0] + "']").addClass('active');
            }

            $("#loginForm").hide();
            $("#forgotForm").hide();

            let module = modules;

            for (let i = 0; i < r.length; i++) {
                if (module[r[i]]) {
                    module = module[r[i]];
                } else {
                    module = false;
                    break;
                }
            }

            if (module) {
                $("#page404").hide();
                $("#pageError").hide();
                $("#topMenuLeft").html(`<li class="ml-3 mr-3 nav-item d-none d-sm-inline-block text-bold text-lg">${i18n(route.split('.')[0] + "." + route.split('.')[0])}</li>`);
                if (currentModule != module) {
                    $("#leftTopDynamic").html("");
                    $("#rightTopDynamic").html("");
                    currentModule = module;
                }
                if (typeof module.search === "function") {
                    $("#searchForm").show();
                } else {
                    $("#searchForm").hide();
                }
                if (typeof module.route === "function") {
                    module.route(params);
                } else {
                    page404();
                }
            } else
            if (route === "default") {
                if (config.defaultRoute && config.defaultRoute != "#" && config.defaultRoute != "?#") {
                    location.href = (config.defaultRoute.charAt(0) == "?")?config.defaultRoute:("?" + config.defaultRoute);
                } else {
                    loadingDone();
                }
            } else {
                page404();
            }
        }, 50);
    }
}

function page404() {
    $("#mainForm").html("");
    $("#altForm").hide();
    loadingDone();
    document.title = `${i18n("windowTitle")} :: 404`;
    $("#page404").html(`
        <section class="content">
            <div class="error-page">
                <h2 class="headline text-danger"> 404</h2>
                <div class="error-content">
                    <h3><i class="fas fa-exclamation-triangle text-danger"></i>${i18n("errors.404caption")}</h3>
                    <p>${i18n("errors.404message")}</p>
                </div>
            </div>
        </section>
    `).show();
}

function pageError(error) {
    $("#mainForm").html("");
    $("#subTop").html("");
    $("#altForm").hide();
    loadingDone();
    document.title = `${i18n("windowTitle")} :: ${i18n("error")}`;
    $("#pageError").html(`
        <section class="content">
            <div class="error-page">
                <h2 class="headline text-danger mr-4"> Error</h2>
                <div class="error-content">
                    <h3><i class="fas fa-exclamation-triangle text-danger"></i>${i18n("error")}</h3>
                    <p>${error?error:i18n("errors.unknown")}</p>
                </div>
            </div>
        </section>
    `).show();
}

function changeLanguage() {
    $.cookie("_lang", $("#loginBoxLang").val(), { expires: 3650, insecure: config.insecureCookie });
    location.reload();
}

function showLoginForm() {
    $("#mainForm").html("");
    $("#altForm").hide();
    $("#page404").hide();
    $("#pageError").hide();
    $("#forgotForm").hide();
    $("#loginForm").show();

    $("#loginBoxLogin").val($.cookie("_login"));
    $("#loginBoxServer").val($.cookie("_server"));
    if (!$("#loginBoxServer").val()) {
        $("#loginBoxServer").val(config.defaultServer);
    }
    $("#loginBoxRemember").attr("checked", $.cookie("_rememberMe") === "on");

    let server = $("#loginBoxServer").val();

    while (server[server.length - 1] === "/") {
        server = server.substring(0, server.length - 1);
    }

    $.get(server + "/accounts/forgot?available=ask").done(() => {
        $("#loginBoxForgot").show();
    });

    loadingDone(true);

    if ($("#loginBoxLogin").val()) {
        $("#loginBoxPassword").focus();
    } else {
        $("#loginBoxLogin").focus();
    }
}

function showForgotPasswordForm() {
    $("#mainForm").html("");
    $("#altForm").hide();
    $("#page404").hide();
    $("#pageError").hide();
    $("#loginForm").hide();
    $("#forgotForm").show();

    $("#forgotBoxServer").val($.cookie("_server"));
    if (!$("#forgotBoxServer").val()) {
        $("#forgotBoxServer").val($("#loginBoxServer").val());
    }
    if (!$("#forgotBoxServer").val()) {
        $("#forgotBoxServer").val(config.defaultServer);
    }

    loadingDone(true);

    $("#forgotBoxEMail").focus();
}

function ping(server) {
    return jQuery.ajax({
        url: server + "/server/ping",
        type: "POST",
        contentType: "json",
        success: response => {
            if (response != "pong") {
                loadingDone(true);
                error(i18n("errors.serverUnavailable"), i18n("error"), 30);
            }
        },
        error: () => {
            loadingDone(true);
            error(i18n("errors.serverUnavailable"), i18n("error"), 30);
        }
    });
}

function login() {
    let test = md5(new Date() + Math.random());

    $.cookie("_test", test, { insecure: config.insecureCookie });

    if ($.cookie("_test") != test) {
        error(i18n("errors.cantStoreCookie"), i18n("error"), 30);
        return;
    }

    loadingStart();

    let login = $.trim($("#loginBoxLogin").val());
    let password = $.trim($("#loginBoxPassword").val());
    let server = $.trim($("#loginBoxServer").val());
    let rememberMe = $("#loginBoxRemember").val();

    while (server[server.length - 1] === "/") {
        server = server.substring(0, server.length - 1);
    }

    $.cookie("_rememberMe", rememberMe, { expires: 3650, insecure: config.insecureCookie });

    if (rememberMe === "on") {
        $.cookie("_login", login, { expires: 3650, insecure: config.insecureCookie });
        $.cookie("_server", server, { expires: 3650, insecure: config.insecureCookie });
    } else {
        $.cookie("_login", login);
        $.cookie("_server", server);
    }

    ping(server).then(() => {
        return jQuery.ajax({
            url: server + "/authentication/login",
            type: "POST",
            contentType: "json",
            data: JSON.stringify({
                login: login,
                password: password,
                rememberMe: rememberMe === "on",
                did: $.cookie("_did"),
            }),
            success: response => {
                if (response && response.token) {
                    if (rememberMe === "on") {
                        $.cookie("_token", response.token, { expires: 3650, insecure: config.insecureCookie });
                    } else {
                        $.cookie("_token", response.token);
                    }
                    location.reload();
                } else {
                    error(i18n("errors.unknown"), i18n("error"), 30);
                }
            },
            error: response => {
                loadingDone(true);
                $("#loginBoxLogin").focus();
                if (response && response.responseJSON && response.responseJSON.error) {
                    error(i18n("errors." + response.responseJSON.error), i18n("error"), 30);
                } else {
                    error(i18n("errors.unknown"), i18n("error"), 30);
                }
            }
        });
    });
}

function logout() {
    window.onbeforeunload = null;

    POST("authentication", "logout", false, {
        mode: "all",
    }).always(() => {
        $.cookie("_token", "");
        location.reload();
    });
}

function forgot() {
    let email = $.trim($("#forgotBoxEMail").val());

    let server = $("#forgotBoxServer").val();

    while (server[server.length - 1] === "/") {
        server = server.substring(0, server.length - 1);
    }

    if (email) {
        $.cookie("_server", $("#loginBoxServer").val());
        $.get(server + "/accounts/forgot?eMail=" + email);
        message(i18n("forgotMessage"));
        showLoginForm();
    }
}

function whoAmI(force) {
    return GET("authentication", "whoAmI", false, force).done(_me => {
        if (_me && _me.user) {
            $(".myNameIs").attr("title", _me.user.realName?_me.user.realName:_me.user.login);
            myself.uid = _me.user.uid;
            myself.realName = _me.user.realName;
            myself.eMail = _me.user.eMail;
            myself.phone = _me.user.phone;
            myself.webRtcExtension = _me.user.webRtcExtension;
            myself.webRtcPassword = _me.user.webRtcPassword;
            if (_me.user.defaultRoute) {
                config.defaultRoute = _me.user.defaultRoute;
            }
            if (myself.eMail) {
                let gravUrl = "https://www.gravatar.com/avatar/" + md5($.trim(myself.eMail).toLowerCase()) + "?s=64&d=404";
                $(".userAvatar").off("click").on("error", function () {
                    $(this).attr("src", "avatars/noavatar.png");
                    error(i18n("errors.noGravatar"));
                }).attr("src", gravUrl);
            } else {
                if (parseInt(myself.uid) === 0) {
                    $(".userAvatar").attr("src", "avatars/admin.png");
                }
            }
            $("#selfSettings").off("click").on("click", () => {
                modules["users"].modifyUser(myself.uid, true);
            });
            let userCard = _me.user.login;
            if (_me.user.realName) {
                userCard += "<br />" + _me.user.realName;
            }
            if (_me.user.eMail) {
                userCard += "<br />" + _me.user.eMail;
            }
            $("#userCard").html(userCard);
        }
    })
}

function initAll() {
    if (!$.cookie("_cookie")) {
        warning(i18n("cookieWarning"), false, 3600);
        $.cookie("_cookie", "1", { expires: 3650, insecure: config.insecureCookie });
    }

    if (!$.cookie("_https") && window.location.protocol === 'http:') {
        warning(i18n("httpsWarning"), false, 3600);
        $.cookie("_https", "1", { expires: 3650, insecure: config.insecureCookie });
    }

    if (config.logo) {
        setFavicon("img/" + config.logo + "Icon.png");
        $("#leftSideToggler").attr("src", "img/" + config.logo + ".png");
        $("#loginBoxLogo").html("<img class='mb-2' src='img/" + config.logo + "Text.png' width='285px'/>");
        $("#forgotBoxLogo").html("<img class='mb-2' src='img/" + config.logo + "Text.png' width='285px'/>");
    }

    $(document.body).css("background-color", '#e9ecef');

    if (!$.cookie("_did")) {
        $.cookie("_did", guid(), { expires: 3650, insecure: config.insecureCookie });
    }

    loadingStart();

    $("#leftSideToggler").parent().parent().on("click", () => {
        setTimeout(() => {
            $.cookie("_ls_collapse", $("body").hasClass("sidebar-collapse")?"1":"0", { expires: 3650, insecure: config.insecureCookie });
        }, 100);
    });

    setTimeout(() => {
        if (parseInt($.cookie("_ls_collapse"))) {
            $("body").addClass("sidebar-collapse");
        } else {
            $("body").removeClass("sidebar-collapse");
        }
    }, 500);

    document.title = i18n("windowTitle");

    $("#loginBoxTitle").text(i18n("loginFormTitle"));
    $("#loginBoxLogin").attr("placeholder", i18n("login"));
    $("#loginBoxPassword").attr("placeholder", i18n("password"));
    $("#loginBoxServer").attr("placeholder", i18n("server"));

    let l = "";
    for (let i in config.languages) {
        if ($.cookie("_lang") == i) {
            l += `<option value='${i}' selected>${config.languages[i]}</option>`;
        } else {
            l += `<option value='${i}'>${config.languages[i]}</option>`;
        }
    }
    $("#loginBoxLang").html(l);

    $("#loginBoxLoginButton").text(i18n("loginAction"));
    $("#loginBoxForgotPassword").text(i18n("passowrdForgot"));
    $("#loginBoxRememberLabel").text(i18n("rememberMe"));

    $("#forgotBoxTitle").text(i18n("forgotFormTitle"));
    $("#forgotBoxEMail").attr("placeholder", i18n("eMail"));
    $("#forgotBoxButton").text(i18n("forgotAction"));
    $("#forgotBoxLogin").text(i18n("forgotLogin"));
    $("#forgotBoxServer").attr("placeholder", i18n("server"));

    $("#brandTitle").text(i18n("windowTitle"));
    $("#logout").text(i18n("logout"));

    if (config.z2Enabled) {
        $(".rs232-scanner-button").show();
    }
    $('.rs232-scanner').attr('title', i18n("connectScanner"));

    $("#searchInput").attr("placeholder", i18n("search")).off("keypress").on("keypress", e => {
        if (e.charCode === 13) {
            modules[currentPage].search($("#searchInput").val());
        }
    });

    $("#inputTextLine").off("keypress").on("keypress", event => {
        if (event.keyCode === 13) $('#inputTextButton').click();
    });

    $("#searchButton").off("click").on("click", () => {
        modules[currentPage].search($("#searchInput").val());
    });

/*
    $("#confirmModal").draggable({
        handle: "#confirmModalHeader",
    });

    $("#yesnoModal").draggable({
        handle: "#yesnoModalHeader",
    });

    $("#alertModal").draggable({
        handle: "#alertModalHeader",
    });

    $("#uploadModalBody").draggable({
        handle: "#uploadModalHeader",
    });
*/

    if ($.cookie("_server") && $.cookie("_token")) {
        POST("authentication", "ping", false).done((a, b) => {
            if (b === "nocontent") {
                GET("authorization", "available").done(a => {
                    if (a && a.available) {
                        myself = {
                            uid: -1,
                        };
                        whoAmI().done(() => {
//                            window.onbeforeunload = () => false;
                            available = a.available;
                            if (config && config.modules) {
                                for (let i in config.modules) {
                                    moduleLoadQueue.push(config.modules[i]);
                                }
                                loadModule();
                            } else {
                                $("#app").show();
                                if (config.defaultRoute) {
                                    onhashchange = hashChange;
                                    location.href = (config.defaultRoute.charAt(0) == "?")?config.defaultRoute:("?" + config.defaultRoute);
                                } else {
                                    hashChange();
                                    onhashchange = hashChange;
                                }
                            }
                            setInterval(() => {
                                $(".blink-icon.blinking").toggleClass("text-warning");
                                $(".blink-icon:not(.blinking)").removeClass("text-warning");
                            }, 1000);
                        }).fail(response => {
                            FAIL(response);
                            showLoginForm();
                        });
                    } else {
                        FAIL();
                        showLoginForm();
                    }
                }).fail(response => {
                    FAIL(response);
                    showLoginForm();
                });
            } else {
                FAIL();
                showLoginForm();
            }
        }).fail(response => {
            FAIL(response);
            showLoginForm();
        });
    } else {
        showLoginForm();
    }
}

function setFavicon(icon, unreaded) {
    if (typeof unreaded == 'undefined') {
        unreaded = 0;
    }

    if ($.browser.chrome) {
        $('#favicon').attr('href', icon);
    } else {
        document.head || (document.head = document.getElementsByTagName('head')[0]);
        let link = document.createElement('link');
        let oldLink = document.getElementById('dynamic-favicon');
        link.id = 'dynamic-favicon';
        link.rel = 'shortcut icon';
        link.href = icon;
        if (oldLink){
            document.head.removeChild(oldLink);
        }
        document.head.appendChild(link);
    }

    badge = new Favico({ animation: 'none', bgColor: '#000000' });

    if (unreaded) {
        if (unreaded <= 9 || !parseInt(unreaded)) {
            badge.badge(unreaded);
        } else {
            badge.badge('9+');
        }
    }
}

function message(message, caption, timeout) {
    timeout = timeout?timeout:15;
    toastr.info(message, caption?caption:i18n("message"), {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-bottom-right",
        "preventDuplicates": false,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": timeout?(timeout * 1000):"0",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    });
}

function warning(message, caption, timeout) {
    timeout = timeout?timeout:15;
    toastr.warning(message, caption?caption:i18n("warning"), {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-bottom-right",
        "preventDuplicates": false,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": timeout?(timeout * 1000):"0",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    });
}

function error(message, caption, timeout) {
    timeout = timeout?timeout:15;
    toastr.error(message, caption?caption:i18n("error"), {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-bottom-right",
        "preventDuplicates": false,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": timeout?(timeout * 1000):"0",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    });
}

function mConfirm(body, title, button, callback) {
    if (!title) {
        title = i18n("confirm");
    }
    $('#confirmModalLabel').html(title);
    $('#confirmModalBody').html(body);
    let bc = 'btn-primary';
    button = button.split(':');
    if (button.length === 2) {
        bc = 'btn-' + button[0];
        button = button[1];
    } else {
        button = button[0];
    }
    $('#confirmModalButton').removeClass('btn-primary btn-secondary btn-success btn-danger btn-warning btn-info btn-light btn-dark btn-link').addClass(bc).html(button).off('click').on('click', () => {
        $('#confirmModal').modal('hide');
        if (typeof callback == 'function') callback();
    });
    autoZ($('#confirmModal')).modal('show');
    xblur();
}

let mYesNoTimeout = 0;

function mYesNo(body, title, callbackYes, callbackNo, yes, no, timeout) {
    if (mYesNoTimeout) {
        clearTimeout(mYesNoTimeout);
    }

    if (!title) {
        title = i18n("confirm");
    }

    $('#yesnoModalLabel').html(title);
    $('#yesnoModalBody').html(body);
    $('#yesnoModalButtonYes').html(yes?yes:i18n("yes")).off('click').on('click', () => {
        $('#yesnoModal').modal('hide');
        if (typeof callbackYes == 'function') callbackYes();
    });
    $('#yesnoModalButtonNo').html(no?no:i18n("no")).off('click').on('click', () => {
        $('#yesnoModal').modal('hide');
        if (typeof callbackNo == 'function') callbackNo();
    });
    autoZ($('#yesnoModal')).modal('show');
    xblur();

    if (timeout) {
        mYesNoTimeout = setTimeout(() => {
            mYesNoTimeout = 0;
            $('#yesnoModal').modal('hide');
        }, timeout);
    }
}

function mAlert(body, title, callback, title_button, main_button) {
    if (!title) {
        title = i18n("message");
    }
    if (title.toLowerCase().indexOf(i18n("error").toLowerCase()) >= 0) {
        title = '<span class="text-danger">' + title + '</span>';
    }
    if (title.toLowerCase().indexOf(i18n("warning").toLowerCase()) >= 0) {
        title = '<span class="text-warning">' + title + '</span>';
    }
    if (title.toLowerCase().indexOf(i18n("message").toLowerCase()) >= 0) {
        title = '<span class="text-success">' + title + '</span>';
    }
    let l = $('#alertModalLabel').html(title);
    if (title_button) {
        l.next().remove();
        l.parent().append($(title_button));
    }
    $('#alertModalBody').html(body);
    if (main_button) {
        $('#alertModalButton').html(main_button);
    } else {
        $('#alertModalButton').html(i18n("ok"));
    }
    $('#alertModalButton').off('click').on('click', (e) => {
        $('#alertModal').modal('hide');
        if (typeof callback == 'function') callback();
        e.stopPropagation();
    });
    autoZ($('#alertModal')).modal('show');
    xblur();
}

function modal(body) {
    $("#modalBody").html(body);
    xblur();
    return autoZ($('#modal')).modal('show');
}

function xblur() {
    setTimeout(() => {
        $('a, input, button, .nav-item').blur();
    }, 100);
}

function autoZ(target) {
    let maxZ = Math.max.apply(null, $.map($('body > *:visible'), function(e) {
        if (e === target) {
            return 1;
        } else {
            // no great than 9999999
            let z = parseInt($(e).css('z-index'));
            if (z < 9999999) {
                return parseInt($(e).css('z-index')) || 1;
            } else {
                return 1;
            }
        }
    }));

    maxZ = Math.max(maxZ, 100500);

    if (target) {
        target.css('z-index', maxZ + 1);
    }

    return target;
}

function loadingStart() {
    autoZ($('#loading').modal({
        backdrop: 'static',
        keyboard: false,
    }));
}

function loadingDone(stayHidden) {
    xblur();

    $('#loading').modal('hide');

    if (stayHidden === true) {
        $('#app').addClass("invisible");
    } else {
        $('#app').removeClass("invisible");
    }

    if (parseInt($.cookie('_ls_collapse'))) {
        $(document.body).addClass('sidebar-collapse');
    } else {
        $(document.body).removeClass('sidebar-collapse');
    }

    $(window).resize();
}

function timeoutStart() {
    autoZ($('#timeout').modal({
        backdrop: 'static',
        keyboard: false,
    }));
    $('.timeout-animate').each(function () {
        this.beginElement();
    });
}

function timeoutDone() {
    $('#timeout').modal('hide');
}

function findBootstrapEnvironment() {
    let envs = ['xs', 'sm', 'md', 'lg', 'xl'];

    let el = document.createElement('div');
    document.body.appendChild(el);

    let curEnv = envs.shift();

    for (let env of envs.reverse()) {
        el.classList.add(`d-${env}-none`);

        if (window.getComputedStyle(el).display === 'none') {
            curEnv = env;
            break;
        }
    }

    document.body.removeChild(el);
    return curEnv;
}

function nl2br(str) {
    if (str && typeof str == "string") {
        return str.split("\n").join("<br />");
    } else {
        return "";
    }
}

function i18n(msg, ...args) {
    try {
        let t = msg.split(".");
        if (t.length > 2) {
            let t_ = [];
            t_[0] = t.shift();
            t_[1] = t.join(".");
            t = t_;
        }
        let loc;
        if (t.length === 2) {
            loc = lang[t[0]][t[1]];
        } else {
            loc = lang[t[0]];
        }
        if (loc) {
            if (typeof loc === "object" && Array.isArray(loc)) {
                loc = nl2br(loc.join("\n"));
            }
            loc = sprintf(loc, ...args);
        }
        if (!loc) {
            if (t[0] === "errors") {
                return t[1];
            } else {
                return msg;
            }
        }
        return loc;
    } catch (_) {
        return msg;
    }
}

function leftSide(button, title, target, group, withibleOnlyWhenActive) {
    if (group != mainSidebarGroup && !mainSidebarFirst) {
        $("#leftside-menu").append(`
            <li class="nav-item"><hr class="border-top" style="opacity: 15%"></li>
        `);
    }

    let [ route ] = hashParse();

    $("#leftside-menu").append(`
        <li class="nav-item ${mainSidebarFirst?"mt-1":""} ${withibleOnlyWhenActive?" withibleOnlyWhenActive":""}" target="${target}" title="${escapeHTML(title)}"${(withibleOnlyWhenActive && target !== "#" + route.split('.')[0])?" style='display: none;'":""}>
            <a href="${target}" class="nav-link${(target === "#" + route.split('.')[0])?" active":""}">
                <i class="${button} nav-icon"></i>
                <p class="text-nowrap">${title}</p>
            </a>
        </li>
    `);

    mainSidebarGroup = group;
    mainSidebarFirst = false;
}

function loadModule() {
    let module = moduleLoadQueue.shift();
    if (!module) {
        for (let i in modules) {
            if (typeof modules[i].allLoaded == "function") {
                modules[i].allLoaded();
            }
        }
        hashChange();
        onhashchange = hashChange;
        $("#app").show();
    } else {
        let l = $.cookie("_lang");
        if (!l) {
            l = config.defaultLanguage;
        }
        if (!l) {
            l = "ru";
        }
        $.get("modules/" + module + "/i18n/" + l + ".json", i18n => {
            if (i18n.errors) {
                if (!lang.errors) {
                    lang.errors = {};
                }
                lang.errors = {...lang.errors, ...i18n.errors};
                delete i18n.errors;
            }
            if (i18n.methods) {
                if (!lang.methods) {
                    lang.methods = {};
                }
                lang.methods = {...lang.methods, ...i18n.methods};
                delete i18n.methods;
            }
            lang[module] = i18n;
        }).always(() => {
            $.getScript("modules/" + module + "/" + module + ".js");
        });
    }
}

function moduleLoaded(module, object) {
    let m = module.split(".");

    if (!modules[module] && m.length === 1 && object) {
        modules[module] = object;
    }

    if (m.length === 2 && modules[m[0]] && object) {
        modules[m[0]][m[1]] = object;
    }

    if (m.length === 1) {
        loadModule();
    }
}

function loadSubModules(parent, subModules, doneOrParentObject) {
    if (!modules[parent] && typeof doneOrParentObject === "object") {
        modules[parent] = doneOrParentObject;
    }
    let module = subModules.shift();
    if (!module) {
        if (typeof doneOrParentObject === "function") {
            doneOrParentObject();
        }
        if (typeof doneOrParentObject === "object") {
            moduleLoaded(parent, doneOrParentObject);
        }
    } else{
        $.getScript("modules/" + parent + "/" + module + ".js").
        done(() => {
            loadSubModules(parent, subModules, doneOrParentObject);
        }).
        fail(FAIL);
    }
}

function formatBytes(bytes) {
    let u = 0;
    for (; bytes > 1024; u++) bytes /= 1024;
    return Math.round(bytes) + ' ' + [ 'B', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y' ][u];
}

function subTop(html) {
    $("#subTop").html(`<div class="info-box mt-2 mb-1" style="min-height: 0px;"><div class="info-box-content"><span class="info-box-text">${html}</span></div></div>`);
}

function hashParse() {
    let hash = location.href.split('#')[1];
    hash = hash?('#' + hash):'';

    $('.dropdownMenu').collapse('hide');
    $('.modal').modal('hide');

    let params = {};
    let route;

    try {
        hash = hash.split('#')[1].split('&');
        route = hash[0]?hash[0]:"default";
        for (let i = 1; i < hash.length; i++) {
            let sp = hash[i].split('=');
            params[sp[0]] = sp[1]?decodeURIComponent(sp[1]):true;
        }
    } catch (e) {
        route = "default";
    }

    return [ route, params, hash ];
}

function escapeHTML(str) {
    if (str && typeof str == "string") {
        let escapeChars = {
            '¢': 'cent',
            '£': 'pound',
            '¥': 'yen',
            '€': 'euro',
            '©':'copy',
            '®': 'reg',
            '<': 'lt',
            '>': 'gt',
            '"': 'quot',
            '&': 'amp',
            '\'': '#39'
        };

        let regexString = '[';

        for(let key in escapeChars) {
            regexString += key;
        }

        regexString += ']';

        let regex = new RegExp(regexString, 'g');

        return str.replace(regex, function(m) {
            return '&' + escapeChars[m] + ';';
        });
    } else {
        return str;
    }
}

Object.defineProperty(Array.prototype, "assoc", {
    value: function (key, target, val) {
        let arr = this;

        for (let i in arr) {
            if (arr[i][key] == target) {
                if (val) {
                    return arr[i][val];
                } else {
                    return arr[i];
                }
            }
        }
    }
});

function isEmpty(v) {
    let f = !!v;

    if (Array.isArray(v)) {
        f = f && v.length;
    }

    if (typeof v == "object" && !Array.isArray(v)) {
        f = f && Object.keys(v).length;
    }

    return !f;
}

function pad2(n) {
    return (n < 10 ? '0' : '') + n;
}

function ttDate(date, dateOnly) {
    if (date) {
        date = new Date(date * 1000);
        if (dateOnly) {
            return date.toLocaleDateString();
        } else {
            return date.toLocaleDateString() + " " + pad2(date.getHours()) + ":" + pad2(date.getMinutes());
        }
    } else {
        return "&nbsp;"
    }
}

function utf8_to_b64(str) {
    return window.btoa(unescape(encodeURIComponent(str)));
}

function b64_to_utf8(str) {
    return decodeURIComponent(escape(window.atob(str)));
}

function trimStr(str, len) {
    if (!len) {
        len = 19;
    }
    let sub = Math.floor((len - 3) / 2);
    if (str.length > len) {
        return str.substring(0, sub) + "..." + str.substring(str.length - sub);
    } else {
        return str;
    }
}

function QUERY(api, method, query, fresh) {
    return $.ajax({
        url: $.cookie("_server") + "/" + encodeURIComponent(api) + "/" + encodeURIComponent(method) + (query?("?" + $.param(query)):""),
        beforeSend: xhr => {
            xhr.setRequestHeader("Authorization", "Bearer " + $.cookie("_token"));
            if (fresh) {
                xhr.setRequestHeader("X-Api-Refresh", "1");
            }
        },
        type: "GET",
        contentType: "json",
    });
}

function GET(api, method, id, fresh) {
    return $.ajax({
        url: $.cookie("_server") + "/" + encodeURIComponent(api) + "/" + encodeURIComponent(method) + ((typeof id !== "undefined" && id !== false)?("/" + encodeURIComponent(id)):""),
        beforeSend: xhr => {
            xhr.setRequestHeader("Authorization", "Bearer " + $.cookie("_token"));
            if (fresh) {
                xhr.setRequestHeader("X-Api-Refresh", "1");
            }
        },
        type: "GET",
        contentType: "json",
    });
}

function AJAX(type, api, method, id, query) {
    return $.ajax({
        url: $.cookie("_server") + "/" + encodeURIComponent(api) + "/" + encodeURIComponent(method) + ((typeof id !== "undefined" && id !== false)?("/" + encodeURIComponent(id)):""),
        beforeSend: xhr => {
            xhr.setRequestHeader("Authorization", "Bearer " + $.cookie("_token"));
        },
        type: type,
        contentType: "json",
        data: query?JSON.stringify(query):null,
    });
}

function POST(api, method, id, query) {
    return AJAX(arguments.callee.name.toString(), api, method, id, query);
}

function PUT(api, method, id, query) {
    return AJAX(arguments.callee.name.toString(), api, method, id, query);
}

function DELETE(api, method, id, query) {
    return AJAX(arguments.callee.name.toString(), api, method, id, query);
}

function FAIL(response) {
    if (response && response.responseJSON && response.responseJSON.error) {
        error(i18n("errors." + response.responseJSON.error), i18n("error"), 30);
        if (response.responseJSON.error == "tokenNotFound") {
            $.cookie("_token", null);
        }
    } else {
        error(i18n("errors.unknown"), i18n("error"), 30);
    }
}

function FAILPAGE(response) {
    if (response && response.responseJSON && response.responseJSON.error) {
        error(i18n("errors." + response.responseJSON.error), i18n("error"), 30);
        pageError(i18n("errors." + response.responseJSON.error));
    } else {
        error(i18n("errors.unknown"), i18n("error"), 30);
        pageError();
    }
    loadingDone();
}

function AVAIL(api, method, request_method) {
    if (request_method) {
        return available && available[api] && available[api][method] && available[api][method][request_method];
    }
    if (method) {
        return available && available[api] && available[api][method];
    }
    if (api) {
        return available && available[api];
    }
}

$(window).off("resize").on("resize", () => {
    if ($("#editorContainer").length) {
        // TODO f..ck!
        let top = 75;
        let height = $(window).height() - top;
        $("#editorContainer").css("height", height + "px");
    }
});