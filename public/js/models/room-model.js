/**
 * Model for the rooms API resource.
 * Handles all fetch calls to /api/rooms and unwraps the response envelope.
 * @extends BaseModel
 */
class RoomModel extends BaseModel {

    /**
     * Creates a RoomModel bound to the /api/rooms endpoint.
     */
    constructor() {
        super('/api/rooms');
    }

    /**
     * Fetches all rooms from the API.
     *
     * @async
     * @returns {Promise<Array<Object>>} Array of room data objects.
     */
    async getAll() {
        return this.get();
    }
}
