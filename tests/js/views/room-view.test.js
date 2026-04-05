/**
 * Tests for RoomView — room card rendering.
 */

'use strict';

var BaseView = require('../../../public/js/views/base-view.js');
global.BaseView = BaseView;
var RoomView = require('../../../public/js/views/room-view.js');

beforeEach(function () {
    document.body.innerHTML = [
        '<template id="room-card">',
        '  <div class="room-card">',
        '    <span class="room-card-title"></span>',
        '  </div>',
        '</template>',
        '<div id="roomsContainer"></div>'
    ].join('');
});

describe('RoomView constructor', function () {
    test('extends BaseView, bound to room-card template and roomsContainer', function () {
        var view = new RoomView();
        expect(view instanceof BaseView).toBe(true);
        expect(view.template).toBe(document.getElementById('room-card'));
        expect(view.container).toBe(document.getElementById('roomsContainer'));
    });
});

describe('RoomView.render — empty state', function () {
    test('shows "No rooms added yet." when passed an empty array', function () {
        var view = new RoomView();
        view.render([]);
        var empty = document.querySelector('.empty');
        expect(empty).not.toBeNull();
        expect(empty.textContent).toBe('No rooms added yet.');
    });

    test('shows empty state when passed null', function () {
        var view = new RoomView();
        view.render(null);
        expect(document.querySelector('.empty')).not.toBeNull();
    });
});

describe('RoomView.render — room cards', function () {
    test('renders one card per room', function () {
        var view = new RoomView();
        view.render([
            { id: 1, display_name: 'Living Room' },
            { id: 2, display_name: 'Kitchen' }
        ]);
        var cards = document.querySelectorAll('.room-card');
        expect(cards.length).toBe(2);
    });

    test('sets data-id and title text on each card', function () {
        var view = new RoomView();
        view.render([{ id: 7, display_name: 'Bedroom' }]);
        var card = document.querySelector('.room-card');
        expect(card.dataset.id).toBe('7');
        expect(card.querySelector('.room-card-title').textContent).toBe('Bedroom');
    });

    test('clears previous content before re-render', function () {
        var view = new RoomView();
        view.render([{ id: 1, display_name: 'First' }]);
        view.render([{ id: 2, display_name: 'Second' }]);
        var cards = document.querySelectorAll('.room-card');
        expect(cards.length).toBe(1);
        expect(cards[0].querySelector('.room-card-title').textContent).toBe('Second');
    });
});
