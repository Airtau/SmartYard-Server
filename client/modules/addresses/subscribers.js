({
    init: function () {
        // submodule - module<dot>submodule
        moduleLoaded("addresses.subscribers", this);
    },

    renderSubscribers: function (target, targetId, formTarget) {
        loadingStart();

    },

    route: function (params) {
        subTop(params.house + ", " + params.flat);


        loadingDone();
    }
}).init();
