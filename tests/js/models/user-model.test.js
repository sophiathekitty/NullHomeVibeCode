/**
 * Tests for UserModel — /api/users and /api/auth resource model.
 */

'use strict';

var BaseModel = require('../../../public/js/models/base-model.js');
global.BaseModel = BaseModel;
var UserModel = require('../../../public/js/models/user-model.js');

beforeEach(function () {
    jest.clearAllMocks();
});

describe('UserModel constructor', function () {
    test('extends BaseModel with _basePath = /api/users', function () {
        var model = new UserModel();
        expect(model instanceof BaseModel).toBe(true);
        expect(model._basePath).toBe('/api/users');
    });
});

describe('UserModel.getAll', function () {
    test('calls GET /api/users and returns unwrapped array', async function () {
        var users = [{ id: 2, name: 'Alex', role: 'resident' }];
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: users }); }
        });
        var model = new UserModel();
        var result = await model.getAll();
        expect(global.fetch).toHaveBeenCalledWith('/api/users', { method: 'GET' });
        expect(result).toEqual(users);
    });
});

describe('UserModel.create', function () {
    test('calls POST /api/users with correct JSON body', async function () {
        var created = { id: 5, name: 'Morgan', role: 'resident' };
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: created }); }
        });
        var model = new UserModel();
        var result = await model.create('Morgan', 'resident', null, false);
        expect(global.fetch).toHaveBeenCalledWith('/api/users', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ name: 'Morgan', role: 'resident', color: null, show_admin_ui: false }),
        }));
        expect(result).toEqual(created);
    });
});

describe('UserModel.delete', function () {
    test('calls DELETE /api/users/5', async function () {
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: null }); }
        });
        var model = new UserModel();
        await model.delete(5);
        expect(global.fetch).toHaveBeenCalledWith('/api/users/5', { method: 'DELETE' });
    });
});

describe('UserModel.me', function () {
    test('calls GET /api/auth/me and returns unwrapped user object', async function () {
        var user = { id: 3, name: 'Sophia', role: 'admin' };
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: user }); }
        });
        var model = new UserModel();
        var result = await model.me();
        expect(global.fetch).toHaveBeenCalledWith('/api/auth/me', { method: 'GET' });
        expect(result).toEqual(user);
    });

    test('failed me() call rejects with the error message from the envelope', async function () {
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: false, error: 'Session expired' }); }
        });
        var model = new UserModel();
        await expect(model.me()).rejects.toThrow('Session expired');
    });
});

describe('UserModel.login', function () {
    test('calls POST /api/auth/login with body { user_id: 3 }', async function () {
        var user = { id: 3, name: 'Sophia', role: 'admin' };
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: user }); }
        });
        var model = new UserModel();
        var result = await model.login(3);
        expect(global.fetch).toHaveBeenCalledWith('/api/auth/login', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ user_id: 3 }),
        }));
        expect(result).toEqual(user);
    });
});

describe('UserModel.logout', function () {
    test('calls POST /api/auth/logout', async function () {
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: null }); }
        });
        var model = new UserModel();
        await model.logout();
        expect(global.fetch).toHaveBeenCalledWith('/api/auth/logout', { method: 'POST' });
    });
});
