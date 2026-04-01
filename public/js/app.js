/**
 * NullHome — app bootstrap.
 *
 * Fetches rooms from GET /api/rooms on DOMContentLoaded, passes them to
 * renderRooms(), and wires up the drawer, Add Room overlay, and Remove Room
 * overlay via their respective init functions.
 */

/** @type {Array<{id: number, name: string, display_name: string}>} */
var rooms = [];

/**
 * Fetches all rooms from GET /api/rooms.
 *
 * Returns an empty array and logs a warning if the request fails.
 *
 * @async
 * @returns {Promise<Array<{id: number, name: string, display_name: string}>>} The array of room objects.
 */
async function fetchRooms() {
    try {
        var response = await fetch('/api/rooms');
        if (!response.ok) {
            return [];
        }
        return response.json();
    } catch (_err) {
        return [];
    }
}

/**
 * Returns the current in-memory rooms array.
 *
 * Used by room-remove.js to read rooms without a second fetch.
 *
 * @returns {Array<{id: number, name: string, display_name: string}>} The current rooms array.
 */
function getRooms() {
    return rooms;
}

/**
 * Updates the in-memory rooms array and re-renders the rooms container.
 *
 * Called by room-remove.js after a successful delete.
 *
 * @param {Array<{id: number, name: string, display_name: string}>} updated - The new rooms array.
 * @returns {void}
 */
function setRooms(updated) {
    rooms = updated;
    renderRooms(rooms);
}

/**
 * Initialises the application.
 *
 * Fetches rooms, renders them, and wires up menu, form, and remove modules.
 *
 * @async
 * @returns {Promise<void>}
 */
async function init() {
    rooms = await fetchRooms();
    renderRooms(rooms);
    initMenu();
    initRoomForm(async function () {
        rooms = await fetchRooms();
        renderRooms(rooms);
    });
    initRoomRemove(getRooms, setRooms);
}

document.addEventListener('DOMContentLoaded', init);
