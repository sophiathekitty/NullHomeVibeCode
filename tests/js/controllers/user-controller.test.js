/**
 * Tests for UserController — identity switcher wiring and AppEvents integration.
 */

'use strict';

var BaseController = require('../../../public/js/controllers/base-controller.js');
global.BaseController = BaseController;

global.AppEvents = { on: jest.fn(), off: jest.fn(), emit: jest.fn() };

// Default stubs for UserModel and UserView
global.UserModel = function () {
    this.getAll = jest.fn().mockResolvedValue([]);
    this.create = jest.fn().mockResolvedValue({ id: 5, name: 'New', role: 'resident' });
    this.delete = jest.fn().mockResolvedValue(null);
    this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
    this.login  = jest.fn().mockResolvedValue({ id: 3, name: 'Sophia', role: 'admin', color: '#d93849' });
    this.logout = jest.fn().mockResolvedValue(null);
};
global.UserView = function () {
    this.render            = jest.fn();
    this.renderCurrentUser = jest.fn();
    this.showOverlay       = jest.fn();
    this.hideOverlay       = jest.fn();
    this.showAddForm       = jest.fn();
    this.hideAddForm       = jest.fn();
    this.setError          = jest.fn();
    this.clearError        = jest.fn();
    this.clear             = jest.fn();
};

var UserController = require('../../../public/js/controllers/user-controller.js');

function buildDom() {
    document.body.innerHTML = [
        '<button id="userIndicator"><span id="userIndicatorDot"></span><span id="userIndicatorName">Guest</span></button>',
        '<button id="openUserSwitcher"></button>',
        '<button id="cancelUserSwitcher"></button>',
        '<ul id="userSwitcherList"></ul>',
        '<button id="addNewUserBtn"></button>',
        '<input type="text" id="newUserName" value="" />',
        '<input type="checkbox" id="newUserAdmin" />',
        '<button id="submitNewUser"></button>',
        '<div id="userSwitcherOverlay" hidden></div>',
        '<div id="userSwitcherAddForm" hidden></div>',
        '<p id="userSwitcherError" hidden></p>',
    ].join('');
}

beforeEach(function () {
    buildDom();
    jest.clearAllMocks();

    global.UserModel = function () {
        this.getAll = jest.fn().mockResolvedValue([]);
        this.create = jest.fn().mockResolvedValue({ id: 5, name: 'New', role: 'resident' });
        this.delete = jest.fn().mockResolvedValue(null);
        this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
        this.login  = jest.fn().mockResolvedValue({ id: 3, name: 'Sophia', role: 'admin', color: '#d93849' });
        this.logout = jest.fn().mockResolvedValue(null);
    };
    global.UserView = function () {
        this.render            = jest.fn();
        this.renderCurrentUser = jest.fn();
        this.showOverlay       = jest.fn();
        this.hideOverlay       = jest.fn();
        this.showAddForm       = jest.fn();
        this.hideAddForm       = jest.fn();
        this.setError          = jest.fn();
        this.clearError        = jest.fn();
        this.clear             = jest.fn();
    };
});

describe('UserController constructor', function () {
    test('extends BaseController', function () {
        var ctrl = new UserController();
        expect(ctrl instanceof BaseController).toBe(true);
    });
});

describe('UserController.init', function () {
    test('calls refresh() on init to load current user', function () {
        var ctrl = new UserController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.init();
        expect(ctrl.refresh).toHaveBeenCalled();
    });

    test('cancelUserSwitcher click calls view.hideOverlay()', function () {
        var ctrl = new UserController();
        ctrl.init();
        document.getElementById('cancelUserSwitcher').click();
        expect(ctrl.view.hideOverlay).toHaveBeenCalled();
    });

    test('addNewUserBtn click calls view.showAddForm()', function () {
        var ctrl = new UserController();
        ctrl.init();
        document.getElementById('addNewUserBtn').click();
        expect(ctrl.view.showAddForm).toHaveBeenCalled();
    });
});

describe('UserController.refresh', function () {
    test('calls model.me() and renders current user via view.renderCurrentUser()', async function () {
        var user = { id: 3, name: 'Sophia', role: 'admin', color: '#d93849' };
        global.UserModel = function () {
            this.me = jest.fn().mockResolvedValue(user);
            this.getAll = jest.fn().mockResolvedValue([]);
        };
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
        };
        var ctrl = new UserController();
        await ctrl.refresh();
        expect(ctrl.model.me).toHaveBeenCalled();
        expect(ctrl.view.renderCurrentUser).toHaveBeenCalledWith(user);
    });
});

