/**
 * Tests for room-remove.js — Remove Room overlay: list rendering and delete action.
 */

'use strict';

var initRoomRemove = require('../../public/js/room-remove.js').initRoomRemove;

function buildDom() {
    document.body.innerHTML = [
        '<div id="removeRoomOverlay" hidden></div>',
        '<ul id="roomRemoveList"></ul>',
        '<button id="openRemoveRoom"></button>',
        '<button id="cancelRemoveRoom"></button>',
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

describe('initRoomRemove — overlay open/close', function () {
    test('clicking openRemoveRoom shows the overlay', function () {
        initRoomRemove(function () { return []; }, jest.fn());
        document.getElementById('openRemoveRoom').click();
        expect(document.getElementById('removeRoomOverlay').hasAttribute('hidden')).toBe(false);
    });

    test('clicking cancelRemoveRoom hides the overlay', function () {
        initRoomRemove(function () { return []; }, jest.fn());
        document.getElementById('removeRoomOverlay').removeAttribute('hidden');
        document.getElementById('cancelRemoveRoom').click();
        expect(document.getElementById('removeRoomOverlay').hasAttribute('hidden')).toBe(true);
    });
});

describe('initRoomRemove — room list rendering', function () {
    test('shows "No rooms to remove." when room list is empty', function () {
        initRoomRemove(function () { return []; }, jest.fn());
        document.getElementById('openRemoveRoom').click();
        var list = document.getElementById('roomRemoveList');
        expect(list.textContent).toContain('No rooms to remove.');
    });

    test('renders one list item per room with a Remove button', function () {
        var rooms = [
            { id: 1, display_name: 'Living Room' },
            { id: 2, display_name: 'Kitchen' }
        ];
        initRoomRemove(function () { return rooms; }, jest.fn());
        document.getElementById('openRemoveRoom').click();
        var items = document.querySelectorAll('#roomRemoveList li');
        expect(items.length).toBe(2);
        expect(items[0].textContent).toContain('Living Room');
        expect(items[0].querySelector('button').textContent).toBe('Remove');
    });
});

describe('initRoomRemove — delete action', function () {
    test('clicking Remove calls DELETE /api/rooms/{id} and removes the list item', async function () {
        var rooms = [{ id: 5, display_name: 'Office' }];
        global.fetch.mockResolvedValue({ ok: true });
        var onChanged = jest.fn();
        initRoomRemove(function () { return rooms; }, onChanged);
        document.getElementById('openRemoveRoom').click();

        var btn = document.querySelector('#roomRemoveList li button');
        btn.click();
        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        expect(global.fetch).toHaveBeenCalledWith('/api/rooms/5', { method: 'DELETE' });
        expect(document.querySelectorAll('#roomRemoveList li').length).toBe(0);
        expect(onChanged).toHaveBeenCalledWith([]);
    });

    test('appends "(failed to remove)" to the item on non-OK response', async function () {
        var rooms = [{ id: 6, display_name: 'Garage' }];
        global.fetch.mockResolvedValue({ ok: false });
        initRoomRemove(function () { return rooms; }, jest.fn());
        document.getElementById('openRemoveRoom').click();

        var btn = document.querySelector('#roomRemoveList li button');
        btn.click();
        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        var span = document.querySelector('#roomRemoveList li span');
        expect(span.textContent).toContain('(failed to remove)');
    });

    test('appends "(network error)" when fetch rejects', async function () {
        var rooms = [{ id: 7, display_name: 'Shed' }];
        global.fetch.mockRejectedValue(new Error('timeout'));
        initRoomRemove(function () { return rooms; }, jest.fn());
        document.getElementById('openRemoveRoom').click();

        var btn = document.querySelector('#roomRemoveList li button');
        btn.click();
        await new Promise(function (resolve) { setTimeout(resolve, 0); });

        var span = document.querySelector('#roomRemoveList li span');
        expect(span.textContent).toContain('(network error)');
    });
});
