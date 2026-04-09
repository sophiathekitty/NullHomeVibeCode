/**
 * View for the user identity switcher.
 * Clones the #user-switcher-item template and renders one item per user.
 * Also manages the header indicator and overlay visibility.
 * @extends BaseView
 */
class UserView extends BaseView {

    /**
     * Creates a UserView bound to the user-switcher-item template and userSwitcherList container.
     */
    constructor() {
        super('user-switcher-item', 'userSwitcherList');
    }

    /**
     * Render the list of users into #userSwitcherList.
     * Clears existing content first. Clones the user-switcher-item template per user.
     * Sets data-user-id, .user-switcher-name text, .user-switcher-role text.
     * Sets .user-switcher-dot background-color inline to user.color (or '' if null).
     *
     * @param {Array<Object>} users
     * @returns {void}
     */
    render(users) {
        this.clear();

        var self = this;
        users.forEach(function (user) {
            var node = self.cloneTemplate();
            var btn  = node.querySelector('.user-switcher-btn');
            var dot  = node.querySelector('.user-switcher-dot');
            var name = node.querySelector('.user-switcher-name');
            var role = node.querySelector('.user-switcher-role');

            btn.dataset.userId = user.id;
            name.textContent   = user.name;
            role.textContent   = user.role;
            role.dataset.role  = user.role;
            dot.style.backgroundColor = user.color || '';

            self.container.appendChild(node);
        });
    }

    /**
     * Update the header indicator with the current user's name and color dot.
     * Sets #userIndicatorName text and #userIndicatorDot background-color inline.
     *
     * @param {Object} user
     * @returns {void}
     */
    renderCurrentUser(user) {
        var nameEl = document.getElementById('userIndicatorName');
        var dotEl  = document.getElementById('userIndicatorDot');
        if (nameEl) {
            nameEl.textContent = user.name;
        }
        if (dotEl) {
            dotEl.style.backgroundColor = user.color || '';
        }
    }

    /**
     * Remove hidden from #userSwitcherOverlay.
     *
     * @returns {void}
     */
    showOverlay() {
        var overlay = document.getElementById('userSwitcherOverlay');
        if (overlay) {
            overlay.removeAttribute('hidden');
        }
    }

    /**
     * Add hidden to #userSwitcherOverlay.
     *
     * @returns {void}
     */
    hideOverlay() {
        var overlay = document.getElementById('userSwitcherOverlay');
        if (overlay) {
            overlay.setAttribute('hidden', '');
        }
    }

    /**
     * Remove hidden from #userSwitcherAddForm.
     *
     * @returns {void}
     */
    showAddForm() {
        var form = document.getElementById('userSwitcherAddForm');
        if (form) {
            form.removeAttribute('hidden');
        }
    }

    /**
     * Add hidden to #userSwitcherAddForm.
     *
     * @returns {void}
     */
    hideAddForm() {
        var form = document.getElementById('userSwitcherAddForm');
        if (form) {
            form.setAttribute('hidden', '');
        }
    }

    /**
     * Show an error message in #userSwitcherError.
     *
     * @param {string} message
     * @returns {void}
     */
    setError(message) {
        var el = document.getElementById('userSwitcherError');
        if (el) {
            el.textContent = message;
            el.removeAttribute('hidden');
        }
    }

    /**
     * Hide #userSwitcherError and clear its text.
     *
     * @returns {void}
     */
    clearError() {
        var el = document.getElementById('userSwitcherError');
        if (el) {
            el.textContent = '';
            el.setAttribute('hidden', '');
        }
    }
}

if (typeof module !== 'undefined') { module.exports = UserView; }
