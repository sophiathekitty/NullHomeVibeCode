/**
 * NullHome — DB Validation UI.
 *
 * Drives the database-validation overlay:
 *   1. POST /api/validation/run          — validate schema; detect orphan tables.
 *   2. POST /api/validation/delete-tables — drop selected orphan tables (confirmed).
 *
 * The overlay shows:
 *   - A status line.
 *   - A per-model results report (model name, table, ok/error).
 *   - If orphan tables are found: a checkbox list (all checked by default).
 *   - A Delete Selected button requiring explicit confirmation.
 *   - A Refresh button to reload the page after changes.
 */

/**
 * Initialise the DB validation overlay.
 *
 * Binds the "Validate DB" drawer button to open the overlay, wires up
 * the Run, Delete Selected, and Close/Refresh buttons inside the overlay.
 *
 * @returns {void}
 */
function initDbValidate() {
    var overlay       = document.getElementById('dbValidateOverlay');
    var statusEl      = document.getElementById('dbValidateStatus');
    var resultsWrap   = document.getElementById('dbValidateResults');
    var orphanSection = document.getElementById('dbValidateOrphanSection');
    var orphanList    = document.getElementById('dbValidateOrphanList');
    var feedbackEl    = document.getElementById('dbValidateFeedback');
    var btnRun        = document.getElementById('dbValidateRun');
    var btnDelete     = document.getElementById('dbValidateDelete');
    var btnRefresh    = document.getElementById('dbValidateRefresh');
    var btnClose      = document.getElementById('dbValidateClose');
    var openBtn       = document.getElementById('openDbValidate');

    /**
     * Open the overlay and reset it to the idle state.
     *
     * @returns {void}
     */
    function openOverlay() {
        statusEl.textContent      = 'Ready';
        resultsWrap.hidden        = true;
        resultsWrap.innerHTML     = '';
        orphanSection.hidden      = true;
        orphanList.innerHTML      = '';
        feedbackEl.hidden         = true;
        feedbackEl.innerHTML      = '';
        btnRun.hidden             = false;
        btnDelete.hidden          = true;
        btnRefresh.hidden         = true;
        overlay.hidden            = false;
    }

    /**
     * Close the overlay.
     *
     * @returns {void}
     */
    function closeOverlay() {
        overlay.hidden = true;
    }

    /**
     * Render the validation results report into the results container.
     *
     * @param {Array<{model: string, table: string, status: string}>} results
     * @returns {void}
     */
    function renderResults(results) {
        resultsWrap.innerHTML = '';
        results.forEach(function (row) {
            var div = document.createElement('div');
            div.className = 'db-validate-result-row';

            var modelSpan = document.createElement('span');
            modelSpan.className = 'db-validate-result-model';
            modelSpan.textContent = row.model;

            var tableSpan = document.createElement('span');
            tableSpan.className = 'db-validate-result-table';
            tableSpan.textContent = row.table;

            var statusSpan = document.createElement('span');
            var isOk = row.status === 'ok';
            statusSpan.className = 'db-validate-result-status ' + (isOk ? 'ok' : 'err');
            statusSpan.textContent = isOk ? '✓ ok' : '✗ ' + row.status;

            div.appendChild(modelSpan);
            div.appendChild(tableSpan);
            div.appendChild(statusSpan);
            resultsWrap.appendChild(div);
        });
        resultsWrap.hidden = false;
    }

    /**
     * Render the orphan tables section with checkboxes (all checked by default).
     *
     * @param {Array<string>} orphans - Table names with no model.
     * @returns {void}
     */
    function renderOrphans(orphans) {
        if (orphans.length === 0) {
            orphanSection.hidden = true;
            return;
        }

        orphanList.innerHTML = '';
        orphans.forEach(function (table) {
            var li = document.createElement('li');
            li.className = 'db-validate-orphan-item';

            var cb = document.createElement('input');
            cb.type    = 'checkbox';
            cb.checked = true;
            cb.id      = 'orphan-cb-' + table;
            cb.value   = table;

            var lbl = document.createElement('label');
            lbl.htmlFor     = cb.id;
            lbl.textContent = table;

            li.appendChild(cb);
            li.appendChild(lbl);
            orphanList.appendChild(li);
        });

        orphanSection.hidden = false;
        btnDelete.hidden     = false;
    }

    /**
     * Collect the currently checked orphan table names.
     *
     * @returns {Array<string>}
     */
    function getCheckedTables() {
        var checkboxes = orphanList.querySelectorAll('input[type="checkbox"]:checked');
        var tables = [];
        checkboxes.forEach(function (cb) {
            tables.push(cb.value);
        });
        return tables;
    }

    /**
     * Run the validation by calling POST /api/validation/run.
     *
     * @async
     * @returns {Promise<void>}
     */
    async function runValidation() {
        btnRun.disabled = true;
        statusEl.textContent = 'Validating…';
        resultsWrap.hidden   = true;
        resultsWrap.innerHTML = '';
        orphanSection.hidden = true;
        orphanList.innerHTML = '';
        feedbackEl.hidden    = true;
        feedbackEl.innerHTML = '';
        btnDelete.hidden     = true;
        btnRefresh.hidden    = true;

        try {
            var resp     = await fetch('/api/validation/run', { method: 'POST' });
            var envelope = await resp.json();

            if (!resp.ok || envelope.error) {
                throw new Error(envelope.error || 'Server error: ' + resp.status);
            }

            var data = envelope.data || {};

            renderResults(data.results || []);
            renderOrphans(data.orphan_tables || []);

            if (data.has_errors) {
                statusEl.textContent = 'Validation complete — errors detected.';
            } else if ((data.orphan_tables || []).length > 0) {
                statusEl.textContent = 'Validation complete — orphan tables found.';
            } else {
                statusEl.textContent = 'Validation complete — schema is clean.';
            }

        } catch (err) {
            statusEl.textContent = 'Error: ' + err.message;
        } finally {
            btnRun.disabled  = false;
            btnRefresh.hidden = false;
        }
    }

    /**
     * Delete the selected orphan tables after explicit user confirmation.
     *
     * @async
     * @returns {Promise<void>}
     */
    async function deleteSelected() {
        var tables = getCheckedTables();
        if (tables.length === 0) {
            feedbackEl.innerHTML = '<span>No tables selected.</span>';
            feedbackEl.hidden    = false;
            return;
        }

        var confirmMsg = 'Delete the following tables? This cannot be undone.\n\n'
            + tables.join('\n')
            + '\n\nThese tables have no corresponding model.';

        if (!confirm(confirmMsg)) {
            return;
        }

        btnDelete.disabled = true;
        statusEl.textContent = 'Deleting tables…';

        try {
            var resp = await fetch('/api/validation/delete-tables', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tables: tables, confirm: true }),
            });
            var envelope = await resp.json();

            if (!resp.ok || envelope.error) {
                throw new Error(envelope.error || 'Server error: ' + resp.status);
            }

            var data     = envelope.data || {};
            var deleted  = data.deleted  || [];
            var skipped  = data.skipped  || [];
            var errs     = data.errors   || [];

            var html = '';
            if (deleted.length > 0) {
                html += '<span class="deleted">Deleted: ' + deleted.join(', ') + '</span><br>';
            }
            if (skipped.length > 0) {
                html += '<span>Skipped (protected): ' + skipped.join(', ') + '</span><br>';
            }
            errs.forEach(function (e) {
                html += '<span class="err">Error on ' + e.table + ': ' + e.error + '</span><br>';
            });

            feedbackEl.innerHTML = html || '<span>Done.</span>';
            feedbackEl.hidden    = false;

            statusEl.textContent = 'Deletion complete.';
            btnDelete.hidden     = true;
            orphanSection.hidden = true;

        } catch (err) {
            statusEl.textContent = 'Error: ' + err.message;
        } finally {
            btnDelete.disabled = false;
        }
    }

    // ── Event bindings ─────────────────────────────────────────────────────────

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            var drawer        = document.getElementById('drawer');
            var drawerOverlay = document.getElementById('drawerOverlay');
            if (drawer)        { drawer.classList.remove('is-open'); }
            if (drawerOverlay) { drawerOverlay.classList.remove('is-visible'); }

            openOverlay();
        });
    }

    btnRun.addEventListener('click', runValidation);
    btnDelete.addEventListener('click', deleteSelected);
    btnRefresh.addEventListener('click', function () { location.reload(); });
    btnClose.addEventListener('click', closeOverlay);
}
