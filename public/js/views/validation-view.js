/**
 * View for the validation overlay.
 * Clones the #db-validate-result-row and #db-validate-orphan-item templates and
 * renders them into their respective containers inside the validation overlay.
 * Also manages the visibility and state of all overlay elements.
 * @extends BaseView
 */
class ValidationView extends BaseView {

    /**
     * Creates a ValidationView bound to the db-validate-result-row template
     * and dbValidateResults container, and stores references to all other
     * overlay elements.
     */
    constructor() {
        super('db-validate-result-row', 'dbValidateResults');
        this._orphanTemplate  = document.getElementById('db-validate-orphan-item');
        this._orphanContainer = document.getElementById('dbValidateOrphanList');
        this._orphanSection   = document.getElementById('dbValidateOrphanSection');
        this._statusEl        = document.getElementById('dbValidateStatus');
        this._feedbackEl      = document.getElementById('dbValidateFeedback');
        this._overlay         = document.getElementById('dbValidateOverlay');
        this._btnRun          = document.getElementById('dbValidateRun');
        this._btnDelete       = document.getElementById('dbValidateDelete');
        this._btnRefresh      = document.getElementById('dbValidateRefresh');
    }

    /**
     * Renders the full validation result: per-model status rows and orphan
     * table checkboxes (all checked by default). Updates the status line and
     * shows or hides the delete and refresh buttons accordingly.
     *
     * @param {{results: Array<{model: string, table: string, status: string}>,
     *          orphan_tables: Array<string>,
     *          has_errors: boolean}} data - Unwrapped data from ValidationModel.run().
     * @returns {void}
     */
    render(data) {
        var self    = this;
        var orphans = data.orphan_tables || [];

        // ── Result rows ──────────────────────────────────────────────────────
        this.clear();
        (data.results || []).forEach(function (row) {
            var node      = self.cloneTemplate();
            var isOk      = row.status === 'ok';
            var statusEl  = node.querySelector('.db-validate-result-status');

            node.querySelector('.db-validate-result-model').textContent = row.model;
            node.querySelector('.db-validate-result-table').textContent = row.table;
            statusEl.textContent = isOk ? '✓ ok' : '✗ ' + row.status;
            statusEl.classList.add(isOk ? 'ok' : 'err');

            self.container.appendChild(node);
        });
        this.container.hidden = false;

        // ── Orphan table checkboxes ──────────────────────────────────────────
        this._orphanContainer.innerHTML = '';
        if (orphans.length > 0) {
            orphans.forEach(function (table) {
                var node = self._orphanTemplate.content.cloneNode(true);
                var cb   = node.querySelector('input[type="checkbox"]');
                var lbl  = node.querySelector('label');
                cb.id        = 'orphan-cb-' + table;
                cb.value     = table;
                lbl.htmlFor = 'orphan-cb-' + table;
                lbl.textContent = table;
                self._orphanContainer.appendChild(node);
            });
            this._orphanSection.hidden = false;
            this._btnDelete.hidden     = false;
        } else {
            this._orphanSection.hidden = true;
            this._btnDelete.hidden     = true;
        }

        // ── Status line ──────────────────────────────────────────────────────
        if (data.has_errors) {
            this._statusEl.textContent = 'Validation complete — errors detected.';
        } else if (orphans.length > 0) {
            this._statusEl.textContent = 'Validation complete — orphan tables found.';
        } else {
            this._statusEl.textContent = 'Validation complete — schema is clean.';
        }

        this._btnRefresh.hidden = false;
    }

    /**
     * Renders the result of a table deletion into the feedback area and hides
     * the orphan section and delete button.
     *
     * @param {{deleted: Array<string>, skipped: Array<string>, errors: Array<{table: string, error: string}>}} data
     *   Unwrapped data from ValidationModel.deleteTables().
     * @returns {void}
     */
    renderDeletionResult(data) {
        var self     = this;
        var deleted  = data.deleted || [];
        var skipped  = data.skipped || [];
        var errors   = data.errors  || [];
        var hasContent = false;

        this._feedbackEl.innerHTML = '';

        if (deleted.length > 0) {
            hasContent = true;
            var delSpan = document.createElement('span');
            delSpan.className   = 'deleted';
            delSpan.textContent = 'Deleted: ' + deleted.join(', ');
            self._feedbackEl.appendChild(delSpan);
            self._feedbackEl.appendChild(document.createElement('br'));
        }

        if (skipped.length > 0) {
            hasContent = true;
            var skipSpan = document.createElement('span');
            skipSpan.textContent = 'Skipped (protected): ' + skipped.join(', ');
            self._feedbackEl.appendChild(skipSpan);
            self._feedbackEl.appendChild(document.createElement('br'));
        }

        errors.forEach(function (e) {
            hasContent = true;
            var errSpan = document.createElement('span');
            errSpan.className   = 'err';
            errSpan.textContent = 'Error on ' + e.table + ': ' + e.error;
            self._feedbackEl.appendChild(errSpan);
            self._feedbackEl.appendChild(document.createElement('br'));
        });

        if (!hasContent) {
            var doneSpan = document.createElement('span');
            doneSpan.textContent = 'Done.';
            self._feedbackEl.appendChild(doneSpan);
        }

        this._feedbackEl.hidden    = false;
        this._orphanSection.hidden = true;
        this._btnDelete.hidden     = true;
    }

    /**
     * Updates the status line text.
     *
     * @param {string} text - Status message to display.
     * @returns {void}
     */
    setStatus(text) {
        this._statusEl.textContent = text;
    }

    /**
     * Shows the overlay.
     *
     * @returns {void}
     */
    showOverlay() {
        this._overlay.hidden = false;
    }

    /**
     * Hides the overlay.
     *
     * @returns {void}
     */
    hideOverlay() {
        this._overlay.hidden = true;
    }

    /**
     * Shows the Refresh button.
     *
     * @returns {void}
     */
    showRefreshButton() {
        this._btnRefresh.hidden = false;
    }

    /**
     * Returns the table names of all currently checked orphan checkboxes.
     *
     * @returns {Array<string>} Checked orphan table names.
     */
    getCheckedTables() {
        var checkboxes = this._orphanContainer.querySelectorAll('input[type="checkbox"]:checked');
        var tables = [];
        checkboxes.forEach(function (cb) {
            tables.push(cb.value);
        });
        return tables;
    }

    /**
     * Enables or disables the Run Validation button to reflect a running state.
     *
     * @param {boolean} isRunning - True to disable the button, false to re-enable it.
     * @returns {void}
     */
    setRunning(isRunning) {
        this._btnRun.disabled = isRunning;
    }

    /**
     * Enables or disables the Delete Selected button to reflect a deleting state.
     *
     * @param {boolean} isDeleting - True to disable the button, false to re-enable it.
     * @returns {void}
     */
    setDeleting(isDeleting) {
        this._btnDelete.disabled = isDeleting;
    }

    /**
     * Resets the overlay to the initial ready state, clearing all rendered
     * content and restoring default button visibility.
     *
     * @returns {void}
     */
    reset() {
        this._statusEl.textContent   = 'Ready';
        this.clear();
        this.container.hidden        = true;
        this._orphanContainer.innerHTML = '';
        this._orphanSection.hidden   = true;
        this._feedbackEl.innerHTML   = '';
        this._feedbackEl.hidden      = true;
        this._btnRun.hidden          = false;
        this._btnRun.disabled        = false;
        this._btnDelete.hidden       = true;
        this._btnDelete.disabled     = false;
        this._btnRefresh.hidden      = true;
    }
}

if (typeof module !== 'undefined') { module.exports = ValidationView; }
