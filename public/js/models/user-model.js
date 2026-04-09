/**
 * Model for the user identity and auth API resources.
 * Handles fetch calls to /api/users and /api/auth.
 * @extends BaseModel
 */
class UserModel extends BaseModel {

    /**
     * Creates a UserModel bound to the /api/users endpoint.
     * Auth routes (/api/auth/…) are called directly with fetch inside each method.
     */
    constructor() {
        super('/api/users');
    }

    /**
     * Fetch all human users for the switcher list.
     *
     * @async
     * @returns {Promise<Array<Object>>}
     */
    async getAll() {
        return this.get();
    }

    /**
     * Create a new human user.
     *
     * @async
     * @param {string} name
     * @param {string} role - One of: 'guest', 'resident', 'admin'
     * @param {string|null} color - Hex color or null
     * @param {boolean} showAdminUi
     * @returns {Promise<Object>} The created user.
     */
    async create(name, role, color, showAdminUi) {
        return this.post('', {
            name:          name,
            role:          role,
            color:         color,
            show_admin_ui: showAdminUi,
        });
    }

    /**
     * Delete a user by id.
     *
     * @async
     * @param {number} id
     * @returns {Promise<null>}
     */
    async delete(id) {
        return super.delete('/' + id);
    }

    /**
     * Fetch the current user from the session cookie.
     * Calls GET /api/auth/me. Always resolves (returns guest on no session).
     *
     * @async
     * @returns {Promise<Object>} User object.
     */
    async me() {
        var response = await fetch('/api/auth/me', { method: 'GET' });
        var json = await response.json();
        if (!json.success) {
            throw new Error(json.error || 'API request failed.');
        }
        return json.data;
    }

    /**
     * Log in as the given user. Sets the session cookie server-side.
     *
     * @async
     * @param {number} userId
     * @returns {Promise<Object>} The logged-in user object.
     */
    async login(userId) {
        var response = await fetch('/api/auth/login', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ user_id: userId }),
        });
        var json = await response.json();
        if (!json.success) {
            throw new Error(json.error || 'API request failed.');
        }
        return json.data;
    }

    /**
     * Log out — clears the session cookie server-side.
     *
     * @async
     * @returns {Promise<null>}
     */
    async logout() {
        var response = await fetch('/api/auth/logout', { method: 'POST' });
        var json = await response.json();
        if (!json.success) {
            throw new Error(json.error || 'API request failed.');
        }
        return json.data;
    }
}

if (typeof module !== 'undefined') { module.exports = UserModel; }
