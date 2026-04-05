/**
 * View for the rooms resource.
 * Clones the #room-card template and renders one card per room into #roomsContainer.
 * @extends BaseView
 */
class RoomView extends BaseView {

    /**
     * Creates a RoomView bound to the room-card template and roomsContainer.
     */
    constructor() {
        super('room-card', 'roomsContainer');
    }

    /**
     * Renders room cards into the container.
     *
     * Clears existing content and inserts one card per room showing the
     * room's display_name. Shows an empty-state message when the array is empty.
     *
     * @param {Array<{id: number, name: string, display_name: string}>} rooms - The list of rooms to render.
     * @returns {void}
     */
    render(rooms) {
        this.clear();

        if (!rooms || rooms.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'empty';
            empty.textContent = 'No rooms added yet.';
            this.container.appendChild(empty);
            return;
        }

        var self = this;
        rooms.forEach(function (room) {
            var node = self.cloneTemplate();
            node.querySelector('.room-card').dataset.id = room.id;
            node.querySelector('.room-card-title').textContent = room.display_name;
            self.container.appendChild(node);
        });
    }
}

if (typeof module !== 'undefined') { module.exports = RoomView; }
