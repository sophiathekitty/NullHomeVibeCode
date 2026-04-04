/**
 * Controller for the validation overlay.
 * Wires ValidationModel to ValidationView, attaches overlay button listeners,
 * and drives the validate → report → delete → refresh flow.
 * @extends BaseController
 */
class ValidationController extends BaseController {

    /**
     * Creates a ValidationController with its own ValidationModel and ValidationView.
     */
    constructor() {
        super();
        this.model = new ValidationModel();
        this.view  = new ValidationView();
    }

    /**
     * Initializes the controller.
     *
     * Binds the "Validate DB" drawer button to open the overlay and wires
     * the Run, Delete Selected, Refresh, and Close buttons inside the overlay.
     *
     * @returns {void}
     */
    init() {
        var self     = this;
        var openBtn  = document.getElementById('openDbValidate');
        var runBtn   = document.getElementById('dbValidateRun');
        var deleteBtn   = document.getElementById('dbValidateDelete');
        var refreshBtn  = document.getElementById('dbValidateRefresh');
        var closeBtn    = document.getElementById('dbValidateClose');

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                var drawer        = document.getElementById('drawer');
                var drawerOverlay = document.getElementById('drawerOverlay');
                if (drawer)        { drawer.classList.remove('is-open'); }
                if (drawerOverlay) { drawerOverlay.classList.remove('is-visible'); }
                self.view.reset();
                self.view.showOverlay();
            });
        }

        runBtn.addEventListener('click', function () {
            self.refresh();
        });

        deleteBtn.addEventListener('click', function () {
            self._handleDelete();
        });

        refreshBtn.addEventListener('click', function () {
            location.reload();
        });

        closeBtn.addEventListener('click', function () {
            self.view.hideOverlay();
        });
    }

    /**
     * Runs the DB validation and renders the result report.
     * Called when the user clicks "Run Validation".
     *
     * @async
     * @returns {Promise<void>}
     */
    async refresh() {
        var self = this;
        self.view.setStatus('Validating…');
        self.view.setRunning(true);

        try {
            var data = await self.model.run();
            self.view.render(data);
        } catch (err) {
            self.view.setStatus('Error: ' + err.message);
        } finally {
            self.view.setRunning(false);
            self.view.showRefreshButton();
        }
    }

    /**
     * Handles the Delete Selected flow: confirms with the user, calls the
     * model to drop the selected orphan tables, then renders the result.
     *
     * @async
     * @returns {Promise<void>}
     */
    async _handleDelete() {
        var self   = this;
        var tables = self.view.getCheckedTables();

        if (tables.length === 0) {
            self.view.setStatus('No tables selected.');
            return;
        }

        var confirmMsg = 'Delete the following tables? This cannot be undone.\n\n'
            + tables.join('\n')
            + '\n\nThese tables have no corresponding model.';

        if (!confirm(confirmMsg)) {
            return;
        }

        self.view.setDeleting(true);
        self.view.setStatus('Deleting tables…');

        try {
            var data = await self.model.deleteTables(tables);
            self.view.renderDeletionResult(data);
            self.view.setStatus('Deletion complete.');
        } catch (err) {
            self.view.setStatus('Error: ' + err.message);
        } finally {
            self.view.setDeleting(false);
        }
    }
}
