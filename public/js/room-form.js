/**
 * NullHome — Add Room overlay form submission.
 */

/**
 * Initialises the Add Room overlay and form submission handler.
 *
 * Opens the overlay when #openAddRoom is clicked (and closes the drawer).
 * Validates that both `name` and `display_name` are non-empty before
 * submitting a POST to /api/rooms. On success the overlay is closed, the
 * form is cleared, and rooms are re-fetched via the provided callback. On
 * API error the `error` field from the response is displayed inline inside
 * #addRoomError.
 *
 * @param {function(): void} onSuccess - Called after a room is successfully created; should re-fetch and re-render rooms.
 * @returns {void}
 */
function initRoomForm(onSuccess) {
    var overlay      = document.getElementById('addRoomOverlay');
    var nameInput    = document.getElementById('roomName');
    var displayInput = document.getElementById('roomDisplayName');
    var errorEl      = document.getElementById('addRoomError');
    var openBtn      = document.getElementById('openAddRoom');
    var submitBtn    = document.getElementById('submitAddRoom');
    var cancelBtn    = document.getElementById('cancelAddRoom');
    var drawer       = document.getElementById('drawer');
    var drawerOverlay = document.getElementById('drawerOverlay');

    /**
     * Opens the Add Room overlay and closes the drawer.
     *
     * @returns {void}
     */
    function openOverlay() {
        drawer.classList.remove('is-open');
        drawerOverlay.classList.remove('is-visible');
        overlay.removeAttribute('hidden');
        nameInput.focus();
    }

    /**
     * Closes the Add Room overlay and resets the error message.
     *
     * @returns {void}
     */
    function closeOverlay() {
        overlay.setAttribute('hidden', '');
        errorEl.setAttribute('hidden', '');
        errorEl.textContent = '';
    }

    /**
     * Displays an inline error message below the form.
     *
     * @param {string} message - The error text to display.
     * @returns {void}
     */
    function showError(message) {
        errorEl.textContent = message;
        errorEl.removeAttribute('hidden');
    }

    /**
     * Submits the Add Room form to POST /api/rooms.
     *
     * Validates inputs, sends the request, and handles success or error.
     *
     * @async
     * @returns {Promise<void>}
     */
    async function submitForm() {
        var name        = nameInput.value.trim();
        var displayName = displayInput.value.trim();

        if (!name || !displayName) {
            showError('Both fields are required.');
            return;
        }

        try {
            var response = await fetch('/api/rooms', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, display_name: displayName })
            });

            var data = await response.json();

            if (!response.ok || data.error) {
                showError(data.error || 'Failed to add room.');
                return;
            }

            nameInput.value = '';
            displayInput.value = '';
            closeOverlay();
            onSuccess();
        } catch (_err) {
            showError('Network error. Please try again.');
        }
    }

    openBtn.addEventListener('click', openOverlay);
    cancelBtn.addEventListener('click', closeOverlay);
    submitBtn.addEventListener('click', submitForm);
}
