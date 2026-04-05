/**
 * Tests for ValidationController — overlay flow, run, and delete actions.
 */

'use strict';

// Set up globals before requiring any modules that depend on them
var BaseController = require('../../../public/js/controllers/base-controller.js');
global.BaseController = BaseController;
global.AppEvents = { on: jest.fn(), off: jest.fn(), emit: jest.fn() };

// Default stubs — overridden per-test in beforeEach
global.ValidationModel = function () {
    this.run = jest.fn().mockResolvedValue({ results: [], orphan_tables: [], has_errors: false });
    this.deleteTables = jest.fn().mockResolvedValue({ deleted: [], skipped: [], errors: [] });
};
global.ValidationView = function () {
    this.reset = jest.fn();
    this.showOverlay = jest.fn();
    this.hideOverlay = jest.fn();
    this.setStatus = jest.fn();
    this.setRunning = jest.fn();
    this.setDeleting = jest.fn();
    this.showRefreshButton = jest.fn();
    this.render = jest.fn();
    this.renderDeletionResult = jest.fn();
    this.getCheckedTables = jest.fn().mockReturnValue([]);
    this.clear = jest.fn();
};

// Require controller once — it will use whatever global.ValidationModel/View are set to at
// construction time (inside each test's new ValidationController() call).
var ValidationController = require('../../../public/js/controllers/validation-controller.js');

function buildDom() {
    document.body.innerHTML = [
        '<button id="openDbValidate"></button>',
        '<button id="dbValidateRun"></button>',
        '<button id="dbValidateDelete"></button>',
        '<button id="dbValidateRefresh"></button>',
        '<button id="dbValidateClose"></button>',
        '<div id="drawer" class="is-open"></div>',
        '<div id="drawerOverlay" class="is-visible"></div>',
        '<div id="dbValidateOverlay"></div>'
    ].join('');
}

beforeEach(function () {
    buildDom();
    jest.clearAllMocks();

    // Reset stubs to clean defaults for each test
    global.ValidationModel = function () {
        this.run = jest.fn().mockResolvedValue({ results: [], orphan_tables: [], has_errors: false });
        this.deleteTables = jest.fn().mockResolvedValue({ deleted: [], skipped: [], errors: [] });
    };
    global.ValidationView = function () {
        this.reset = jest.fn();
        this.showOverlay = jest.fn();
        this.hideOverlay = jest.fn();
        this.setStatus = jest.fn();
        this.setRunning = jest.fn();
        this.setDeleting = jest.fn();
        this.showRefreshButton = jest.fn();
        this.render = jest.fn();
        this.renderDeletionResult = jest.fn();
        this.getCheckedTables = jest.fn().mockReturnValue([]);
        this.clear = jest.fn();
    };
});

describe('ValidationController constructor', function () {
    test('extends BaseController', function () {
        var ctrl = new ValidationController();
        expect(ctrl instanceof BaseController).toBe(true);
    });
});

describe('ValidationController.init', function () {
    test('openDbValidate button shows overlay and resets view', function () {
        var ctrl = new ValidationController();
        ctrl.init();
        document.getElementById('openDbValidate').click();
        expect(ctrl.view.reset).toHaveBeenCalled();
        expect(ctrl.view.showOverlay).toHaveBeenCalled();
    });

    test('dbValidateRun button calls refresh()', async function () {
        var ctrl = new ValidationController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.init();
        document.getElementById('dbValidateRun').click();
        expect(ctrl.refresh).toHaveBeenCalled();
    });

    test('dbValidateClose button hides overlay', function () {
        var ctrl = new ValidationController();
        ctrl.init();
        document.getElementById('dbValidateClose').click();
        expect(ctrl.view.hideOverlay).toHaveBeenCalled();
    });
});

describe('ValidationController.refresh', function () {
    test('calls model.run and renders the result', async function () {
        var report = { results: [{ model: 'Room', table: 'rooms', status: 'ok' }], orphan_tables: [], has_errors: false };
        global.ValidationModel = function () {
            this.run = jest.fn().mockResolvedValue(report);
            this.deleteTables = jest.fn();
        };
        var ctrl = new ValidationController();
        await ctrl.refresh();
        expect(ctrl.view.render).toHaveBeenCalledWith(report);
    });

    test('sets status to error message when model.run rejects', async function () {
        global.ValidationModel = function () {
            this.run = jest.fn().mockRejectedValue(new Error('DB gone'));
            this.deleteTables = jest.fn();
        };
        var ctrl = new ValidationController();
        await ctrl.refresh();
        expect(ctrl.view.setStatus).toHaveBeenCalledWith('Error: DB gone');
    });

    test('always re-enables run button after completion', async function () {
        var ctrl = new ValidationController();
        await ctrl.refresh();
        expect(ctrl.view.setRunning).toHaveBeenLastCalledWith(false);
    });
});

describe('ValidationController._handleDelete', function () {
    test('sets "No tables selected." status when no tables are checked', async function () {
        global.ValidationView = function () {
            this.reset = jest.fn();
            this.showOverlay = jest.fn();
            this.hideOverlay = jest.fn();
            this.setStatus = jest.fn();
            this.setRunning = jest.fn();
            this.setDeleting = jest.fn();
            this.showRefreshButton = jest.fn();
            this.render = jest.fn();
            this.renderDeletionResult = jest.fn();
            this.getCheckedTables = jest.fn().mockReturnValue([]);
            this.clear = jest.fn();
        };
        var ctrl = new ValidationController();
        await ctrl._handleDelete();
        expect(ctrl.view.setStatus).toHaveBeenCalledWith('No tables selected.');
    });

    test('calls model.deleteTables with checked tables and renders result on confirm', async function () {
        var deleteResult = { deleted: ['old_tbl'], skipped: [], errors: [] };
        global.ValidationModel = function () {
            this.run = jest.fn();
            this.deleteTables = jest.fn().mockResolvedValue(deleteResult);
        };
        global.ValidationView = function () {
            this.reset = jest.fn();
            this.showOverlay = jest.fn();
            this.hideOverlay = jest.fn();
            this.setStatus = jest.fn();
            this.setRunning = jest.fn();
            this.setDeleting = jest.fn();
            this.showRefreshButton = jest.fn();
            this.render = jest.fn();
            this.renderDeletionResult = jest.fn();
            this.getCheckedTables = jest.fn().mockReturnValue(['old_tbl']);
            this.clear = jest.fn();
        };
        global.confirm = jest.fn().mockReturnValue(true);
        var ctrl = new ValidationController();
        await ctrl._handleDelete();
        expect(ctrl.model.deleteTables).toHaveBeenCalledWith(['old_tbl']);
        expect(ctrl.view.renderDeletionResult).toHaveBeenCalledWith(deleteResult);
    });

    test('does nothing when user cancels the confirm dialog', async function () {
        global.ValidationModel = function () {
            this.run = jest.fn();
            this.deleteTables = jest.fn();
        };
        global.ValidationView = function () {
            this.reset = jest.fn();
            this.showOverlay = jest.fn();
            this.hideOverlay = jest.fn();
            this.setStatus = jest.fn();
            this.setRunning = jest.fn();
            this.setDeleting = jest.fn();
            this.showRefreshButton = jest.fn();
            this.render = jest.fn();
            this.renderDeletionResult = jest.fn();
            this.getCheckedTables = jest.fn().mockReturnValue(['tbl']);
            this.clear = jest.fn();
        };
        global.confirm = jest.fn().mockReturnValue(false);
        var ctrl = new ValidationController();
        await ctrl._handleDelete();
        expect(ctrl.model.deleteTables).not.toHaveBeenCalled();
    });
});