describe('UserController.openSwitcher', function () {
    test('fetches users, renders them, and shows overlay', async function () {
        var users = [{ id: 2, name: 'Alex', role: 'resident', color: null }];
        global.UserModel = function () {
            this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
            this.getAll = jest.fn().mockResolvedValue(users);
        };
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
            this.render            = jest.fn();
            this.showOverlay       = jest.fn();
            this.clearError        = jest.fn();
            this.hideAddForm       = jest.fn();
        };
        var ctrl = new UserController();
        await ctrl.openSwitcher();
        expect(ctrl.model.getAll).toHaveBeenCalled();
        expect(ctrl.view.render).toHaveBeenCalledWith(users);
        expect(ctrl.view.showOverlay).toHaveBeenCalled();
    });
});

describe('UserController.handleUserClick', function () {
    test('calls model.login with the clicked user id and emits user:changed', async function () {
        var loginUser = { id: 3, name: 'Sophia', role: 'admin', color: '#d93849' };
        global.UserModel = function () {
            this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
            this.login  = jest.fn().mockResolvedValue(loginUser);
            this.getAll = jest.fn().mockResolvedValue([]);
        };
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
            this.hideOverlay       = jest.fn();
        };
        var ctrl = new UserController();

        // Simulate a click on a button with data-user-id="3"
        var li   = document.createElement('li');
        var btn  = document.createElement('button');
        btn.dataset.userId = '3';
        li.appendChild(btn);
        document.getElementById('userSwitcherList').appendChild(li);

        var clickEvent = { target: btn };
        ctrl.handleUserClick(clickEvent);

        // Wait for the login promise to resolve
        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(ctrl.model.login).toHaveBeenCalledWith(3);
        expect(ctrl.view.renderCurrentUser).toHaveBeenCalledWith(loginUser);
        expect(ctrl.view.hideOverlay).toHaveBeenCalled();
        expect(global.AppEvents.emit).toHaveBeenCalledWith('user:changed', { user: loginUser });
    });

    test('ignores clicks that are not on a [data-user-id] element', function () {
        var ctrl = new UserController();
        var e = { target: { closest: jest.fn().mockReturnValue(null) } };
        ctrl.handleUserClick(e);
        expect(ctrl.model.login).not.toHaveBeenCalled();
    });
});

describe('UserController.handleAddUser', function () {
    test('calls model.create with name and resident role, then refreshes list', async function () {
        var created = { id: 7, name: 'Jordan', role: 'resident', color: null };
        global.UserModel = function () {
            this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
            this.create = jest.fn().mockResolvedValue(created);
            this.getAll = jest.fn().mockResolvedValue([created]);
        };
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
            this.render            = jest.fn();
            this.hideAddForm       = jest.fn();
            this.clearError        = jest.fn();
            this.setError          = jest.fn();
        };
        document.getElementById('newUserName').value = 'Jordan';
        document.getElementById('newUserAdmin').checked = false;

        var ctrl = new UserController();
        await ctrl.handleAddUser();

        expect(ctrl.model.create).toHaveBeenCalledWith('Jordan', 'resident', null, false);
        expect(ctrl.view.render).toHaveBeenCalled();
        expect(ctrl.view.hideAddForm).toHaveBeenCalled();
        expect(ctrl.view.clearError).toHaveBeenCalled();
    });

    test('shows error when name is empty', async function () {
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
            this.setError          = jest.fn();
            this.clearError        = jest.fn();
        };
        document.getElementById('newUserName').value = '';

        var ctrl = new UserController();
        await ctrl.handleAddUser();

        expect(ctrl.view.setError).toHaveBeenCalledWith('Name is required.');
        expect(ctrl.model.create).not.toHaveBeenCalled();
    });

    test('shows error when model.create rejects', async function () {
        global.UserModel = function () {
            this.me     = jest.fn().mockResolvedValue({ id: 0, name: 'Guest', role: 'guest', color: null });
            this.create = jest.fn().mockRejectedValue(new Error('role must be one of: guest, resident, admin'));
            this.getAll = jest.fn().mockResolvedValue([]);
        };
        global.UserView = function () {
            this.renderCurrentUser = jest.fn();
            this.setError          = jest.fn();
            this.clearError        = jest.fn();
            this.hideAddForm       = jest.fn();
            this.render            = jest.fn();
        };
        document.getElementById('newUserName').value = 'BadUser';

        var ctrl = new UserController();
        await ctrl.handleAddUser();

        expect(ctrl.view.setError).toHaveBeenCalledWith('role must be one of: guest, resident, admin');
    });
});
