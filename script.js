/* globals JSINFO, DOKU_BASE, jQuery */

/**
 * Statistics script
 */
const plugin_statistics = {
    data: {},

    /**
     * initialize the script
     */
    init: function () {

        // load visitor cookie
        const now = new Date();
        let uid = DokuCookie.getValue('plgstats');
        if (!uid) {
            uid = now.getTime() + '-' + Math.floor(Math.random() * 32000);
            DokuCookie.setValue('plgstats', uid);
        }

        plugin_statistics.data = {
            uid: uid,
            ses: plugin_statistics.get_session(),
            p: JSINFO['id'],
            r: document.referrer,
            sx: screen.width,
            sy: screen.height,
            vx: window.innerWidth,
            vy: window.innerHeight,
            js: 1,
            rnd: now.getTime()
        };

        // log access
        if (JSINFO['act'] === 'show') {
            plugin_statistics.log_view('v');
        } else {
            plugin_statistics.log_view('s');
        }

        // attach outgoing event
        jQuery('a.urlextern').click(plugin_statistics.log_external);

        // attach unload event
        jQuery(window).bind('beforeunload', plugin_statistics.log_exit);
    },

    /**
     * Log a view or session
     *
     * @param {string} act 'v' = view, 's' = session
     */
    log_view: function (act) {
        const params = jQuery.param(plugin_statistics.data);
        const img = new Image();
        img.src = DOKU_BASE + 'lib/plugins/statistics/log.php?do=' + act + '&' + params;
    },

    /**
     * Log clicks to external URLs
     */
    log_external: function () {
        const params = jQuery.param(plugin_statistics.data);
        const url = DOKU_BASE + 'lib/plugins/statistics/log.php?do=o&ol=' + encodeURIComponent(this.href) + '&' + params;
        navigator.sendBeacon(url);
        return true;
    },

    /**
     * Log any leaving action as session info
     */
    log_exit: function () {
        const params = jQuery.param(plugin_statistics.data);

        const ses = plugin_statistics.get_session();
        if (ses !== params.ses) return; // session expired a while ago, don't log this anymore

        const url = DOKU_BASE + 'lib/plugins/statistics/log.php?do=s&' + params;
        navigator.sendBeacon(url);
    },

    /**
     * get current session identifier
     *
     * Auto clears an expired session and creates a new one after 15 min idle time
     *
     * @returns {string}
     */
    get_session: function () {
        const now = new Date();

        // load session cookie
        let ses = DokuCookie.getValue('plgstatsses');
        if (ses) {
            ses = ses.split('-');
            const time = ses[0];
            ses = ses[1];
            if (now.getTime() - time > 15 * 60 * 1000) {
                ses = ''; // session expired
            }
        }
        // assign new session
        if (!ses) {
            //http://stackoverflow.com/a/16693578/172068
            ses = (Math.random().toString(16) + "000000000").substr(2, 8) +
                (Math.random().toString(16) + "000000000").substr(2, 8) +
                (Math.random().toString(16) + "000000000").substr(2, 8) +
                (Math.random().toString(16) + "000000000").substr(2, 8);
        }
        // update session info
        DokuCookie.setValue('plgstatsses', now.getTime() + '-' + ses);

        return ses;
    },
};


jQuery(plugin_statistics.init);
