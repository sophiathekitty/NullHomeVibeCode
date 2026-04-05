/**
 * Tests for RoomController — model/view wiring and AppEvents integration.
 */

'use strict';

var BaseController = require('../../../public/js/controllers/base-controller.js');
global.BaseController = BaseController;

// Mock AppEvents
global.AppEvents = { on: jest.fn(), off: jest.fn(), emit: jest.fn() };

// Stub model and view constructors
global.RoomModel = function () {
    this.getAll = jest.fn().mockResolvedValue([]);
};
global.RoomView = function () {
    this.render = jest.fn();
    this.clear = jest.fn();
};

var RoomController = require('../../../public/js/controllers/room-controller.js');

beforeEach(function () {
    jest.clearAllMocks();
    global.RoomModel = function () {
        this.getAll = jest.fn().mockResolvedValue([]);
    };
    global.RoomView = function () {
        this.render = jest.fn();
        this.clear = jest.fn();
    };
});

describe('RoomController constructor', function () {
    test('extends BaseController', function () {
        var ctrl = new RoomController();
        expect(ctrl instanceof BaseController).toBe(true);
    });

    test('initialises _rooms as an empty array', function () {
        var ctrl = new RoomController();
        expect(ctrl._rooms).toEqual([]);
    });
});

describe('RoomController.init', function () {
    test('subscribes to app:ready and room:added AppEvents', function () {
        var ctrl = new RoomController();
        ctrl.init();
        var subscribedEvents = global.AppEvents.on.mock.calls.map(function (c) { return c[0]; });
        expect(subscribedEvents).toContain('app:ready');
        expect(subscribedEvents).toContain('room:added');
    });
});

describe('RoomController.refresh', function () {
    test('fetches rooms and renders them', async function () {
        var rooms = [{ id: 1, display_name: 'Living Room' }];
        global.RoomModel = function () {
            this.getAll = jest.fn().mockResolvedValue(rooms);
        };
        global.RoomView = function () {
            this.render = jest.fn();
        };
        var ctrl = new RoomController();
        await ctrl.refresh();
        expect(ctrl.view.render).toHaveBeenCalledWith(rooms);
    });

    test('stores fetched rooms in _rooms cache', async function () {
        var rooms = [{ id: 2, display_name: 'Kitchen' }];
        global.RoomModel = function () {
            this.getAll = jest.fn().mockResolvedValue(rooms);
        };
        global.RoomView = function () { this.render = jest.fn(); };
        var ctrl = new RoomController();
        await ctrl.refresh();
        expect(ctrl._rooms).toEqual(rooms);
    });
});

describe('RoomController.getRooms', function () {
    test('returns the current _rooms cache', function () {
        var ctrl = new RoomController();
        ctrl._rooms = [{ id: 3 }];
        expect(ctrl.getRooms()).toEqual([{ id: 3 }]);
    });
});

describe('RoomController.setRooms', function () {
    test('updates _rooms and re-renders the view', function () {
        var rooms = [{ id: 4, display_name: 'Office' }];
        global.RoomView = function () { this.render = jest.fn(); };
        var ctrl = new RoomController();
        ctrl.setRooms(rooms);
        expect(ctrl._rooms).toEqual(rooms);
        expect(ctrl.view.render).toHaveBeenCalledWith(rooms);
    });
});
