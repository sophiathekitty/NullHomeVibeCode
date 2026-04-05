/**
 * Tests for room-form.js — Add Room overlay form submission.
 */

'use strict';

var initRoomForm = require('../../public/js/room-form.js').initRoomForm;

function buildDom() {
    document.body.innerHTML = [
        '<div id="addRoomOverlay" hidden></div>',
        '<input id="roomName" value="" />',
        '<input id="roomDisplayName" value="" />',
        '<p id="addRoomError" hidden></p>',
        '<button id="openAddRoom"></button>',
        '<button id="submitAddRoom"></button>',
        '<button id="cancelAddRoom"></button>',
        '<div id="drawer" class="is-open"></div>',
        '<div id="drawerOverlay" class="is-visible"></div>'
    ].join('');
}

beforeEach(function () {
    buildDom();
    global.fetch = jest.fn();
});

afterEach(function () {
    jest.clearAllMocks();
});

describe('initRoomForm — overlay open/close', function () {
    test('clicking openAddRoom removes hidden from the overlay', function () {
        initRoomForm(jest.fn());
        document.getElementById('openAddRoom').click();
        expect(document.getElementById('addRoomOverlay').hasAttribute('hidden')).toBe(false);
    });

    test('clicking cancelAddRoom sets hidden on the overlay', function () {
        initRoomForm(jest.fn());
        document.getElementById('addRoomOverlay').removeAttribute('hidden');
        document.getElementById('cancelAddRoom').click();
        expect(document.getElementById('addRoomOverlay').hasAttribute('hidden')).toBe(true);
    });
});

describe('initRoomForm — validation', function () {
    test('shows error when both fields are empty', function () {
        initRoomForm(jest.fn());
        document.getElementById('submitAddRoom').click();
        expect(document.getElementById('addRoomError').textContent).toBe('Both fields are required.');
        expect(document.getElementById('addRoomError').hasAttribute('hidden')).toBe(false);
    });

    test('shows error when name is empty but display_name is filled', function () {
        initRoomForm(jest.fn());
        document.getElementById('roomDisplayName').value = 'Living Room';
        document.getElementById('submitAddRoom').click();
        expect(document.getElementById('addRoomError').textContent).toBe('Both fields are required.');
    });

    test('does not call fetch when validation fails', function () {
        initRoomForm(jest.fn());
        document.getElementById('submitAddRoom').click();
        expect(global.fetch).not.toHaveBeenCalled();
    });
});

describe('initRoomForm — successful submission', function () {
    test('POSTs to /api/rooms with name and display_name', async function () {
        global.fetch.mockResolvedValue({
            ok: true,
            json: function () { return Promise.resolve({ success: true, data: { id: 1 }, error: null }); }
        });
        var onSuccess = jest.fn();
        initRoomForm(onSuccess);

        document.getElementById('roomName').value = 'living';
        document.getElementById('roomDisplayName').value = 'Living Room';
        document.getElementById('submitAddRoom').click();

        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(global.fetch).toHaveBeenCalledWith('/api/rooms', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ name: 'living', display_name: 'Living Room' })
        }));
        expect(onSuccess).toHaveBeenCalled();
    });

    test('closes overlay and clears fields on success', async function () {
        global.fetch.mockResolvedValue({
            ok: true,
            json: function () { return Promise.resolve({ success: true, data: { id: 2 }, error: null }); }
        });
        initRoomForm(jest.fn());

        document.getElementById('roomName').value = 'kitchen';
        document.getElementById('roomDisplayName').value = 'Kitchen';
        document.getElementById('addRoomOverlay').removeAttribute('hidden');
        document.getElementById('submitAddRoom').click();

        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(document.getElementById('roomName').value).toBe('');
        expect(document.getElementById('addRoomOverlay').hasAttribute('hidden')).toBe(true);
    });
});

describe('initRoomForm — API error handling', function () {
    test('displays API error message inline', async function () {
        global.fetch.mockResolvedValue({
            ok: false,
            json: function () { return Promise.resolve({ success: false, data: null, error: 'Name taken' }); }
        });
        initRoomForm(jest.fn());

        document.getElementById('roomName').value = 'taken';
        document.getElementById('roomDisplayName').value = 'Taken Room';
        document.getElementById('submitAddRoom').click();

        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(document.getElementById('addRoomError').textContent).toBe('Name taken');
        expect(document.getElementById('addRoomError').hasAttribute('hidden')).toBe(false);
    });

    test('displays network error message when fetch rejects', async function () {
        global.fetch.mockRejectedValue(new Error('Network failure'));
        initRoomForm(jest.fn());

        document.getElementById('roomName').value = 'room';
        document.getElementById('roomDisplayName').value = 'Room';
        document.getElementById('submitAddRoom').click();

        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(document.getElementById('addRoomError').textContent).toBe('Network error. Please try again.');
    });
});
