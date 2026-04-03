/**
 * Base class for all API resource models.
 * Handles fetch, JSON parsing, and envelope unwrapping.
 * Subclasses define the base API path and resource-specific methods.
 */
class BaseModel {

    /**
     * @param {string} basePath - The base API path, e.g. '/api/rooms'.
     */
    constructor(basePath) {
        this._basePath = basePath;
    }

    /**
     * Performs a GET request.
     *
     * @async
     * @param {string} [path=''] - Path appended to basePath.
     * @returns {Promise<Object|Array>} Unwrapped response.data value.
     * @throws {Error} If response.success is false, throws with response.error as message.
     */
    async get(path = '') {
        return this.#request(this._basePath + path, { method: 'GET' });
    }

    /**
     * Performs a POST request with a JSON body.
     *
     * @async
     * @param {string} path - Path appended to basePath.
     * @param {Object} body - Request payload, serialized as JSON.
     * @returns {Promise<Object|Array>} Unwrapped response.data value.
     * @throws {Error} If response.success is false, throws with response.error as message.
     */
    async post(path, body) {
        return this.#request(this._basePath + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    /**
     * Performs a PUT request with a JSON body.
     *
     * @async
     * @param {string} path - Path appended to basePath.
     * @param {Object} body - Request payload, serialized as JSON.
     * @returns {Promise<Object|Array>} Unwrapped response.data value.
     * @throws {Error} If response.success is false, throws with response.error as message.
     */
    async put(path, body) {
        return this.#request(this._basePath + path, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    /**
     * Performs a DELETE request.
     *
     * @async
     * @param {string} path - Path appended to basePath.
     * @returns {Promise<Object|Array>} Unwrapped response.data value.
     * @throws {Error} If response.success is false, throws with response.error as message.
     */
    async delete(path) {
        return this.#request(this._basePath + path, { method: 'DELETE' });
    }

    /**
     * Internal fetch helper. Parses JSON, checks the standard envelope,
     * and returns response.data. Throws on network error or response.success === false.
     *
     * @async
     * @param {string} url - Full URL to fetch.
     * @param {RequestInit} options - Fetch options.
     * @returns {Promise<Object|Array>} Unwrapped response.data.
     * @throws {Error} On network failure or API error.
     */
    async #request(url, options) {
        var response = await fetch(url, options);
        var json = await response.json();
        if (!json.success) {
            throw new Error(json.error || 'API request failed.');
        }
        return json.data;
    }
}
