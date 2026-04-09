/**
 * Controller for the user identity switcher.
 * Wires UserModel to UserView and manages identity switching,
 * user creation, and header indicator updates.
 * @extends BaseController
 */
class UserController extends BaseController {

    /**
     * Creates a UserController with its own UserModel and UserView.
     */
    constructor() {
        super();
        this.model = new UserModel();
        this.view  = new UserView();
    }

    /**
     * Initialise the controller: fetch current user, render header indicator,
     * attach all event listeners.
     *
     * Listeners attached:
     *   #userIndicator click      → openSwitcher()
     *   #openUserSwitcher click   → openSwitcher()
     *   #cancelUserSwitcher click → view.hideOverlay()
     *   #userSwitcherList click   → handleUserClick(e)
     *   #addNewUserBtn click      → view.showAddForm()
     *   #submitNewUser click      → handleAddUser()
     *
     * @returns {void}
     */
    init() {
        var self = this;

        this.refresh();

        var indicator = document.getElementById('userIndicator');
        if (indicator) {
            indicator.addEventListener('click', function () { self.openSwitcher(); });
        }

        var openBtn = document.getElementById('openUserSwitcher');
        if (openBtn) {
            openBtn.addEventListener('click', function () { self.openSwitcher(); });
        }

        var cancelBtn = document.getElementById('cancelUserSwitcher');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () { self.view.hideOverlay(); });
        }

        var list = document.getElementById('userSwitcherList');
        if (list) {
            list.addEventListener('click', function (e) { self.handleUserClick(e); });
        }

        var addBtn = document.getElementById('addNewUserBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () { self.view.showAddForm(); });
        }

        var submitBtn = document.getElementById('submitNewUser');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () { self.handleAddUser(); });
        }
    }

    /**
     * Fetch current user via model.me() and re-render the header indicator.
     *
     * @async
     * @returns {Promise<void>}
     */
    async refresh() {
        var user = await this.model.me();
        this.view.renderCurrentUser(user);
    }

    /**
     * Load the user list and open the switcher overlay.
     *
     * @async
     * @returns {Promise<void>}
     */
    async openSwitcher() {
        var users = await this.model.getAll();
        this.view.render(users);
        this.view.clearError();
        this.view.hideAddForm();
        this.view.showOverlay();
    }

    /**
     * Handle a click on a user-switcher-btn inside the list.
     * Reads data-user-id from the closest [data-user-id] ancestor.
     * Calls model.login(userId), then model.me(), updates header, closes overlay,
     * emits AppEvents.emit('user:changed', { user }).
     *
     * @param {Event} e
     * @returns {void}
     */
    handleUserClick(e) {
        var target = e.target.closest('[data-user-id]');
        if (!target) {
            return;
        }
        var userId = parseInt(target.dataset.userId, 10);
        var self   = this;
        this.model.login(userId).then(function (user) {
            self.view.renderCurrentUser(user);
            self.view.hideOverlay();
            AppEvents.emit('user:changed', { user: user });
        }).catch(function (err) {
            self.view.setError(err.message || 'Login failed.');
        });
    }

    /**
     * Handle submission of the add-user form.
     * Reads #newUserName and #newUserAdmin.
     * Role defaults to 'resident'. show_admin_ui from checkbox.
     * Calls model.create(...), re-renders list, hides form, clears error.
     * Shows error via view.setError() on failure.
     *
     * @async
     * @returns {Promise<void>}
     */
    async handleAddUser() {
        var nameInput  = document.getElementById('newUserName');
        var adminInput = document.getElementById('newUserAdmin');
        var name       = nameInput ? nameInput.value.trim() : '';
        var showAdmin  = adminInput ? adminInput.checked : false;

        if (!name) {
            this.view.setError('Name is required.');
            return;
        }

        try {
            await this.model.create(name, 'resident', null, showAdmin);
            var users = await this.model.getAll();
            this.view.render(users);
            this.view.hideAddForm();
            this.view.clearError();
            if (nameInput) { nameInput.value = ''; }
            if (adminInput) { adminInput.checked = false; }
        } catch (err) {
            this.view.setError(err.message || 'Could not create user.');
        }
    }
}

if (typeof module !== 'undefined') { module.exports = UserController; }
