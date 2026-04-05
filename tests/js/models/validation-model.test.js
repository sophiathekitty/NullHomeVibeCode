/**
 * Tests for ValidationModel — /api/validation resource model.
 */

'use strict';

var BaseModel = require('../../../public/js/models/base-model.js');
global.BaseModel = BaseModel;
var ValidationModel = require('../../../public/js/models/validation-model.js');

function makeFetchMock(data) {
    return jest.fn().mockResolvedValue({
        json: function () { return Promise.resolve({ success: true, data: data }); }
    });
}

describe('ValidationModel constructor', function () {
    test('extends BaseModel with /api/validation base path', function () {
        var model = new ValidationModel();
        expect(model instanceof BaseModel).toBe(true);
        expect(model._basePath).toBe('/api/validation');
    });
});

describe('ValidationModel.run', function () {
    test('POSTs to /api/validation/run with empty body', async function () {
        var report = { results: [], orphan_tables: [], has_errors: false };
        global.fetch = makeFetchMock(report);
        var model = new ValidationModel();
        var result = await model.run();
        expect(global.fetch).toHaveBeenCalledWith(
            '/api/validation/run',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({})
            })
        );
        expect(result).toEqual(report);
    });

    test('propagates errors from a failed run', async function () {
        global.fetch = jest.fn().mockResolvedValue({
            json: function () { return Promise.resolve({ success: false, error: 'DB error' }); }
        });
        var model = new ValidationModel();
        await expect(model.run()).rejects.toThrow('DB error');
    });
});

describe('ValidationModel.deleteTables', function () {
    test('POSTs to /api/validation/delete-tables with tables and confirm flag', async function () {
        var deleteResult = { deleted: ['old_table'], skipped: [], errors: [] };
        global.fetch = makeFetchMock(deleteResult);
        var model = new ValidationModel();
        var result = await model.deleteTables(['old_table']);
        expect(global.fetch).toHaveBeenCalledWith(
            '/api/validation/delete-tables',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ tables: ['old_table'], confirm: true })
            })
        );
        expect(result).toEqual(deleteResult);
    });
});
