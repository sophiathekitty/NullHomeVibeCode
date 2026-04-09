/**
 * NullHome — Service Logs viewer.
 *
 * Three-panel client-side navigation:
 *   1. Service list  (#service-list)
 *   2. Run list      (#log-run-list)
 *   3. Log detail    (#log-detail)
 *
 * All data is fetched via fetch(). No page reloads or window.location changes.
 */

/**
 * Fetches all services with their last run summary and renders service cards.
 *
 * @async
 * @returns {Promise<void>}
 */
async function loadServices() {
    var res  = await fetch('/api/service-logs');
    var json = await res.json();
    if (json.success) {
        renderServiceCards(json.data);
    }
}

/**
 * Renders an array of service objects as cards in #service-list.
 * Each card shows: service name, last run time (or "Never"), error/warn badge
 * if counts > 0. Clicking a card calls loadRuns(serviceId, serviceName).
 *
 * @param {Array<Object>} services - Array of service objects from the API.
 * @returns {void}
 */
function renderServiceCards(services) {
    var container = document.getElementById('service-list');
    container.innerHTML = '';

    services.forEach(function (svc) {
        var card = document.createElement('div');
        card.className = 'service-card';

        var nameEl = document.createElement('div');
        nameEl.className = 'service-card-name';
        nameEl.textContent = svc.name;

        var metaEl = document.createElement('div');
        metaEl.className = 'service-card-meta';

        var lastRun = svc.last_run;
        var timeSpan = document.createElement('span');
        timeSpan.textContent = lastRun ? lastRun.started_at : 'Never';
        metaEl.appendChild(timeSpan);

        if (lastRun) {
            if (lastRun.error_count > 0) {
                var errBadge = document.createElement('span');
                errBadge.className = 'badge-error';
                errBadge.textContent = lastRun.error_count + ' error' + (lastRun.error_count !== 1 ? 's' : '');
                metaEl.appendChild(errBadge);
            }
            if (lastRun.warn_count > 0) {
                var warnBadge = document.createElement('span');
                warnBadge.className = 'badge-warn';
                warnBadge.textContent = lastRun.warn_count + ' warning' + (lastRun.warn_count !== 1 ? 's' : '');
                metaEl.appendChild(warnBadge);
            }
        }

        card.appendChild(nameEl);
        card.appendChild(metaEl);

        card.addEventListener('click', function () {
            loadRuns(svc.id, svc.name);
        });

        container.appendChild(card);
    });
}

/**
 * Fetches the 20 most recent runs for a service and renders the run list panel.
 * Hides #service-list and shows #log-run-list.
 *
 * @async
 * @param {number} serviceId   - The service ID.
 * @param {string} serviceName - The service name (for the heading).
 * @returns {Promise<void>}
 */
async function loadRuns(serviceId, serviceName) {
    var res  = await fetch('/api/service-logs/' + serviceId);
    var json = await res.json();
    if (json.success) {
        document.getElementById('service-name-heading').textContent = serviceName;
        renderRunList(json.data, serviceId);
        showRunList();
    }
}

/**
 * Formats a duration in seconds as a human-readable string.
 *
 * Returns "Xs" for under 60 seconds, "Xm Ys" for 60 seconds or more,
 * or "running…" when completed_at is null.
 *
 * @param {string|null} startedAt   - ISO-like datetime string.
 * @param {string|null} completedAt - ISO-like datetime string, or null if still running.
 * @returns {string}
 */
function formatDuration(startedAt, completedAt) {
    if (!completedAt) {
        return 'running\u2026';
    }
    var secs = Math.round((new Date(completedAt) - new Date(startedAt)) / 1000);
    if (secs < 60) {
        return secs + 's';
    }
    var m = Math.floor(secs / 60);
    var s = secs % 60;
    return m + 'm ' + s + 's';
}

/**
 * Renders an array of run rows in #run-list-container.
 * Each row shows: started_at, duration (completed_at - started_at, or "running…"
 * if null), error count, warn count. Clicking a row calls
 * loadLogDetail(serviceId, logId).
 *
 * @param {Array<Object>} runs      - Array of run objects from the API.
 * @param {number}        serviceId - The service ID (passed through to loadLogDetail).
 * @returns {void}
 */
function renderRunList(runs, serviceId) {
    var container = document.getElementById('run-list-container');
    container.innerHTML = '';

    runs.forEach(function (run) {
        var row = document.createElement('div');
        row.className = 'run-row';

        var timeEl = document.createElement('span');
        timeEl.className = 'run-row-time';
        timeEl.textContent = run.started_at;

        var durEl = document.createElement('span');
        durEl.className = 'run-row-duration';
        durEl.textContent = formatDuration(run.started_at, run.completed_at);

        var badgesEl = document.createElement('span');
        badgesEl.className = 'run-row-badges';

        if (run.error_count > 0) {
            var errBadge = document.createElement('span');
            errBadge.className = 'badge-error';
            errBadge.textContent = run.error_count + ' error' + (run.error_count !== 1 ? 's' : '');
            badgesEl.appendChild(errBadge);
        }
        if (run.warn_count > 0) {
            var warnBadge = document.createElement('span');
            warnBadge.className = 'badge-warn';
            warnBadge.textContent = run.warn_count + ' warning' + (run.warn_count !== 1 ? 's' : '');
            badgesEl.appendChild(warnBadge);
        }

        row.appendChild(timeEl);
        row.appendChild(durEl);
        row.appendChild(badgesEl);

        row.addEventListener('click', function () {
            loadLogDetail(serviceId, run.id);
        });

        container.appendChild(row);
    });
}

