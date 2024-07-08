({
    menuItem: false,

    init: function () {
        if (parseInt(myself.uid) > 0) {
            if (AVAIL("notes")) {
                this.menuItem = leftSide("fas fa-fw fa-thumbtack", i18n("notes.notes"), "?#notes", "notes");
            }
        }

        moduleLoaded("notes", this);
    },

    createNote: function () {
        let icons = [];
        for (let i in faIcons) {
            icons.push({
                icon: faIcons[i].title + " fa-fw",
                text: faIcons[i].title.split(" fa-")[1] + (faIcons[i].searchTerms.length ? (", " + faIcons[i].searchTerms.join(", ")) : ""),
                value: faIcons[i].title,
            });
        }
        let fonts = [];
        for (let i in availableFonts) {
            fonts.push({
                text: availableFonts[i],
                value: availableFonts[i],
                font: availableFonts[i],
            });
        }
        cardForm({
            title: i18n("notes.addNote"),
            footer: true,
            borderless: true,
            topApply: true,
            apply: i18n("add"),
            size: "lg",
            fields: [
                {
                    id: "subject",
                    title: i18n("notes.subject"),
                    type: "text",
                },
                {
                    id: "body",
                    title: i18n("notes.body"),
                    type: "area",
                },
                {
                    id: "category",
                    title: i18n("notes.category"),
                    type: "select2",
                },
                {
                    id: "remind",
                    title: i18n("notes.remind"),
                    type: "datetime-local",
                },
                {
                    id: "icon",
                    title: i18n("notes.icon"),
                    type: "select2",
                    options: icons,
                    value: "fas fa-thumbtack",
                },
                {
                    id: "font",
                    title: i18n("notes.font"),
                    type: "select2",
                    options: fonts,
                },
                {
                    id: "color",
                    title: i18n("notes.color"),
                    type: "select2",
                    options: [
                        {
                            text: "По умолчанию",
                            value: "bg-warning",
                            icon: "p-1 fas fa-palette bg-warning",
                        },
                        {
                            text: "Primary",
                            value: "bg-primary",
                            icon: "p-1 fas fa-palette bg-primary",
                        },
                        {
                            text: "Secondary",
                            value: "bg-secondary",
                            icon: "p-1 fas fa-palette bg-secondary",
                        },
                        {
                            text: "Success",
                            value: "bg-success",
                            icon: "p-1 fas fa-palette bg-success",
                        },
                        {
                            text: "Danger",
                            value: "bg-danger",
                            icon: "p-1 fas fa-palette bg-danger",
                        },
                        {
                            text: "Info",
                            value: "bg-info",
                            icon: "p-1 fas fa-palette bg-info",
                        },
                        {
                            text: "Purple",
                            value: "bg-purple",
                            icon: "p-1 fas fa-palette bg-purple",
                        },
                        {
                            text: "Orange",
                            value: "bg-orange",
                            icon: "p-1 fas fa-palette bg-orange",
                        },
                    ],
                    value: "bg-warning",
                },
            ],
            callback: r => {
                //
            },
        });
    },

    renderNotes: function() {
        $("#mainForm").html(`<div id="stickies-container" style="position: relative;"></div>`);

        let isDragging = false;
        let dragTarget;

        let lastOffsetX = 0;
        let lastOffsetY = 0;

        function createSticky(x) {
            const stickyArea = $('#stickies-container');

            const newSticky = `<div class='drag sticky' style='z-index: 1;'><h3>subject ${x}</h3><p>body</p><span class="deletesticky">&times;</span></div>`;

            stickyArea.append(newSticky);

            /*
            console.log(1);
            positionSticky(newSticky);
            console.log(2);
            */
        }

        function positionSticky(sticky) {
            sticky.style.left = window.innerWidth / 2 - sticky.clientWidth / 2 + (-100 + Math.round(Math.random() * 50)) + 'px';
            sticky.style.top = window.innerHeight / 2 - sticky.clientHeight / 2 + (-100 + Math.round(Math.random() * 50)) + 'px';
          }


        window.addEventListener('mousedown', e => {
            let target = $(e.target);
            if (!target.hasClass('drag')) {
                return;
            }
            let z = 1;
            $(".sticky").each(function () {
                let mz = parseInt($(this).css("z-index"));
                if (mz > z) {
                    z = mz;
                }
            });
            console.log(z);
            target.css("z-index", parseInt(z) + 1);
            dragTarget = target;
            lastOffsetX = e.offsetX;
            lastOffsetY = e.offsetY;
            isDragging = true;
        });

        window.addEventListener('mousemove', e => {
            if (!isDragging) return;

            let cont = $('#stickies-container').offset();

            dragTarget.css({
                left: -cont.left + e.clientX - lastOffsetX + 'px',
                top: -cont.top + e.clientY - lastOffsetY + 'px',
            });
        });

        window.addEventListener('mouseup', () => (isDragging = false));

        loadingDone();

        setTimeout(() => {
            createSticky(1);
        }, 100);

        setTimeout(() => {
            createSticky(2);
        }, 100);
    },

    route: function (params) {
        subTop();
        $("#altForm").hide();

        document.title = i18n("windowTitle") + " :: " + i18n("notes.notes");

        if (modules.notes.menuItem) {
            $("#" + modules.notes.menuItem).children().first().attr("href", "?#notes&_=" + Math.random());
        }

        if (parseInt(myself.uid) && AVAIL("notes")) {
            $("#leftTopDynamic").html(`<li class="nav-item d-none d-sm-inline-block"><span class="hoverable pointer nav-link text-success text-bold createNote">${i18n("notes.createNote")}</span></li>`);
        }

        $(".createNote").off("click").on("click", () => {
            modules.notes.createNote();
        });

        modules.notes.renderNotes();
    },
}).init();