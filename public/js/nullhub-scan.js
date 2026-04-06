/**
 * NullHome — NullHub scan UI.
 *
 * Drives the frontend side of the incremental NullHub scan loop:
 *   1. POST /api/scan/reset           — resets and populates the nmap scan queue.
 *   2. POST /api/scan/next/nullhub    — called in a loop, one step per call,
 *      until the server returns `done: true`.
 *
 * The overlay shows:
 *   - A progress bar (filled based on checked / total IPs).
 *   - A live list of NullHub devices found during the scan.
 *   - A Cancel button (halts the loop mid-scan).
 *   - A Close button (appears when the scan is finished or cancelled).
 */

/**
 * Initialise the NullHub scan overlay.
 *
 * Binds the "Scan for NullHubs" drawer button to open the overlay and wires
 * up the Start, Cancel, and Close buttons inside the overlay.
 *
 * @returns {void}
 */
function initNullHubScan() {
    var overlay      = document.getElementById('nullhubScanOverlay');
    var statusEl     = document.getElementById('nullhubScanStatus');
    var progressWrap = document.getElementById('nullhubScanProgressWrap');
    var progressFill = document.getElementById('nullhubScanProgressFill');
    var progressLbl  = document.getElementById('nullhubScanProgressLabel');
    var foundList    = document.getElementById('nullhubScanFoundList');
    var btnStart     = document.getElementById('nullhubScanStart');
    var btnCancel    = document.getElementById('nullhubScanCancel');
    var btnClose     = document.getElementById('nullhubScanClose');
    var openBtn      = document.getElementById('openNullHubScan');

    /** @type {boolean} Set to true when the user clicks Cancel. */
    var cancelled = false;

    /** @type {number} Total IPs queued at scan start (for progress bar). */
    var totalQueued = 0;

    /**
     * Open the scan overlay and reset its state to the idle/ready appearance.
     *
     * @returns {void}
     */
    function openOverlay() {
        cancelled = false;
        totalQueued = 0;

        statusEl.textContent = 'Ready to scan';
        progressWrap.hidden  = true;
        progressFill.style.width = '0%';
        progressLbl.textContent  = '';
        foundList.hidden     = true;
        foundList.innerHTML  = '';

        btnStart.hidden  = false;
        btnCancel.hidden = true;
        btnClose.hidden  = true;

        overlay.hidden = false;
    }

    /**
     * Close the scan overlay.
     *
     * @returns {void}
     */
    function closeOverlay() {
        cancelled      = true;
        overlay.hidden = true;
    }

    /**
     * Update the progress bar and label.
     *
     * @param {number} remaining - Number of IPs still to check.
     * @returns {void}
     */
    function updateProgress(remaining) {
        if (totalQueued === 0) {
            progressFill.style.width = '100%';
            progressLbl.textContent  = 'Done';
            return;
        }
        var checked = totalQueued - remaining;
        var pct     = Math.round((checked / totalQueued) * 100);
        progressFill.style.width = pct + '%';
        progressLbl.textContent  = checked + ' / ' + totalQueued + ' checked';
    }

    /**
     * Append a found NullHub entry to the results list.
     *
     * @param {string} ip   - The IP address of the found device.
     * @param {string} name - The friendly name of the device.
     * @returns {void}
     */
    function addFoundNullHub(ip, name) {
        foundList.hidden = false;
        var li = document.createElement('li');
        li.className = 'nullhub-scan-found-item';
        var nameSpan = document.createElement('span');
        nameSpan.className = 'nullhub-scan-found-name';
        nameSpan.textContent = name;
        var ipSpan = document.createElement('span');
        ipSpan.className = 'nullhub-scan-found-ip';
        ipSpan.textContent = ip;
        li.appendChild(nameSpan);
        li.appendChild(ipSpan);
        foundList.appendChild(li);
    }

    /**
     * Run one step of the scan loop by calling POST /api/scan/next/nullhub.
     *
     * Schedules itself recursively (via setTimeout) until done or cancelled.
     *
     * @async
     * @returns {Promise<void>}
     */
    async function runNextStep() {
        if (cancelled) {
            statusEl.textContent = 'Scan cancelled.';
            btnCancel.hidden = true;
            btnClose.hidden  = false;
            return;
        }

        try {
            var resp = await fetch('/api/scan/next/nullhub', { method: 'POST' });
            var envelope = await resp.json();

            if (!resp.ok || envelope.error) {
                throw new Error(envelope.error || 'Server error: ' + resp.status);
            }

            var data = envelope.data || {};

            if (data.done) {
                updateProgress(0);
                statusEl.textContent = 'Scan complete.';
                btnCancel.hidden = true;
                btnClose.hidden  = false;
                return;
            }

            updateProgress(data.remaining);

            if (data.result === 'found_nullhub' || data.result === 'known_nullhub') {
                addFoundNullHub(data.ip, data.name || data.ip);
            }

            // Continue loop.
            setTimeout(runNextStep, 0);

        } catch (err) {
            statusEl.textContent = 'Error: ' + err.message;
            btnCancel.hidden = true;
            btnClose.hidden  = false;
        }
    }

    /**
     * Start the full scan: reset the queue, then drive the step loop.
     *
     * @async
     * @returns {Promise<void>}
     */
    async function startScan() {
        cancelled = false;

        btnStart.hidden  = true;
        btnCancel.hidden = false;
        btnClose.hidden  = true;

        statusEl.textContent     = 'Starting scan\u2026';
        progressWrap.hidden      = false;
        progressFill.style.width = '0%';
        progressLbl.textContent  = '';
        foundList.hidden         = true;
        foundList.innerHTML      = '';

        try {
            var resp = await fetch('/api/scan/reset', { method: 'POST' });
            var envelope = await resp.json();

            if (!resp.ok || envelope.error) {
                throw new Error(envelope.error || 'Server error: ' + resp.status);
            }

            var data = envelope.data || {};
            totalQueued = data.queued || 0;

            if (totalQueued === 0) {
                statusEl.textContent     = 'No hosts found on network.';
                progressFill.style.width = '100%';
                progressLbl.textContent  = '0 / 0 checked';
                btnCancel.hidden = true;
                btnClose.hidden  = false;
                return;
            }

            statusEl.textContent    = 'Scanning ' + totalQueued + ' host(s)\u2026';
            progressFill.style.width = '0%';
            progressLbl.textContent  = '0 / ' + totalQueued + ' checked';

            runNextStep();

        } catch (err) {
            statusEl.textContent = 'Error: ' + err.message;
            btnCancel.hidden = true;
            btnClose.hidden  = false;
        }
    }

    // ── Event bindings ─────────────────────────────────────────────────────────

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            // Close the drawer first.
            var drawer        = document.getElementById('drawer');
            var drawerOverlay = document.getElementById('drawerOverlay');
            if (drawer)        { drawer.classList.remove('is-open'); }
            if (drawerOverlay) { drawerOverlay.classList.remove('is-visible'); }

            openOverlay();
        });
    }

    btnStart.addEventListener('click', startScan);

    btnCancel.addEventListener('click', function () {
        cancelled = true;
    });

    btnClose.addEventListener('click', closeOverlay);
}

if (typeof module !== 'undefined') { module.exports = { initNullHubScan: initNullHubScan }; }