/**
 * Fetches the full log detail for a single run and renders the detail panel.
 * Hides #log-run-list and shows #log-detail.
 *
 * @async
 * @param {number} serviceId - The service ID.
 * @param {number} logId     - The service_log row ID.
 * @returns {Promise<void>}
 */
async function loadLogDetail(serviceId, logId) {
    var res  = await fetch('/api/service-logs/' + serviceId + '/' + logId);
    var json = await res.json();
    if (json.success) {
        document.getElementById('run-heading').textContent = json.data.started_at;
        renderLogEntries(json.data.entries);
        showLogDetail();
    }
}

/**
 * Renders parsed log entries in #log-entries-container.
 * Each entry is a row with time, a level badge (styled by level), and message.
 * LOG entries use a neutral style. WARN entries use a warning style.
 * ERROR entries use an error style.
 *
 * @param {Array<Object>} entries - Parsed log entry objects from the API.
 * @returns {void}
 */
function renderLogEntries(entries) {
    var container = document.getElementById('log-entries-container');
    container.innerHTML = '';

    entries.forEach(function (entry) {
        var row = document.createElement('div');
        row.className = 'log-entry';

        var timeEl = document.createElement('span');
        timeEl.className = 'log-entry-time';
        timeEl.textContent = entry.time || '';

        var levelEl = document.createElement('span');
        var levelClass = 'level-log';
        if (entry.level === 'WARN') {
            levelClass = 'level-warn';
        } else if (entry.level === 'ERROR') {
            levelClass = 'level-error';
        }
        levelEl.className = 'log-entry-level ' + levelClass;
        levelEl.textContent = entry.level;

        var msgEl = document.createElement('span');
        msgEl.className = 'log-entry-message';
        msgEl.textContent = entry.message;

        row.appendChild(timeEl);
        row.appendChild(levelEl);
        row.appendChild(msgEl);

        container.appendChild(row);
    });
}

/**
 * Shows the services list panel and hides all other panels.
 *
 * @returns {void}
 */
function showServiceList() {
    document.getElementById('service-list').classList.remove('hidden');
    document.getElementById('log-run-list').classList.add('hidden');
    document.getElementById('log-detail').classList.add('hidden');
}

/**
 * Shows the run list panel and hides all other panels.
 *
 * @returns {void}
 */
function showRunList() {
    document.getElementById('service-list').classList.add('hidden');
    document.getElementById('log-run-list').classList.remove('hidden');
    document.getElementById('log-detail').classList.add('hidden');
}

/**
 * Shows the log detail panel and hides all other panels.
 *
 * @returns {void}
 */
function showLogDetail() {
    document.getElementById('service-list').classList.add('hidden');
    document.getElementById('log-run-list').classList.add('hidden');
    document.getElementById('log-detail').classList.remove('hidden');
}

/**
 * Initialises the Service Logs page.
 *
 * Wires up the #openServiceLogs drawer button to show the service-logs page
 * (hiding the main rooms container), wires the back-navigation buttons, and
 * calls loadServices() when the page is first opened.
 *
 * @returns {void}
 */
function initServiceLogs() {
    var openBtn     = document.getElementById('openServiceLogs');
    var page        = document.getElementById('service-logs-page');
    var roomsMain   = document.getElementById('roomsContainer');
    var backSvcBtn  = document.getElementById('back-to-services');
    var backRunsBtn = document.getElementById('back-to-runs');

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            var drawer        = document.getElementById('drawer');
            var drawerOverlay = document.getElementById('drawerOverlay');
            if (drawer)        { drawer.classList.remove('is-open'); }
            if (drawerOverlay) { drawerOverlay.classList.remove('is-visible'); }

            if (roomsMain) { roomsMain.classList.add('hidden'); }
            if (page)      { page.classList.remove('hidden'); }

            showServiceList();
            loadServices();
        });
    }

    if (backSvcBtn) {
        backSvcBtn.addEventListener('click', showServiceList);
    }

    if (backRunsBtn) {
        backRunsBtn.addEventListener('click', showRunList);
    }
}

if (typeof module !== 'undefined') {
    module.exports = {
        loadServices:      loadServices,
        renderServiceCards: renderServiceCards,
        loadRuns:          loadRuns,
        renderRunList:     renderRunList,
        loadLogDetail:     loadLogDetail,
        renderLogEntries:  renderLogEntries,
        showServiceList:   showServiceList,
        showRunList:       showRunList,
        showLogDetail:     showLogDetail,
        formatDuration:    formatDuration,
        initServiceLogs:   initServiceLogs,
    };
}
