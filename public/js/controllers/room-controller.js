/**
 * Controller for the rooms resource.
 * Wires RoomModel to RoomView, manages the rooms in-memory state,
 * and exposes helpers for the Add Room and Remove Room overlays.
 * @extends BaseController
 */
class RoomController extends BaseController {

    /**
     * Creates a RoomController with its own RoomModel and RoomView.
     */
    constructor() {
        super();
        this.model  = new RoomModel();
        this.view   = new RoomView();
        /** @type {Array<{id: number, name: string, display_name: string}>} */
        this._rooms = [];
    }

    /**
     * Initializes the controller.
     *
     * Subscribes to 'app:ready' and 'room:added' AppEvents so that
     * the room list is fetched and rendered on startup and after a new room
     * is created.
     *
     * @returns {void}
     */
    init() {
        var self = this;
        AppEvents.on('app:ready', function () { self.refresh(); });
        AppEvents.on('room:added', function () { self.refresh(); });
    }

    /**
     * Fetches all rooms and re-renders the view.
     *
     * @async
     * @returns {Promise<void>}
     */
    async refresh() {
        var rooms = await this.model.getAll();
        this._rooms = rooms;
        this.view.render(rooms);
    }

    /**
     * Returns the current in-memory rooms array.
     *
     * Used by the Remove Room overlay to read rooms without an additional fetch.
     *
     * @returns {Array<{id: number, name: string, display_name: string}>} The current rooms array.
     */
    getRooms() {
        return this._rooms;
    }

    /**
     * Updates the in-memory rooms array and re-renders the view.
     *
     * Called by the Remove Room overlay after a successful delete so that
     * the main rooms view reflects the deletion immediately.
     *
     * @param {Array<{id: number, name: string, display_name: string}>} rooms - The updated rooms array.
     * @returns {void}
     */
    setRooms(rooms) {
        this._rooms = rooms;
        this.view.render(rooms);
    }
}

if (typeof module !== 'undefined') { module.exports = RoomController; }
