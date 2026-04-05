/**
 * Model for the validation API resource.
 * Handles all fetch calls to /api/validation and unwraps the response envelope.
 * @extends BaseModel
 */
class ValidationModel extends BaseModel {

    /**
     * Creates a ValidationModel bound to the /api/validation endpoint.
     */
    constructor() {
        super('/api/validation');
    }

    /**
     * Runs DB schema validation against all registered models and returns a
     * combined report including orphan-table detection.
     *
     * @async
     * @returns {Promise<{results: Array<Object>, orphan_tables: Array<string>, has_errors: boolean}>}
     *   Unwrapped validation result data.
     */
    async run() {
        return this.post('/run', {});
    }

    /**
     * Deletes the specified orphan tables (server-side confirmation included).
     *
     * @async
     * @param {Array<string>} tables - Table names to drop.
     * @returns {Promise<{deleted: Array<string>, skipped: Array<string>, errors: Array<Object>}>}
     *   Unwrapped deletion result data.
     */
    async deleteTables(tables) {
        return this.post('/delete-tables', { tables: tables, confirm: true });
    }
}

if (typeof module !== 'undefined') { module.exports = ValidationModel; }
