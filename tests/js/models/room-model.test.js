/**
 * Tests for RoomModel — /api/rooms resource model.
 */

'use strict';

var BaseModel = require('../../../public/js/models/base-model.js');
global.BaseModel = BaseModel;
var RoomModel = require('../../../public/js/models/room-model.js');

describe('RoomModel constructor', function () {
    test('extends BaseModel with /api/rooms base path', function () {
        var model = new RoomModel();
        expect(model instanceof BaseModel).toBe(true);
        expect(model._basePath).toBe('/api/rooms');
    });
});

describe('RoomModel.getAll', function () {
    test('performs a GET to /api/rooms and returns unwrapped data', async function () {
        var rooms = [{ id: 1, name: 'living', display_name: 'Living Room' }];
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: rooms }); }
        });
        var model = new RoomModel();
        var result = await model.getAll();
        expect(global.fetch).toHaveBeenCalledWith('/api/rooms', { method: 'GET' });
        expect(result).toEqual(rooms);
    });

    test('propagates errors thrown by the base GET request', async function () {
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: false, error: 'Server error' }); }
        });
        var model = new RoomModel();
        await expect(model.getAll()).rejects.toThrow('Server error');
    });
});
