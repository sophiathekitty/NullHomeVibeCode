/**
 * Tests for BaseModel — fetch wrapper and envelope unwrapper.
 */

'use strict';

var BaseModel = require('../../../public/js/models/base-model.js');

function makeFetchMock(responseData) {
    return jest.fn().mockResolvedValue({
        json: function () { return Promise.resolve(responseData); }
    });
}

describe('BaseModel constructor', function () {
    test('stores the base path', function () {
        var model = new BaseModel('/api/test');
        expect(model._basePath).toBe('/api/test');
    });
});

describe('BaseModel.get', function () {
    test('calls fetch with GET method and full URL', async function () {
        global.fetch = makeFetchMock({ success: true, data: [1, 2, 3] });
        var model = new BaseModel('/api/rooms');
        var result = await model.get();
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms', { method: 'GET' });
        expect(result).toEqual([1, 2, 3]);
    });

    test('appends path suffix to base path', async function () {
        global.fetch = makeFetchMock({ success: true, data: {} });
        var model = new BaseModel('/api/rooms');
        await model.get('/123');
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms/123', { method: 'GET' });
    });

    test('throws with response.error when success is false', async function () {
        global.fetch = makeFetchMock({ success: false, error: 'Not found' });
        var model = new BaseModel('/api/rooms');
        await expect(model.get()).rejects.toThrow('Not found');
    });

    test('throws generic message when success is false and error is absent', async function () {
        global.fetch = makeFetchMock({ success: false });
        var model = new BaseModel('/api/rooms');
        await expect(model.get()).rejects.toThrow('API request failed.');
    });
});

describe('BaseModel.post', function () {
    test('calls fetch with POST method, JSON header, and serialised body', async function () {
        global.fetch = makeFetchMock({ success: true, data: { id: 1 } });
        var model = new BaseModel('/api/rooms');
        var result = await model.post('', { name: 'living' });
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: 'living' })
        });
        expect(result).toEqual({ id: 1 });
    });

    test('throws on failed POST envelope', async function () {
        global.fetch = makeFetchMock({ success: false, error: 'Validation error' });
        var model = new BaseModel('/api/rooms');
        await expect(model.post('', {})).rejects.toThrow('Validation error');
    });
});

describe('BaseModel.put', function () {
    test('calls fetch with PUT method and JSON body', async function () {
        global.fetch = makeFetchMock({ success: true, data: { updated: true } });
        var model = new BaseModel('/api/rooms');
        var result = await model.put('/1', { name: 'kitchen' });
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms/1', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: 'kitchen' })
        });
        expect(result).toEqual({ updated: true });
    });
});

describe('BaseModel.delete', function () {
    test('calls fetch with DELETE method', async function () {
        global.fetch = makeFetchMock({ success: true, data: null });
        var model = new BaseModel('/api/rooms');
        await model.delete('/5');
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms/5', { method: 'DELETE' });
    });

    test('throws on failed DELETE', async function () {
        global.fetch = makeFetchMock({ success: false, error: 'Cannot delete' });
        var model = new BaseModel('/api/rooms');
        await expect(model.delete('/5')).rejects.toThrow('Cannot delete');
    });
});
