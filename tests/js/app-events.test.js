/**
 * Tests for AppEvents — static named-event dispatcher.
 *
 * Each test resets module state via jest.resetModules() so the static
 * #listeners field starts empty for every test case.
 */

'use strict';

var AppEvents;

beforeEach(function () {
    jest.resetModules();
    AppEvents = require('../../public/js/app-events.js');
});

describe('AppEvents.on / emit', function () {
    test('on subscribes a callback; emit calls it with payload', function () {
        var cb = jest.fn();
        AppEvents.on('test:event', cb);
        AppEvents.emit('test:event', { value: 42 });
        expect(cb).toHaveBeenCalledTimes(1);
        expect(cb).toHaveBeenCalledWith({ value: 42 });
    });

    test('emit with no subscribers does not throw', function () {
        expect(function () {
            AppEvents.emit('no:listeners', {});
        }).not.toThrow();
    });

    test('multiple subscribers all receive the payload', function () {
        var cb1 = jest.fn();
        var cb2 = jest.fn();
        AppEvents.on('multi:event', cb1);
        AppEvents.on('multi:event', cb2);
        AppEvents.emit('multi:event', { x: 1 });
        expect(cb1).toHaveBeenCalledWith({ x: 1 });
        expect(cb2).toHaveBeenCalledWith({ x: 1 });
    });

    test('emit defaults payload to empty object', function () {
        var cb = jest.fn();
        AppEvents.on('default:payload', cb);
        AppEvents.emit('default:payload');
        expect(cb).toHaveBeenCalledWith({});
    });
});

describe('AppEvents.off', function () {
    test('off unsubscribes the callback; it is not called after removal', function () {
        var cb = jest.fn();
        AppEvents.on('remove:event', cb);
        AppEvents.off('remove:event', cb);
        AppEvents.emit('remove:event', {});
        expect(cb).not.toHaveBeenCalled();
    });

    test('off with no existing listeners does not throw', function () {
        var cb = jest.fn();
        expect(function () {
            AppEvents.off('nonexistent:event', cb);
        }).not.toThrow();
    });

    test('off removes only the specified callback', function () {
        var cb1 = jest.fn();
        var cb2 = jest.fn();
        AppEvents.on('partial:remove', cb1);
        AppEvents.on('partial:remove', cb2);
        AppEvents.off('partial:remove', cb1);
        AppEvents.emit('partial:remove', {});
        expect(cb1).not.toHaveBeenCalled();
        expect(cb2).toHaveBeenCalledTimes(1);
    });
});

describe('AppEvents wildcard listener', function () {
    test('* listener receives every emitted event name and payload', function () {
        var wildcard = jest.fn();
        AppEvents.on('*', wildcard);
        AppEvents.emit('room:added', { id: 7 });
        AppEvents.emit('app:ready', {});
        expect(wildcard).toHaveBeenCalledTimes(2);
        expect(wildcard).toHaveBeenCalledWith('room:added', { id: 7 });
        expect(wildcard).toHaveBeenCalledWith('app:ready', {});
    });
});
