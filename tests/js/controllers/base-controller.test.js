/**
 * Tests for BaseController — polling lifecycle and refresh hook.
 */

'use strict';

var BaseController = require('../../../public/js/controllers/base-controller.js');

beforeEach(function () {
    jest.useFakeTimers();
});

afterEach(function () {
    jest.useRealTimers();
});

describe('BaseController constructor', function () {
    test('initialises _pollInterval to null', function () {
        var ctrl = new BaseController();
        expect(ctrl._pollInterval).toBeNull();
    });
});

describe('BaseController.init', function () {
    test('init() is a no-op that does not throw', function () {
        var ctrl = new BaseController();
        expect(function () { ctrl.init(); }).not.toThrow();
    });
});

describe('BaseController.refresh', function () {
    test('throws requiring subclass override', async function () {
        var ctrl = new BaseController();
        await expect(ctrl.refresh()).rejects.toThrow('BaseController.refresh() must be implemented by subclass.');
    });
});

describe('BaseController.startPolling / stopPolling', function () {
    test('startPolling calls refresh on the given interval', function () {
        var ctrl = new BaseController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.startPolling(1000);
        jest.advanceTimersByTime(3000);
        expect(ctrl.refresh).toHaveBeenCalledTimes(3);
    });

    test('stopPolling prevents further refresh calls', function () {
        var ctrl = new BaseController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.startPolling(1000);
        jest.advanceTimersByTime(1000);
        ctrl.stopPolling();
        jest.advanceTimersByTime(3000);
        expect(ctrl.refresh).toHaveBeenCalledTimes(1);
    });

    test('stopPolling clears _pollInterval', function () {
        var ctrl = new BaseController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.startPolling(500);
        ctrl.stopPolling();
        expect(ctrl._pollInterval).toBeNull();
    });

    test('calling stopPolling with no active interval does not throw', function () {
        var ctrl = new BaseController();
        expect(function () { ctrl.stopPolling(); }).not.toThrow();
    });

    test('startPolling clears any existing interval before starting a new one', function () {
        var ctrl = new BaseController();
        ctrl.refresh = jest.fn().mockResolvedValue();
        ctrl.startPolling(1000);
        ctrl.startPolling(1000);
        jest.advanceTimersByTime(1000);
        // Only one interval should be active
        expect(ctrl.refresh).toHaveBeenCalledTimes(1);
    });
});
