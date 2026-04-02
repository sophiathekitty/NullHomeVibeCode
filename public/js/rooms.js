/**
 * NullHome — room card rendering.
 */

/**
 * Renders room cards into #roomsContainer.
 *
 * Clears any existing content and inserts one `.room-card` element per
 * room showing the room's `display_name`. No device content is rendered.
 *
 * @param {Array<{id: number, name: string, display_name: string}>} rooms - The list of rooms to render.
 * @returns {void}
 */
function renderRooms(rooms) {
    var container = document.getElementById('roomsContainer');
    container.innerHTML = '';

    if (!rooms || rooms.length === 0) {
        var empty = document.createElement('p');
        empty.className = 'empty';
        empty.textContent = 'No rooms added yet.';
        container.appendChild(empty);
        return;
    }
    console.log('Rendering rooms:', rooms);
    rooms.forEach(function (room) {
        var card = document.createElement('div');
        card.className = 'room-card';
        card.dataset.id = room.id;

        var title = document.createElement('h2');
        title.className = 'room-card-title';
        title.textContent = room.display_name;

        card.appendChild(title);
        container.appendChild(card);
    });
}
