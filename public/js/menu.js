/**
 * NullHome — hamburger menu drawer open/close behaviour.
 */

/**
 * Initialises hamburger menu drawer open/close behaviour.
 *
 * Binds click handlers to #menuToggle (open), #drawerClose (close),
 * and #drawerOverlay (close). Toggles the `is-open` class on .drawer
 * and the `is-visible` class on .drawer-overlay.
 *
 * @returns {void}
 */
function initMenu() {
    var drawer        = document.getElementById('drawer');
    var drawerOverlay = document.getElementById('drawerOverlay');
    var menuToggle    = document.getElementById('menuToggle');
    var drawerClose   = document.getElementById('drawerClose');

    /**
     * Opens the slide-in drawer.
     *
     * @returns {void}
     */
    function openDrawer() {
        drawer.classList.add('is-open');
        drawerOverlay.classList.add('is-visible');
    }

    /**
     * Closes the slide-in drawer.
     *
     * @returns {void}
     */
    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawerOverlay.classList.remove('is-visible');
    }

    menuToggle.addEventListener('click', openDrawer);
    drawerClose.addEventListener('click', closeDrawer);
    drawerOverlay.addEventListener('click', closeDrawer);
}

if (typeof module !== 'undefined') { module.exports = { initMenu: initMenu }; }
