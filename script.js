/* globals JSINFO, DOKU_BASE, DokuCookie */

/**
 * Modern Statistics Plugin
 */
class StatisticsPlugin {
    constructor() {
        this.data = {};
        this.sessionTimeout = 15 * 60 * 1000; // 15 minutes
        this.uid = this.initializeUserTracking();
        this.sessionId = this.getSession();
    }

    /**
     * Initialize the statistics plugin
     */
    async init() {
        try {
            this.buildTrackingData();
            await this.logPageView();
            this.attachEventListeners();
        } catch (error) {
            console.error('Statistics plugin initialization failed:', error);
        }
    }

    /**
     * Initialize user tracking with visitor cookie
     * @returns {string} User ID (UUID)
     */
    initializeUserTracking() {
        let uid = DokuCookie.getValue('plgstats');
        if (!uid) {
            uid = this.generateUUID();
            DokuCookie.setValue('plgstats', uid);
        }
        return uid;
    }


    /**
     * Build tracking data object
     */
    buildTrackingData() {
        const now = Date.now();
        this.data = {
            uid: this.uid,
            ses: this.sessionId,
            p: JSINFO.id,
            r: document.referrer,
            sx: screen.width,
            sy: screen.height,
            vx: window.innerWidth,
            vy: window.innerHeight,
            js: 1,
            rnd: now
        };
    }

    /**
     * Log page view based on action
     */
    async logPageView() {
        const action = JSINFO.act === 'show' ? 'v' : 's';
        await this.logView(action);
    }

    /**
     * Attach event listeners for tracking
     */
    attachEventListeners() {
        // Track external link clicks
        document.querySelectorAll('a.urlextern').forEach(link => {
            link.addEventListener('click', this.logExternal.bind(this));
        });

        // Track page unload
        window.addEventListener('beforeunload', this.logExit.bind(this));
    }

    /**
     * Log a view or session
     * @param {string} action 'v' = view, 's' = session
     */
    async logView(action) {
        const params = new URLSearchParams(this.data);
        const url = `${DOKU_BASE}lib/plugins/statistics/log.php?do=${action}&${params}`;

        try {
            // Use fetch with keepalive for better reliability
            await fetch(url, {
                method: 'GET',
                keepalive: true,
                cache: 'no-cache'
            });
        } catch (error) {
            // Fallback to image beacon for older browsers
            const img = new Image();
            img.src = url;
        }
    }

    /**
     * Log clicks to external URLs
     * @param {Event} event Click event
     */
    logExternal(event) {
        const params = new URLSearchParams(this.data);
        const url = `${DOKU_BASE}lib/plugins/statistics/log.php?do=o&ol=${encodeURIComponent(event.target.href)}&${params}`;

        // Use sendBeacon for reliable tracking
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url);
        } else {
            // Fallback for older browsers
            const img = new Image();
            img.src = url;
        }

        return true;
    }

    /**
     * Log page exit as session info
     */
    logExit() {
        const currentSession = this.getSession();
        if (currentSession !== this.sessionId) {
            return; // Session expired, don't log
        }

        const params = new URLSearchParams(this.data);
        const url = `${DOKU_BASE}lib/plugins/statistics/log.php?do=s&${params}`;

        if (navigator.sendBeacon) {
            navigator.sendBeacon(url);
        }
    }

    /**
     * Get current session identifier
     * Auto clears expired sessions and creates new ones after 15 min idle time
     * @returns {string} Session ID
     */
    getSession() {
        const now = Date.now();

        // Load session cookie
        let sessionData = DokuCookie.getValue('plgstatsses');
        let sessionId = '';

        if (sessionData) {
            const [timestamp, id] = sessionData.split('-', 2);
            if (now - parseInt(timestamp, 10) <= this.sessionTimeout) {
                sessionId = id;
            }
        }

        // Generate new session if needed
        if (!sessionId) {
            sessionId = this.generateUUID();
        }

        // Update session cookie
        DokuCookie.setValue('plgstatsses', `${now}-${sessionId}`);
        return sessionId;
    }

    /**
     * Generate a UUID v4
     * @returns {string} UUID
     */
    generateUUID() {
        function s4() {
            return Math.floor((1 + Math.random()) * 0x10000)
                .toString(16)
                .substring(1);
        }

        return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new StatisticsPlugin().init();
    });
} else {
    // DOM already loaded
    new StatisticsPlugin().init();
}
