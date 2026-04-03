/**
 * Base class for all controllers.
 * Provides polling lifecycle (startPolling / stopPolling) and
 * a refresh() hook for subclasses to implement.
 */
class BaseController {

    constructor() {
        /** @type {number|null} */
        this._pollInterval = null;
    }

    /**
     * Initializes the controller. Subclasses override to attach event listeners
     * and subscribe to AppEvents. Called once by app.js after instantiation.
     *
     * @returns {void}
     */
    init() {}

    /**
     * Starts polling by calling this.refresh() on a fixed interval.
     * Only call when the associated view is visible.
     * Clears any existing interval before starting a new one.
     *
     * @param {number} intervalMs - Polling interval in milliseconds.
     * @returns {void}
     */
    startPolling(intervalMs) {
        this.stopPolling();
        var self = this;
        this._pollInterval = setInterval(function () {
            self.refresh();
        }, intervalMs);
    }

    /**
     * Stops the polling interval if one is running.
     *
     * @returns {void}
     */
    stopPolling() {
        if (this._pollInterval !== null) {
            clearInterval(this._pollInterval);
            this._pollInterval = null;
        }
    }

    /**
     * Fetches fresh data and re-renders the view.
     * Must be overridden by subclasses.
     *
     * @async
     * @returns {Promise<void>}
     */
    async refresh() {
        throw new Error('BaseController.refresh() must be implemented by subclass.');
    }
}
