({
    meta: {},

    defaultIssuesPerPage: 50,
    defaultPagerItemsCount: 10,

    init: function () {
        if (AVAIL("tt", "tt")) {
            leftSide("fas fa-fw fa-tasks", i18n("tt.tt"), "#tt", "tt");
        }
        loadSubModules("tt", [
            "issue",
            "settings",
        ], this);
    },
    
    issueFieldTitle: function (field) {
        let fieldId;

        if (typeof field === "object") {
            fieldId = field.field;
        } else{
            fieldId = field;
        }

        if (fieldId.toString().substring(0, 4) !== "_cf_") {
            // regular issue fields
            switch (fieldId) {
                case "issueId":
                    return i18n("tt.issueId");

                case "subject":
                    return i18n("tt.subject");

                case "description":
                    return i18n("tt.description");

                case "comment":
                    return i18n("tt.comment");

                case "resolution":
                    return i18n("tt.resolution");

                case "status":
                    return i18n("tt.status");

                case "tags":
                    return i18n("tt.tags");

                case "assigned":
                    return i18n("tt.assigned");

                case "watchers":
                    return i18n("tt.watchers");

                case "attachments":
                    return i18n("tt.attachments");

                case "created":
                    return i18n("tt.created");

                case "updated":
                    return i18n("tt.updated");

                case "author":
                    return i18n("tt.author");
    
                case "project":
                    return i18n("tt.project");

                case "workflow":
                    return i18n("tt.workflowName");

                case "commentAuthor":
                    return i18n("tt.commentAuthor");

                case "commentBody":
                    return i18n("tt.commentBody");
    
                case "commentCreated":
                    return i18n("tt.commentCreated");
            
                case "commentPrivate":
                    return i18n("tt.commentPrivate");
        
                case "attachmentFilename":
                    return i18n("tt.attachmentFilename");
    
                case "catalog":
                    return i18n("tt.catalog");
        
                case "parent":
                    return i18n("tt.parent");
        
                default:
                    return fieldId;
            }
        } else {
            // custom field
            fieldId = fieldId.substring(4);

            let cf = false;
            for (let i in modules.tt.meta.customFields) {
                if (modules.tt.meta.customFields[i].field === fieldId) {
                    cf = modules.tt.meta.customFields[i];
                    break;
                }
            }
            
            if (cf) {
                return cf.fieldDisplay;
            } else {
                return fieldId;
            }
        }
    },

    issueField2FormFieldEditor: function (issue, field, projectId, filter) {

        function peoples(project, withGroups, withUsers) {
            let p = [];

            let already = {
                "admin": true,
            };

            if (withGroups) {
                for (let i in project.groups) {
                    for (let j in modules.groups.meta) {
                        if (modules.groups.meta[j].gid == project.groups[i].gid && !already[modules.groups.meta[j].acronym]) {
                            already[modules.groups.meta[j].acronym] = true;
                            p.push({
                                id: modules.groups.meta[j].acronym,
                                text: modules.groups.meta[j].name?modules.groups.meta[j].name:modules.groups.meta[j].acronym,
                                icon: "fas fa-fw fa-users",
                            });
                        }
                    }
                }
            }

            if (withUsers) {
                for (let i in project.users) {
                    for (let j in modules.users.meta) {
                        if (modules.users.meta[j].uid == project.users[i].uid && !already[modules.users.meta[j].login]) {
                            already[modules.users.meta[j].login] = true;
                            if (project.users[i].level > 0) {
                                p.push({
                                    id: modules.users.meta[j].login,
                                    text: modules.users.meta[j].realName?modules.users.meta[j].realName:modules.users.meta[j].login,
                                    icon: "fas fa-fw fa-user",
                                });
                            }
                        }
                    }
                }
            }

            return p;
        }

        function select2Filter(options, filter) {
            if (filter) {
                let o = [];

                for (let i in options) {
                    for (let j in filter) {
                        if (options[i].id == filter[j] || options[i].text == filter[j]) {
                            o.push(options[i]);
                            break;
                        }
                    }
                }

                return o;
            } else {
                return options;
            }
        }

        let fieldId;

        if (typeof field === "object") {
            fieldId = field.field;
        } else{
            fieldId = field;
        }

        let tags = [];
        for (let i in modules.tt.meta.tags) {
            if (modules.tt.meta.tags[i].projectId == projectId) {
                tags.push({
                    id: modules.tt.meta.tags[i].tag,
                    text: modules.tt.meta.tags[i].tag,
                    icon: "fas fa-fw fa-tag",
                });
            }
        }

        let project;

        for (let i in modules.tt.meta.projects) {
            if (modules.tt.meta.projects[i].projectId == projectId) {
                project = modules.tt.meta.projects[i];
            }
        }

        if (fieldId.substring(0, 4) !== "_cf_") {
            // regular issue fields
            switch (fieldId) {
                case "issueId":
                    return {
                        id: "issueId",
                        type: "text",
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        value: (issue && issue.issueId)?issue.issueId:"",
                        hidden: true,
                    };

                case "subject":
                    return {
                        id: "subject",
                        type: "text",
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        value: (issue && issue.subject)?issue.subject:"",
                        validate: v => {
                            return $.trim(v) !== "";
                        },
                    };

                case "description":
                    return {
                        id: "description",
                        type: "area",
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        value: (issue && issue.description)?issue.description:"",
                        validate: v => {
                            return $.trim(v) !== "";
                        },
                    };

                case "comment":
                    return {
                        id: "comment",
                        type: "area",
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        validate: v => {
                            return $.trim(v) !== "";
                        },
                    };

                case "resolution":
                    let resolutions = [];

                    for (let i in modules.tt.meta.resolutions) {
                        if (project.resolutions.indexOf(modules.tt.meta.resolutions[i].resolutionId) >= 0) {
                            resolutions.push({
                                id: modules.tt.meta.resolutions[i].alias,
                                text: modules.tt.meta.resolutions[i].resolution,
                            });
                        }
                    }

                    return {
                        id: "resolution",
                        type: "select2",
                        title: modules.tt.issueFieldTitle(field),
                        options: select2Filter(resolutions, filter),
                        value: (issue && issue.resolution)?issue.resolution:-1,
                        validate: v => {
                            return $.trim(v) !== "";
                        },
                    };

                case "status":
                    let statuses = [];

                    for (let i in modules.tt.meta.statuses) {
                        statuses.push({
                            id: modules.tt.meta.statuses[i].status,
                            text: modules.tt.meta.statuses[i].statusDisplay?modules.tt.meta.statuses[i].statusDisplay:modules.tt.meta.statuses[i].status,
                        });
                    }

                    return {
                        id: "status",
                        type: "select2",
                        title: modules.tt.issueFieldTitle(field),
                        options: select2Filter(statuses, filter),
                        value: (issue && issue.status)?issue.status:-1,
                    };

                case "tags":
                    return {
                        id: "tags",
                        type: "select2",
                        tags: true,
                        createTags: false,
                        multiple: true,
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        options: select2Filter(tags, filter),
                        value: (issue && issue.tags)?Object.values(issue.tags):[],
                    };

                case "assigned":
                    return {
                        id: "assigned",
                        type: "select2",
                        multiple: true,
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        options: select2Filter(peoples(project, true, true), filter),
                        value: (issue && issue.assigned)?Object.values(issue.assigned):[],
                    };

                case "watchers":
                    return {
                        id: "watchers",
                        type: "select2",
                        multiple: true,
                        title: modules.tt.issueFieldTitle(field),
                        placeholder: modules.tt.issueFieldTitle(field),
                        options: select2Filter(peoples(project, false, true), filter),
                        value: (issue && issue.watchers)?Object.values(issue.watchers):[],
                    };

                case "attachments":
                    return {
                        id: "attachments",
                        type: "files",
                        title: modules.tt.issueFieldTitle(field),
                        maxSize: project.maxFileSize,
                    };
            }
        } else {
            // custom field
            fieldId = fieldId.substring(4);

            let cf = false;
            for (let i in modules.tt.meta.customFields) {
                if (modules.tt.meta.customFields[i].field === fieldId) {
                    cf = modules.tt.meta.customFields[i];
                    break;
                }
            }

            if (cf) {
                let validate = false;
                if (cf.required && !cf.regex) {
                    validate = new Function ("v", `return v && $.trim(v) !== "";`);
                } else
                if (!cf.required && cf.regex) {
                    validate = new Function ("v", `return /${cf.regex}/.test(v);`);
                } else
                if (cf.required && cf.regex) {
                    validate = new Function ("v", `return v && $.trim(v) !== "" && /${cf.regex}/.test(v);`);
                }

                let options = [];

                switch (cf.type) {
                    case "text":
                        if ([ "text", "number", "area", "email", "tel", "date", "time", "datetime-local", "yesno" ].indexOf(cf.editor) < 0) {
                            cf.editor = "text";
                        }

                        return {
                            id: "_cf_" + fieldId,
                            type: cf.editor,
                            title: modules.tt.issueFieldTitle(field),
                            placeholder: modules.tt.issueFieldTitle(field),
                            hint: cf.fieldDescription?cf.fieldDescription:false,
                            value: (issue && issue["_cf_" + fieldId])?issue["_cf_" + fieldId]:"",
                            validate: validate,
                        }

                    case "select":
                        for (let i in cf.options) {
                            options.push({
                                id: cf.options[i].option,
                                text: cf.options[i].optionDisplay,
                            });
                        }
                        return {
                            id: "_cf_" + fieldId,
                            type: "select2",
                            title: modules.tt.issueFieldTitle(field),
                            placeholder: modules.tt.issueFieldTitle(field),
                            hint: cf.fieldDescription?cf.fieldDescription:false,
                            options: select2Filter(options, filter),
                            multiple: cf.format.indexOf("multiple") >= 0,
                            value: (issue && issue["_cf_" + fieldId])?issue["_cf_" + fieldId]:[],
                            validate: validate,
                        }

                    case "users":
                        if (cf.format.split(" ").includes("users")) {
                            options = peoples(project, false, true);
                        } else
                        if (cf.format.split(" ").includes("groups")) {
                            options = peoples(project, true, false);
                        } else
                        if (cf.format.split(" ").includes("usersAndGroups")) {
                            options = peoples(project, true, true);
                        }
                        return {
                            id: "_cf_" + fieldId,
                            type: "select2",
                            title: modules.tt.issueFieldTitle(field),
                            placeholder: modules.tt.issueFieldTitle(field),
                            hint: cf.fieldDescription?cf.fieldDescription:false,
                            options: select2Filter(options, filter),
                            multiple: cf.format.indexOf("multiple") >= 0,
                            value: (issue && issue["_cf_" + fieldId])?issue["_cf_" + fieldId]:[],
                            validate: validate,
                        }

                    case "geo":
                        let vx;
                        
                        if (issue && issue["_cf_" + fieldId]) {
                            vx = issue["_cf_" + fieldId];
                            options = [
                                {
                                    id: issue["_cf_" + fieldId],
                                    text: issue["_cf_" + fieldId],
                                },
                            ];
                        }
                        
                        return {
                            id: "_cf_" + fieldId,
                            type: "select2",
                            title: modules.tt.issueFieldTitle(field),
                            placeholder: modules.tt.issueFieldTitle(field),
                            hint: cf.fieldDescription?cf.fieldDescription:false,
                            options: select2Filter(options, filter),
                            value: (issue && issue["_cf_" + fieldId])?issue["_cf_" + fieldId]:[],
                            validate: validate,
                            ajax: {
                                delay: 1000,
                                transport: function (params, success, failure) {
                                    loadingStart();
                                    QUERY("geo", "suggestions", {
                                        search: params.data.term,
                                    }).
                                    then(response => {
                                        loadingDone();
                                        success(response);
                                    }).
                                    fail(response => {
                                        FAIL(response);
                                        loadingDone();
                                        failure(response);
                                    }).
                                    fail(FAIL).
                                    always(loadingDone);
                                },
                                processResults: function (data) {
                                    let suggestions = options;
                                    for (let i in data.suggestions) {
                                        let vl = " [ " + data.suggestions[i].data.geo_lon + ", " + data.suggestions[i].data.geo_lat + " ]: " + data.suggestions[i].value;
                                        if (parseInt(data.suggestions[i].data.fias_level) === 8 && vx !== vl) {
                                            suggestions.push({
                                                id: vl,
                                                text: vl,
                                            });
                                        }
                                    }
                                    return {
                                        results: suggestions,
                                    };
                                },
                            },
                        }
    
                    case "issues":
                        let vi = [];
                        options = [];

                        if (issue && issue["_cf_" + fieldId]) {
                            let va;

                            if (typeof issue["_cf_" + fieldId] == "string") {
                                va = [ issue["_cf_" + fieldId] ];
                            } else {
                                va = issue["_cf_" + fieldId];
                            }
                            for (let i in va) {
                                vi.push(va[i]);
                                options.push({
                                    id: va[i],
                                    text: va[i],
                                });
                            }
                        }

                        return {
                            id: "_cf_" + fieldId,
                            type: "select2",
                            title: modules.tt.issueFieldTitle(field),
                            placeholder: modules.tt.issueFieldTitle(field),
                            hint: cf.fieldDescription?cf.fieldDescription:false,
                            options: select2Filter(options, filter),
                            multiple: cf.format.indexOf("multiple") >= 0,
                            value: vi,
                            validate: validate,
                            ajax: {
                                delay: 1000,
                                transport: function (params, success, failure) {
                                    loadingStart();
                                    QUERY("tt", "issues", {
                                        project: project.acronym,
                                        filter: "#search",
                                        skip: 0,
                                        limit: 32768,
                                        search: params.data.term,
                                    }).
                                    then(response => {
                                        loadingDone();
                                        success(response);
                                    }).
                                    fail(response => {
                                        FAIL(response);
                                        loadingDone();
                                        failure(response);
                                    }).
                                    fail(FAIL).
                                    always(loadingDone);
                                },
                                processResults: function (data) {
                                    let suggestions = options;
                                    for (let i in data.issues.issues) {
                                        let vl = "[ " + data.issues.issues[i].issueId + " ] " + data.issues.issues[i].subject;
                                        if (vi.indexOf(vl) < 0) {
                                            suggestions.push({
                                                id: vl,
                                                text: vl,
                                            });
                                        }
                                    }
                                    return {
                                        results: suggestions,
                                    };
                                },
                            },
                        }
                }
            }
        }
    },

    issueField2Html: function (issue, field, val) {
        let members = {};

        if (modules.groups) {
            for (let i in modules.groups.meta) {
                members[modules.groups.meta[i].acronym] = modules.groups.meta[i].name?modules.groups.meta[i].name:modules.groups.meta[i].acronym;
            }
        }

        for (let i in modules.users.meta) {
            members[modules.users.meta[i].login] = modules.users.meta[i].realName?modules.users.meta[i].realName:modules.users.meta[i].login;
        }

        let project;
        for (let i in modules.tt.meta.projects) {
            if (modules.tt.meta.projects[i].acronym == issue.project || modules.tt.meta.projects[i].acronym == issue.issueId.split("-")[0]) {
                project = modules.tt.meta.projects[i];
                break;
            }
        }

        let v = false;
        for (let i in project.viewers) {
            if (project.viewers[i].field == field) {
                v = project.viewers[i].name;
                break;
            }
        }

        if (typeof val === "undefined") {
            val = issue[field];
        }

        if (v && modules.tt.viewers[field] && typeof modules.tt.viewers[field][v] == "function") {
            val = modules.tt.viewers[field][v](val, issue, field);
        } else {
            if (field.substring(0, 4) !== "_cf_") {
                switch (field) {
                    case "description":
                    case "subject":
                    case "commentBody":
                        val = nl2br(escapeHTML(val));
                        break;
    
                    case "assigned":
                    case "watchers":
                        let m = "";
    
                        for (let i in val) {
                            m += members[val[i]]?members[val[i]]:val[i];
                            m += ", ";
                        }
    
                        if (m) {
                            m = m.substring(0, m.length - 2);
                        }
    
                        val = escapeHTML(m);
                        break;
    
                    case "author":
                        val = escapeHTML(members[val]?members[val]:val);
                        break;
    
                    case "commentPrivate":
                        val = val ? i18n("yes") : i18n("no");
                        break;
        
                    case "status":
                        for (let i in modules.tt.meta.statuses) {
                            if (val == modules.tt.meta.statuses[i].status) {
                                val = escapeHTML(modules.tt.meta.statuses[i].statusDisplay?modules.tt.meta.statuses[i].statusDisplay:modules.tt.meta.statuses[i].status);
                                break;
                            }
                        }
                        break;
    
                    case "resolution":
                        if (val) {
                            for (let i in modules.tt.meta.resolutions) {
                                if (val == modules.tt.meta.resolutions[i].alias) {
                                    val = escapeHTML(modules.tt.meta.resolutions[i].resolution?modules.tt.meta.resolutions[i].resolution:modules.tt.meta.resolution[i].alias);
                                    break;
                                }
                            }
                        } else {
                            val = '';
                        }
                        break;
                    
                    case "project":
                        for (let i in modules.tt.meta.projects) {
                            if (modules.tt.meta.projects[i].acronym == val) {
                                val = modules.tt.meta.projects[i].project?modules.tt.meta.projects[i].project:val;
                                break;
                            }
                        }
                        break;

                    case "workflow":
                        for (let i in modules.tt.meta.workflows) {
                            if (i == val) {
                                val = modules.tt.meta.workflows[i].name?modules.tt.meta.workflows[i].name:val;
                                if (val.charAt(0) == "#") {
                                    val = val.substring(1);
                                }
                                break;
                            }
                        }
                        break;

                    case "parent":
                        val = `<span class='hoverable ttIssue text-bold'>${val}</span>`;
                        break;
    
                    case "created":
                    case "updated":
                    case "commentCreated":
                        val = ttDate(val);
                        break;
                }
            } else {
                field = field.substring(4);

                let cf = {};
                let multiple = false;

                for (let i in modules.tt.meta.customFields) {
                    if (modules.tt.meta.customFields[i].field == field) {
                        cf = modules.tt.meta.customFields[i];
                        multiple = cf.format.indexOf("multiple") >= 0;
                    }
                }

                switch (cf.type) {
                    case "geo":
                        if (val) {
                            let lon = $.trim(val.split("[")[1].split(",")[0]);
                            let lat = $.trim(val.split("[")[1].split(",")[1].split("]")[0]);
    
                            val = `<a target="_blank" class="hoverable" href="https://yandex.ru/maps/13/tambov/?ll=${lon}%2C${lat}&mode=whatshere&whatshere%5Bpoint%5D=${lon}%2C${lat}&whatshere%5Bzoom%5D=19.33&z=19">${escapeHTML(val)}</a>`;
                        } else {
                            val = '';
                        }
                        break;

                    case "issues":
                        if (val) {
                            if (typeof val == "string") {
                                val = [ val ];
                            }

                            let t = "";

                            for (let i in val) {
                                let issueId = $.trim(val[i].split("[")[1].split("]")[0]);
                                let subject = $.trim(val[i].substring(val[i].indexOf("]") + 1));
                                t += `<a href="?#tt&issue=${encodeURIComponent(issueId)}" class="hoverable">[ ${issueId} ]: ${escapeHTML(subject)}</a><br/>`;
                            }
    
                            val = t;
                        } else {
                            val = '';
                        }
                        break;

                    case "text":
                        if (cf.format) {
                            val = sprintf(cf.format, val);
                        }
                        
                        val = nl2br(escapeHTML(val));

                        if (cf.link) {
                            val = "<a href='" + cf.link.replaceAll('%value%', val) + "' target='_blank' class='hover'>" + val + "</a>";
                        }

                        break;

                    case "select":
                        if (multiple) {
                            let s = "";
                            let x = [];
    
                            for (let i in val) {
                                x.push(val[i]);
                            }
    
                            for (let i in cf.options) {
                                if (x.indexOf(cf.options[i].option) >= 0) {
                                    s += '<i class="far fa-fw fa-check-square mr-1"></i>';
                                } else {
                                    s += '<i class="far fa-fw fa-square mr-1"></i>';
                                }
                                s += cf.options[i].optionDisplay + "<br/>";
                            }
    
                            val = s;
                        }
                        break;

                    case "users":
                        console.log(field);
                        console.log(cf);
                        console.log(val);
                        console.log(typeof val);

                        if (typeof val == "array") {
                            let m = "";
    
                            for (let i in val) {
                                m += members[val[i]]?members[val[i]]:val[i];
                                m += ", ";
                            }
        
                            if (m) {
                                m = m.substring(0, m.length - 2);
                            }
        
                            val = escapeHTML(m);
                        } else {
                            val = escapeHTML(members[val]?members[val]:val);
                        }
                        break;

                    default:
                        break;
                }
            }
        }

        return val;
    },

    tt: function (tt) {
        modules.tt.meta = tt["meta"];

        if (!modules.tt.viewers) {
            modules.tt.viewers = {};
        }

        for (let i in modules.tt.meta.viewers) {
            if (!modules.tt.viewers[modules.tt.meta.viewers[i].field]) {
                modules.tt.viewers[modules.tt.meta.viewers[i].field] = {};
            }
            try {
                modules.tt.viewers[modules.tt.meta.viewers[i].field][modules.tt.meta.viewers[i].name] = new Function('value', 'issue', 'field', modules.tt.meta.viewers[i].code);
            } catch (e) {
                modules.tt.viewers[modules.tt.meta.viewers[i].field][modules.tt.meta.viewers[i].name] = new Function('value', 'issue', 'field', "//function $name (val, field, issue) {\n\treturn val;\n//}\n");
            }
        }
    },

    selectFilter: function (filter, skip, limit, search) {
        if (filter) {
            if (filter !== "#search") {
                $.cookie("_tt_issue_filter_" + $("#ttProjectSelect").val(), filter, { expires: 3650, insecure: config.insecureCookie });
            }
        } else {
            filter = $.cookie("_tt_issue_filter_" + $("#ttProjectSelect").val());
        }
        window.location.href = `?#tt&filter=${encodeURIComponent(filter)}&skip=${skip?skip:0}&limit=${limit?limit:modules.tt.defaultIssuesPerPage}&search=${encodeURIComponent(($.trim(search) && typeof search === "string")?$.trim(search):"")}&_refresh=${Math.random()}`;
    },

    selectProject: function (project) {
        $.cookie("_project", project, { expires: 3650, insecure: config.insecureCookie });
        window.location.href = `?#tt&project=${encodeURIComponent(project)}`;
    },

    renderIssues: function (params) {
        let rtd = '';

        let current_project = params["project"]?params["project"]:$.cookie("_project");

        let pn = {};

        for (let i in modules.tt.meta.projects) {
            pn[modules.tt.meta.projects[i].acronym] = modules.tt.meta.projects[i].project;
        }

        if (Object.keys(modules.tt.meta.myRoles).length) {
            let cog = "mt-1";
            if (AVAIL("tt", "project", "POST")) {
                cog = "";
            }
            rtd += `<div class="form-inline"><div class="input-group input-group-sm mr-2 ${cog}"><select id="ttProjectSelect" class="form-control">`;
            for (let j in modules.tt.meta.myRoles) {
                if (j == current_project) {
                    rtd += `<option selected="selected" value="${j}">${pn[j]} [${j}]</option>`;
                } else {
                    rtd += `<option value="${j}">${pn[j]} [${j}]</option>`;
                }
            }
            rtd += `</select></div>`;
            rtd += '<form autocomplete="off">';
            rtd += `<div class="input-group input-group-sm ${cog} ttSearchInputGroup">`;
            rtd += `<input id="ttSearch" class="form-control" type="search" aria-label="Search" autocomplete="off"><div class="input-group-append"><button class="btn btn-default" id="ttSearchButton" title="${i18n("tt.search")}"><i class="fas fa-search"></i></button></div></div>`;
            if (AVAIL("tt", "project", "POST")) {
                rtd += `<div class="nav-item mr-0 pr-0"><a href="#tt.settings&edit=projects" class="nav-link text-primary mr-0 pr-0" role="button" style="cursor: pointer" title="${i18n("tt.settings")}"><i class="fas fa-lg fa-fw fa-cog"></i></a></div>`;
            }
            rtd += `</div>`;
            rtd += '</form>';
        } else {
            if (AVAIL("tt", "project", "POST")) {
                rtd += `<div class="nav-item mr-0 pr-0"><a href="#tt.settings&edit=projects" class="nav-link text-primary mr-0 pr-0" role="button" style="cursor: pointer" title="${i18n("tt.settings")}"><i class="fas fa-lg fa-fw fa-cog"></i></a></div>`;
            }
        }

        $("#rightTopDynamic").html(rtd);

        current_project = $("#ttProjectSelect").val();

        if (AVAIL("tt", "customFilter") && current_project && current_project !== true) {
            $(".ttSearchInputGroup").prepend(`<div class="input-group-prepend"><span class="input-group-text pointer-input-group ttFilterCustom" title="${i18n("tt.customSearch")}"><i class="fas fa-fw fa-running"></i></span></div>`);
        }

        $("#ttProjectSelect").off("change").on("change", () => {
            modules.tt.selectProject($("#ttProjectSelect").val());
        });

        let project = false;

        for (let i in modules.tt.meta.projects) {
            if (modules.tt.meta.projects[i].acronym == current_project) {
                project = modules.tt.meta.projects[i];
            }
        }

        if (Object.keys(modules.tt.meta.myRoles).length) {
            $("#ttProjectSelect").css("width", $("#ttSearch").parent().css("width"));
        }

        $("#ttSearch").off("keypress").on("keypress", function (e) {
            if (e.keyCode == 13) {
                $("#ttSearchButton").click();
            }
        });

        $(".ttFilterCustom").off("click").on("click", () => {
            location.href = '?#tt&filter=empty&customSearch=yes&_refresh=' + Math.random();
        });

        $("#ttSearchButton").off("click").on("click", () => {
            let s = $.trim($("#ttSearch").val());
            if (s) {
                modules.tt.selectFilter("#search", 0, modules.tt.defaultIssuesPerPage, s);
            }
        });

        if ($.trim(params.search) && params.search !== true) {
            $("#ttSearch").val($.trim(params.search));
        }

        let x = false;

        try {
            x = params["filter"]?params["filter"]:$.cookie("_tt_issue_filter_" + current_project);
        } catch (e) {
            //
        }

        let fcount = 0;
        let filters = `<span class="dropdown">`;

        filters += `<span class="pointer dropdown-toggle dropdown-toggle-no-icon text-primary text-bold" id="ttFilter" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" style="margin-left: -4px;"><i class="far fa-fw fa-caret-square-down mr-1 ml-1"></i>${modules.tt.meta.filters[x]?modules.tt.meta.filters[x]:i18n("tt.filter")}</span>`;
        filters += `<ul class="dropdown-menu" aria-labelledby="ttFilter">`;
        let personal = "user";
        for (let i in project.filters) {
            if (parseInt(project.filters[i].personal) > 1000000) {
                if (personal === "user") {
                    if (fcount) {
                        filters += `<li class="dropdown-divider"></li>`;
                    }
                    personal = "group";
                }
            }
            if (!parseInt(project.filters[i].personal)) {
                if (personal === "group" || personal === "user") {
                    if (fcount) {
                        filters += `<li class="dropdown-divider"></li>`;
                    }
                    personal = "common";
                }
            }
            if (x == project.filters[i].filter) {
                filters += `<li class="pointer dropdown-item tt_issues_filter text-bold" data-filter-name="${project.filters[i].filter}">${project.filters[i].filter?modules.tt.meta.filters[project.filters[i].filter]:project.filters[i].filter}</li>`;
            } else {
                filters += `<li class="pointer dropdown-item tt_issues_filter" data-filter-name="${project.filters[i].filter}">${project.filters[i].filter?modules.tt.meta.filters[project.filters[i].filter]:project.filters[i].filter}</li>`;
            }
            fcount++;
        }
        filters += `</ul></span>`;

        let fp = -1;
        for (let i in project.filters) {
            if (project.filters[i].filter == x) {
                fp = project.filters[i].personal;
                break;
            }
        }

        if ($.trim(modules.tt.meta.filters[x]) + "-" + md5($.cookie("_login") + ":" + $.trim(modules.tt.meta.filters[x])) == x && fp == myself.uid) {
            filters += '<span class="ml-4 hoverable customFilterEdit text-info" data-filter="' + x + '"><i class="far fa-fw fa-edit"></i> ' + i18n("tt.customFilterEdit") + '</span>';
            filters += '<span class="ml-2 hoverable customFilterDelete text-danger" data-filter="' + x + '"><i class="far fa-fw fa-trash-alt"></i> ' + i18n("tt.customFilterDelete") + '</span>';
        }

        if (!fcount) {
            filters = `<span class="text-bold text-warning">${i18n('tt.noFiltersAvailable')}</span>`;
        }

        if (myself.uid) {
            $("#leftTopDynamic").html(`<li class="nav-item d-none d-sm-inline-block"><a href="javascript:void(0)" class="nav-link text-success text-bold createIssue">${i18n("tt.createIssue")}</a></li>`);
        }

        $(".createIssue").off("click").on("click", () => {
            modules.tt.issue.createIssue($("#ttProjectSelect").val());
        });

        document.title = i18n("windowTitle") + " :: " + i18n("tt.tt");

        let skip = parseInt(params.skip?params.skip:0);
        let limit = parseInt(params.limit?params.limit:modules.tt.defaultIssuesPerPage);

        let _refresh = Math.random();

        QUERY("tt", "issues", {
            "project": current_project,
            "filter": x?x:'',
            "skip": skip,
            "limit": limit,
            "search": ($.trim(params.search) && params.search !== true)?$.trim(params.search):'',
        }, true).
        done(response => {
            if (response.issues.exception) {
                error(i18n("errors." + response.issues.exception), i18n("error"), 30);
            }

            let issues = response.issues;

            limit = parseInt(issues.limit);
            skip = parseInt(issues.skip);

            let page = Math.floor(skip / limit) + 1;

            function pager() {
                let h = '';

                let pages = Math.ceil(issues.count / limit);
                let delta = Math.floor(modules.tt.defaultPagerItemsCount / 2);
                let first = Math.max(page - delta, 1);
                let preFirst = Math.max(0, 1 - page + delta);
                let last = Math.min(page + delta, pages);
                let postLast = Math.max(pages, page + delta) - pages;

                if (last + preFirst - first + postLast >= modules.tt.defaultPagerItemsCount) {
                    if (first > 1) {
                        first++;
                    } else {
                        last--;
                    }
                }

                h += '<nav>';
                h += '<ul class="pagination mb-0 ml-0">';

                if (page > 1) {
                    h += `<li class="page-item pointer tt_pager" data-page="1"><span class="page-link"><span aria-hidden="true">&laquo;</span></li>`;
                } else {
                    h += `<li class="page-item disabled"><span class="page-link"><span aria-hidden="true">&laquo;</span></li>`;
                }
                for (let i = Math.max(first - postLast, 1); i <= Math.min(last + preFirst, pages); i++) {
                    if (page == i) {
                        h += `<li class="page-item font-weight-bold disabled" data-page="${i}"><span class="page-link">${i}</span></li>`;
                    } else {
                        h += `<li class="page-item pointer tt_pager" data-page="${i}"><span class="page-link">${i}</span></li>`;
                    }
                }
                if (page < pages) {
                    h += `<li class="page-item pointer tt_pager" data-page="${pages}"><span class="page-link"><span aria-hidden="true">&raquo;</span></li>`;
                } else {
                    h += `<li class="page-item disabled"><span class="page-link"><span aria-hidden="true">&raquo;</span></li>`;
                }

                h += '</ul>';
                h += '</nav>';

                return h;
            }

            let cs = '';

            if (params.customSearch && params.customSearch !== true) {
                let height = 400;
                cs += '<div class="ml-2 mr-2">';
                cs += `<div id='editorContainer' style='width: 100%; height: ${height}px;'>`;
                cs += `<pre class="ace-editor mt-2" id="filterEditor" style="position: relative; border: 1px solid #ced4da; border-radius: 0.25rem; width: 100%; height: 100%;"></pre>`;
                cs += "</div>";
                cs += `<span style='position: absolute; right: 35px; top: 35px;'>`;
                cs += `<span id="filterRun" class="hoverable"><i class="fas fa-running pr-2"></i>${i18n("tt.filterRun")}</span>`;
                cs += `</span>`;
                cs += '</div>';
            }

            $("#mainForm").html(`${cs}<table class="mt-2 ml-2" style="width: 100%;"><tr><td style="width: 100%;">${cs?'&nbsp;':filters}</td><td style="padding-right: 15px;">${pager()}</td></tr></table><div class="ml-2 mr-2" id="issuesList"></div>`);

            $(".tt_issues_filter").off("click").on("click", function () {
                modules.tt.selectFilter($(this).attr("data-filter-name"));
            });

            $(".tt_pager").off("click").on("click", function () {
                modules.tt.selectFilter(false, Math.max(0, (parseInt($(this).attr("data-page")) - 1) * limit));
            });

            let columns = [ {
                title: i18n("tt.issueIndx"),
                nowrap: true,
            } ];

            let pKeys = [];

            if (issues.projection) {
                pKeys = Object.keys(issues.projection);
            }

            for (let i = 0; i < pKeys.length; i++) {
                columns.push({
                    title: modules.tt.issueFieldTitle(pKeys[i]),
                    nowrap: true,
                    fullWidth: i == pKeys.length - 1,
                });
            };

            if (params.customSearch && params.customSearch !== true) {
                let editor = ace.edit("filterEditor");
                editor.setTheme("ace/theme/chrome");
                editor.session.setMode("ace/mode/json");
                editor.clearSelection();
                editor.setFontSize(14);
                $("#filterRun").off("click").on("click", () => {
                    let f = false;
                    try {
                        f = JSON.parse($.trim(editor.getValue()));
                    } catch (e) {
                        f = false;
                    }
                    if (f && $.trim(f.name) && f.fields) {
                        let n = $.trim(f.name) + "-" + md5($.cookie("_login") + ":" + $.trim(f.name));
                        loadingStart();
                        PUT("tt", "customFilter", n, { "project": current_project, "body": $.trim(editor.getValue()) }).
                        done(() => {
                            message(i18n("tt.filterWasSaved"));
                            $.cookie("_tt_issue_filter_" + current_project, n, { expires: 3650, insecure: config.insecureCookie });
                            location.href = '?#tt&filter=' + n + '&customSearch=yes&_refresh=' + Math.random();
                        }).
                        fail(FAIL).
                        fail(loadingDone);
                    }
                });
                if (params.filter && params.filter !== true && params.filter != "empty") {
                    GET("tt", "customFilter", params.filter).
                    done(response => {
                        editor.setValue(response.body, -1);
                        loadingDone();
                    });
                }
            }

            $(".customFilterEdit").off("click").on("click", function () {
                location.href = '?#tt&filter=' + $(this).attr("data-filter") + '&customSearch=yes&_refresh=' + Math.random();
            });

            $(".customFilterDelete").off("click").on("click", function () {
                let f = $(this).attr("data-filter");
                mConfirm(i18n("tt.filterDelete", modules.tt.meta.filters[f]), i18n("confirm"), i18n("delete"), () => {
                    loadingStart();
                    DELETE("tt", "customFilter", f, { "project": current_project }).
                    done(() => {
                        message(i18n("tt.filterWasDeleted"));
                        $.cookie("_tt_issue_filter_" + current_project, null);
                        location.href = '?#tt&_refresh=' + Math.random();
                    }).
                    fail(FAIL).
                    fail(loadingDone);
                });
            });

            if (issues.issues) {
                cardTable({
                    target: "#issuesList",
                    columns: columns,
                    rows: () => {
                        let rows = [];

                        for (let i = 0; i < issues.issues.length; i++) {

                            let cols = [ {
                                data: i + skip + 1,
                                nowrap: true,
                                click: modules.tt.issue.viewIssue,
                            } ];

                            for (let j = 0; j < pKeys.length; j++) {
                                cols.push({
                                    data: modules.tt.issueField2Html(issues.issues[i], pKeys[j]),
                                    nowrap: true,
                                    click: modules.tt.issue.viewIssue,
                                    fullWidth: j == pKeys.length - 1,
                                });
                            }

                            rows.push({
                                uid: utf8_to_b64(JSON.stringify({
                                    id: issues.issues[i]["issueId"],
                                    filter: x?x:"",
                                    index: i + skip + 1,
                                    count: parseInt(issues.count)?parseInt(issues.count):modules.tt.defaultIssuesPerPage,
                                    search: ($.trim(params.search) && params.search !== true)?$.trim(params.search):"",
                                })),
                                cols: cols,
                            });
                        }

                        return rows;
                    },
                });
            } else {
                $("#issuesList").append(`<span class="ml-1 text-bold">${i18n("tt.noIssuesAvailable")}</span>`);
            }
            if (!params.customSearch || params.customSearch === true || !params.filter || params.filter === true || params.filter == "empty") {
                loadingDone();
            }
        }).
        fail(FAIL).
        fail(() => {
            $.cookie("_tt_issue_filter_" + $("#ttProjectSelect").val(), null);
            $.cookie("_tt_issue_filter_" + $.cookie("_project"), null);
            $.cookie("_project", null);
            if (params["_refresh"] != _refresh) {
                window.location.href = `?#tt&_refresh=${_refresh}`;
            }
        });
    },

    route: function (params) {
        loadingStart();

        $("#subTop").html("");
        $("#altForm").hide();

        GET("tt", "tt", false, true).
        done(modules.tt.tt).
        done(() => {
            if (params["issue"]) {
                GET("tt", "issue", params["issue"], true).
                done(r => {
                    if (modules.groups && AVAIL("accounts", "groups")) {
                        modules.users.loadUsers(() => {
                            modules.groups.loadGroups(() => {
                                modules.tt.issue.renderIssue(r.issue, params["filter"], params["index"], params["count"], params["search"]);
                            });
                        });
                    } else {
                        modules.users.loadUsers(() => {
                            modules.tt.issue.renderIssue(r.issue, params["filter"], params["index"], params["count"], params["search"]);
                        });
                    }
                }).
                fail(FAIL).
                fail(loadingDone);
            } else {
                if (myself.uid) {
                    if (modules.groups) {
                        modules.users.loadUsers(() => {
                            modules.groups.loadGroups(() => {
                                modules.tt.renderIssues(params);
                            });
                        });
                    } else {
                        modules.users.loadUsers(() => {
                            modules.tt.renderIssues(params);
                        });
                    }
                } else {
                    window.location.href = "?#tt.settings&edit=projects";
                }
            }
        }).
        fail(FAIL).
        fail(loadingDone);
    },
}).init();