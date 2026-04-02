/**
 * NullHome — Remove Room overlay: room list rendering and delete action.
 */

/**
 * Initialises the Remove Room overlay.
 *
 * Opens the overlay when #openRemoveRoom is clicked (and closes the drawer).
 * Reads the current room list via the `getRooms` callback and renders one
 * list item per room with a Remove button. Clicking Remove calls
 * DELETE /api/rooms/{id}, removes the item from the rendered list on success,
 * and calls `onRoomsChanged` with the updated array to trigger a re-render
 * of the main rooms view.
 *
 * @param {function(): Array<{id: number, name: string, display_name: string}>} getRooms - Returns the current room array from app.js.
 * @param {function(Array<{id: number, name: string, display_name: string}>): void} onRoomsChanged - Called with the updated array after a successful delete.
 * @returns {void}
 */
function initRoomRemove(getRooms, onRoomsChanged) {
    var overlay       = document.getElementById('removeRoomOverlay');
    var list          = document.getElementById('roomRemoveList');
    var openBtn       = document.getElementById('openRemoveRoom');
    var cancelBtn     = document.getElementById('cancelRemoveRoom');
    var drawer        = document.getElementById('drawer');
    var drawerOverlay = document.getElementById('drawerOverlay');

    /**
     * Opens the Remove Room overlay, closes the drawer, and populates the list.
     *
     * @returns {void}
     */
    function openOverlay() {
        drawer.classList.remove('is-open');
        drawerOverlay.classList.remove('is-visible');
        renderList();
        overlay.removeAttribute('hidden');
    }

    /**
     * Closes the Remove Room overlay.
     *
     * @returns {void}
     */
    function closeOverlay() {
        overlay.setAttribute('hidden', '');
    }

    /**
     * Renders the list of rooms inside the overlay.
     *
     * @returns {void}
     */
    function renderList() {
        var rooms = getRooms();
        list.innerHTML = '';

        if (!rooms || rooms.length === 0) {
            var empty = document.createElement('li');
            empty.textContent = 'No rooms to remove.';
            list.appendChild(empty);
            return;
        }

        rooms.forEach(function (room) {
            var li   = document.createElement('li');
            var span = document.createElement('span');
            span.textContent = room.display_name;

            var btn = document.createElement('button');
            btn.textContent = 'Remove';
            btn.addEventListener('click', function () {
                deleteRoom(room.id, li);
            });

            li.appendChild(span);
            li.appendChild(btn);
            list.appendChild(li);
        });
    }

    /**
     * Sends DELETE /api/rooms/{id} and removes the list item on success.
     *
     * Calls onRoomsChanged with the updated rooms array after deletion.
     * Displays the list item text in an error state if the request fails.
     *
     * @async
     * @param {number} id - The ID of the room to delete.
     * @param {HTMLElement} listItem - The list item element to remove from the DOM.
     * @returns {Promise<void>}
     */
    async function deleteRoom(id, listItem) {
        try {
            var response = await fetch('/api/rooms/' + id, {
                method: 'DELETE'
            });

            if (!response.ok) {
                listItem.querySelector('span').textContent += ' (failed to remove)';
                return;
            }

            listItem.remove();

            var updated = getRooms().filter(function (r) { return r.id !== id; });
            onRoomsChanged(updated);
        } catch (_err) {
            listItem.querySelector('span').textContent += ' (network error)';
        }
    }

    openBtn.addEventListener('click', openOverlay);
    cancelBtn.addEventListener('click', closeOverlay);
}
