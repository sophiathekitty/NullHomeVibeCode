/**
 * Tests for ValidationView — DB validation overlay rendering.
 */

'use strict';

var BaseView = require('../../../public/js/views/base-view.js');
global.BaseView = BaseView;
var ValidationView = require('../../../public/js/views/validation-view.js');

function buildDom() {
    document.body.innerHTML = [
        '<template id="db-validate-result-row">',
        '  <div class="db-validate-result-row">',
        '    <span class="db-validate-result-model"></span>',
        '    <span class="db-validate-result-table"></span>',
        '    <span class="db-validate-result-status"></span>',
        '  </div>',
        '</template>',
        '<template id="db-validate-orphan-item">',
        '  <div><input type="checkbox" /><label></label></div>',
        '</template>',
        '<div id="dbValidateResults"></div>',
        '<div id="dbValidateOrphanList"></div>',
        '<div id="dbValidateOrphanSection"></div>',
        '<p id="dbValidateStatus"></p>',
        '<div id="dbValidateFeedback"></div>',
        '<div id="dbValidateOverlay" hidden></div>',
        '<button id="dbValidateRun"></button>',
        '<button id="dbValidateDelete"></button>',
        '<button id="dbValidateRefresh"></button>',
        '<button id="dbValidateClose"></button>'
    ].join('');
}

beforeEach(buildDom);

describe('ValidationView constructor', function () {
    test('extends BaseView, bound to db-validate-result-row template', function () {
        var view = new ValidationView();
        expect(view instanceof BaseView).toBe(true);
        expect(view.template).toBe(document.getElementById('db-validate-result-row'));
    });
});

describe('ValidationView.render', function () {
    test('renders one result row per entry', function () {
        var view = new ValidationView();
        view.render({
            results: [
                { model: 'Room', table: 'rooms', status: 'ok' },
                { model: 'Device', table: 'devices', status: 'missing_table' }
            ],
            orphan_tables: [],
            has_errors: false
        });
        var rows = document.querySelectorAll('.db-validate-result-row');
        expect(rows.length).toBe(2);
    });

    test('marks ok rows with "ok" class', function () {
        var view = new ValidationView();
        view.render({ results: [{ model: 'Room', table: 'rooms', status: 'ok' }], orphan_tables: [], has_errors: false });
        var status = document.querySelector('.db-validate-result-status');
        expect(status.classList.contains('ok')).toBe(true);
        expect(status.textContent).toBe('✓ ok');
    });

    test('marks error rows with "err" class', function () {
        var view = new ValidationView();
        view.render({ results: [{ model: 'Foo', table: 'foo', status: 'missing_table' }], orphan_tables: [], has_errors: true });
        var status = document.querySelector('.db-validate-result-status');
        expect(status.classList.contains('err')).toBe(true);
        expect(status.textContent).toBe('✗ missing_table');
    });

    test('shows orphan checkboxes when orphan_tables is non-empty', function () {
        var view = new ValidationView();
        view.render({ results: [], orphan_tables: ['stale_table'], has_errors: false });
        var cb = document.querySelector('input[type="checkbox"]');
        expect(cb).not.toBeNull();
        expect(cb.value).toBe('stale_table');
    });

    test('hides orphan section when there are no orphans', function () {
        var view = new ValidationView();
        view.render({ results: [], orphan_tables: [], has_errors: false });
        expect(document.getElementById('dbValidateOrphanSection').hidden).toBe(true);
    });

    test('sets clean status message when no errors and no orphans', function () {
        var view = new ValidationView();
        view.render({ results: [], orphan_tables: [], has_errors: false });
        expect(document.getElementById('dbValidateStatus').textContent).toBe('Validation complete — schema is clean.');
    });

    test('sets error status message when has_errors is true', function () {
        var view = new ValidationView();
        view.render({ results: [], orphan_tables: [], has_errors: true });
        expect(document.getElementById('dbValidateStatus').textContent).toBe('Validation complete — errors detected.');
    });
});

describe('ValidationView.renderDeletionResult', function () {
    test('shows deleted table names', function () {
        var view = new ValidationView();
        view.renderDeletionResult({ deleted: ['old_tbl'], skipped: [], errors: [] });
        expect(document.getElementById('dbValidateFeedback').textContent).toContain('Deleted: old_tbl');
    });

    test('shows skipped table names', function () {
        var view = new ValidationView();
        view.renderDeletionResult({ deleted: [], skipped: ['protected_tbl'], errors: [] });
        expect(document.getElementById('dbValidateFeedback').textContent).toContain('Skipped (protected): protected_tbl');
    });

    test('shows error details', function () {
        var view = new ValidationView();
        view.renderDeletionResult({ deleted: [], skipped: [], errors: [{ table: 'bad_tbl', error: 'permission denied' }] });
        expect(document.getElementById('dbValidateFeedback').textContent).toContain('Error on bad_tbl: permission denied');
    });

    test('shows "Done." when all arrays are empty', function () {
        var view = new ValidationView();
        view.renderDeletionResult({ deleted: [], skipped: [], errors: [] });
        expect(document.getElementById('dbValidateFeedback').textContent).toContain('Done.');
    });
});

describe('ValidationView state helpers', function () {
    test('setStatus updates the status element text', function () {
        var view = new ValidationView();
        view.setStatus('Working…');
        expect(document.getElementById('dbValidateStatus').textContent).toBe('Working…');
    });

    test('showOverlay / hideOverlay toggle the hidden attribute', function () {
        var view = new ValidationView();
        view.showOverlay();
        expect(document.getElementById('dbValidateOverlay').hidden).toBe(false);
        view.hideOverlay();
        expect(document.getElementById('dbValidateOverlay').hidden).toBe(true);
    });

    test('setRunning disables and re-enables the run button', function () {
        var view = new ValidationView();
        view.setRunning(true);
        expect(document.getElementById('dbValidateRun').disabled).toBe(true);
        view.setRunning(false);
        expect(document.getElementById('dbValidateRun').disabled).toBe(false);
    });

    test('setDeleting disables and re-enables the delete button', function () {
        var view = new ValidationView();
        view.setDeleting(true);
        expect(document.getElementById('dbValidateDelete').disabled).toBe(true);
        view.setDeleting(false);
        expect(document.getElementById('dbValidateDelete').disabled).toBe(false);
    });

    test('getCheckedTables returns values of checked checkboxes', function () {
        var view = new ValidationView();
        view.render({ results: [], orphan_tables: ['tbl_a', 'tbl_b'], has_errors: false });
        var checkboxes = document.querySelectorAll('#dbValidateOrphanList input[type="checkbox"]');
        checkboxes[0].checked = true;
        checkboxes[1].checked = false;
        expect(view.getCheckedTables()).toEqual(['tbl_a']);
    });

    test('reset restores initial state', function () {
        var view = new ValidationView();
        document.getElementById('dbValidateStatus').textContent = 'Changed';
        document.getElementById('dbValidateOrphanSection').hidden = false;
        view.reset();
        expect(document.getElementById('dbValidateStatus').textContent).toBe('Ready');
        expect(document.getElementById('dbValidateOrphanSection').hidden).toBe(true);
        expect(document.getElementById('dbValidateRefresh').hidden).toBe(true);
    });
});
