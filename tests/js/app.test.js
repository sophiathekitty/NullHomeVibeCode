/**
 * Tests for app.js — DOMContentLoaded bootstrap wiring.
 *
 * Verifies that app.js sets up all controllers and modules when the DOM is ready.
 */

'use strict';

describe('app.js DOMContentLoaded bootstrap', function () {
    var mockRoomController;
    var mockValidationController;

    beforeEach(function () {
        jest.resetModules();

        mockRoomController = {
            init: jest.fn(),
            getRooms: jest.fn().mockReturnValue([]),
            setRooms: jest.fn()
        };
        mockValidationController = { init: jest.fn() };

        global.RoomController = jest.fn().mockReturnValue(mockRoomController);
        global.ValidationController = jest.fn().mockReturnValue(mockValidationController);
        global.AppEvents = { on: jest.fn(), emit: jest.fn() };
        global.initMenu = jest.fn();
        global.initRoomForm = jest.fn();
        global.initRoomRemove = jest.fn();
        global.initWemoScan = jest.fn();
        global.initNullHubScan = jest.fn();
        global.initServiceLogs = jest.fn();

        require('../../public/js/app.js');
        document.dispatchEvent(new Event('DOMContentLoaded'));
    });

    test('instantiates RoomController and calls init()', function () {
        expect(global.RoomController).toHaveBeenCalled();
        expect(mockRoomController.init).toHaveBeenCalled();
    });

    test('instantiates ValidationController and calls init()', function () {
        expect(global.ValidationController).toHaveBeenCalled();
        expect(mockValidationController.init).toHaveBeenCalled();
    });

    test('initialises menu', function () {
        expect(global.initMenu).toHaveBeenCalled();
    });

    test('initialises room form with a callback', function () {
        expect(global.initRoomForm).toHaveBeenCalledWith(expect.any(Function));
    });

    test('room form callback emits room:added', function () {
        var onSuccess = global.initRoomForm.mock.calls[0][0];
        onSuccess();
        expect(global.AppEvents.emit).toHaveBeenCalledWith('room:added', {});
    });

    test('initialises room remove with getRooms and setRooms callbacks', function () {
        expect(global.initRoomRemove).toHaveBeenCalledWith(
            expect.any(Function),
            expect.any(Function)
        );
    });

    test('initialises wemo scan', function () {
        expect(global.initWemoScan).toHaveBeenCalled();
    });

    test('initialises nullhub scan', function () {
        expect(global.initNullHubScan).toHaveBeenCalled();
    });

    test('initialises service logs', function () {
        expect(global.initServiceLogs).toHaveBeenCalled();
    });

    test('emits app:ready at the end', function () {
        expect(global.AppEvents.emit).toHaveBeenCalledWith('app:ready', {});
    });
});
