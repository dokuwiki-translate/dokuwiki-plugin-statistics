/* DOKUWIKI:include_once lib/chart.js */
/* DOKUWIKI:include_once lib/chartjs-plugin-datalabels.js */

/* globals JSINFO, DOKU_BASE, DokuCookie */

/**
 * Modern Statistics Plugin
 */
class StatisticsPlugin {
    constructor() {
        this.data = {};
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
     * Build tracking data object
     */
    buildTrackingData() {
        const now = Date.now();
        this.data = {
            p: JSINFO.id,
            r: document.referrer,
            sx: screen.width,
            sy: screen.height,
            vx: window.innerWidth,
            vy: window.innerHeight,
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
        const params = new URLSearchParams(this.data);
        const url = `${DOKU_BASE}lib/plugins/statistics/log.php?do=s&${params}`;

        if (navigator.sendBeacon) {
            navigator.sendBeacon(url);
        }
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

class ChartComponent extends HTMLElement {
    connectedCallback() {
        this.renderChart();
    }

    renderChart() {
        const chartType = this.getAttribute('type');
        const data = JSON.parse(this.getAttribute('data'));

        console.log('data', data);

        const canvas = document.createElement("canvas");
        canvas.height = this.getAttribute('height') || 300;
        canvas.width = this.getAttribute('width') || 300;

        this.appendChild(canvas);

        const ctx = canvas.getContext('2d');

        // basic config
        const config = {
            type: chartType,
            data: data,
            options: {
                responsive: false,
            },
        };

        // percentage labels and tooltips for pie charts
        if (chartType === "pie") {
            // chartjs-plugin-datalabels needs to be registered
            Chart.register(ChartDataLabels);

            config.options.plugins = {
                datalabels: {
                    formatter: (value, context) => {
                        const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        return ((value / total) * 100).toFixed(2) + '%'; // percentage
                    },
                    color: '#fff',
                }
            };
        }

        new Chart(ctx, config);
    }
}

customElements.define('chart-component', ChartComponent);
