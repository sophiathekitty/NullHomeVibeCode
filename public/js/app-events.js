/**
 * Static named-event dispatcher for cross-controller communication.
 * Controllers emit and subscribe to named events instead of holding
 * references to each other.
 */
class AppEvents {
    /** @type {Object<string, Function[]>} */
    static #listeners = {};

    /**
     * Subscribes a callback to a named event.
     *
     * @param {string} eventName - The event name (e.g. 'room:selected').
     * @param {Function} callback - Called with the event payload when the event fires.
     * @returns {void}
     */
    static on(eventName, callback) {
        if (!AppEvents.#listeners[eventName]) {
            AppEvents.#listeners[eventName] = [];
        }
        AppEvents.#listeners[eventName].push(callback);
    }

    /**
     * Unsubscribes a callback from a named event.
     *
     * @param {string} eventName - The event name.
     * @param {Function} callback - The same function reference passed to on().
     * @returns {void}
     */
    static off(eventName, callback) {
        if (!AppEvents.#listeners[eventName]) {
            return;
        }
        AppEvents.#listeners[eventName] = AppEvents.#listeners[eventName].filter(function (fn) {
            return fn !== callback;
        });
    }

    /**
     * Emits a named event, calling all subscribed callbacks with the payload.
     * Also emits to '*' listeners if any exist (used for debug logging only).
     *
     * @param {string} eventName - The event name.
     * @param {Object} payload - Data passed to all subscribers.
     * @returns {void}
     */
    static emit(eventName, payload = {}) {
        var listeners = AppEvents.#listeners[eventName] || [];
        listeners.slice().forEach(function (fn) {
            fn(payload);
        });
        var wildcardListeners = AppEvents.#listeners['*'] || [];
        wildcardListeners.slice().forEach(function (fn) {
            fn(eventName, payload);
        });
    }
}

if (typeof module !== 'undefined') { module.exports = AppEvents; }
