/**
 * Tests for service-logs.js — three-panel navigation and rendering functions.
 */

'use strict';

var mod = require('../../public/js/service-logs.js');

var loadServices      = mod.loadServices;
var renderServiceCards = mod.renderServiceCards;
var loadRuns          = mod.loadRuns;
var renderRunList     = mod.renderRunList;
var loadLogDetail     = mod.loadLogDetail;
var renderLogEntries  = mod.renderLogEntries;
var showServiceList   = mod.showServiceList;
var showRunList       = mod.showRunList;
var showLogDetail     = mod.showLogDetail;
var formatDuration    = mod.formatDuration;
var initServiceLogs   = mod.initServiceLogs;

function buildDom() {
    document.body.innerHTML = [
        '<div id="roomsContainer"></div>',
        '<div id="service-logs-page" class="page hidden">',
        '  <div id="service-list"></div>',
        '  <div id="log-run-list" class="hidden">',
        '    <button id="back-to-services">← Services</button>',
        '    <h2 id="service-name-heading"></h2>',
        '    <div id="run-list-container"></div>',
        '  </div>',
        '  <div id="log-detail" class="hidden">',
        '    <button id="back-to-runs">← Runs</button>',
        '    <h2 id="run-heading"></h2>',
        '    <div id="log-entries-container"></div>',
        '  </div>',
        '</div>',
        '<div id="drawer"></div>',
        '<div id="drawerOverlay"></div>',
        '<ul class="drawer-menu">',
        '  <li><button id="openServiceLogs">Service Logs</button></li>',
        '</ul>',
    ].join('');
}

beforeEach(function () {
    buildDom();
    global.fetch = jest.fn();
});

afterEach(function () {
    jest.clearAllMocks();
});

// ── formatDuration ────────────────────────────────────────────────────────────

describe('formatDuration', function () {
    test('returns "running…" when completedAt is null', function () {
        expect(formatDuration('2025-04-01 15:02:01', null)).toBe('running\u2026');
    });

    test('returns seconds for durations under 60s', function () {
        expect(formatDuration('2025-04-01 15:02:01', '2025-04-01 15:02:16')).toBe('15s');
    });

    test('returns minutes and seconds for durations 60s or more', function () {
        expect(formatDuration('2025-04-01 15:02:00', '2025-04-01 15:03:05')).toBe('1m 5s');
    });

    test('returns "0s" for zero-length runs', function () {
        expect(formatDuration('2025-04-01 15:02:00', '2025-04-01 15:02:00')).toBe('0s');
    });
});

// ── showServiceList / showRunList / showLogDetail ─────────────────────────────

describe('panel visibility helpers', function () {
    test('showServiceList reveals service-list and hides other panels', function () {
        showServiceList();
        expect(document.getElementById('service-list').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(true);
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(true);
    });

    test('showRunList reveals log-run-list and hides other panels', function () {
        showRunList();
        expect(document.getElementById('service-list').classList.contains('hidden')).toBe(true);
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(true);
    });

    test('showLogDetail reveals log-detail and hides other panels', function () {
        showLogDetail();
        expect(document.getElementById('service-list').classList.contains('hidden')).toBe(true);
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(true);
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(false);
    });
});

// ── renderServiceCards ────────────────────────────────────────────────────────

describe('renderServiceCards', function () {
    test('renders one card per service', function () {
        renderServiceCards([
            { id: 1, name: 'every_minute', last_run: null },
            { id: 2, name: 'every_hour',   last_run: null },
        ]);
        var cards = document.querySelectorAll('.service-card');
        expect(cards.length).toBe(2);
    });

    test('shows service name in card', function () {
        renderServiceCards([{ id: 1, name: 'every_minute', last_run: null }]);
        expect(document.querySelector('.service-card-name').textContent).toBe('every_minute');
    });

    test('shows "Never" when last_run is null', function () {
        renderServiceCards([{ id: 1, name: 'every_minute', last_run: null }]);
        expect(document.querySelector('.service-card-meta').textContent).toContain('Never');
    });

    test('shows last run time when last_run is set', function () {
        renderServiceCards([{
            id: 1,
            name: 'every_minute',
            last_run: { id: 42, started_at: '2025-04-01 15:02:01', completed_at: null, error_count: 0, warn_count: 0 },
        }]);
        expect(document.querySelector('.service-card-meta').textContent).toContain('2025-04-01 15:02:01');
    });

    test('renders error badge when error_count > 0', function () {
        renderServiceCards([{
            id: 1,
            name: 'svc',
            last_run: { id: 1, started_at: '2025-01-01', completed_at: null, error_count: 3, warn_count: 0 },
        }]);
        expect(document.querySelector('.badge-error')).not.toBeNull();
    });

    test('does not render error badge when error_count is 0', function () {
        renderServiceCards([{
            id: 1,
            name: 'svc',
            last_run: { id: 1, started_at: '2025-01-01', completed_at: null, error_count: 0, warn_count: 0 },
        }]);
        expect(document.querySelector('.badge-error')).toBeNull();
    });

    test('renders warn badge when warn_count > 0', function () {
        renderServiceCards([{
            id: 1,
            name: 'svc',
            last_run: { id: 1, started_at: '2025-01-01', completed_at: null, error_count: 0, warn_count: 2 },
        }]);
        expect(document.querySelector('.badge-warn')).not.toBeNull();
    });

    test('does not render warn badge when warn_count is 0', function () {
        renderServiceCards([{
            id: 1,
            name: 'svc',
            last_run: { id: 1, started_at: '2025-01-01', completed_at: null, error_count: 0, warn_count: 0 },
        }]);
        expect(document.querySelector('.badge-warn')).toBeNull();
    });
});

// ── renderRunList ─────────────────────────────────────────────────────────────

describe('renderRunList', function () {
    var runs = [
        { id: 10, service_id: 1, started_at: '2025-04-01 15:02:01', completed_at: '2025-04-01 15:02:16', error_count: 0, warn_count: 0 },
        { id: 11, service_id: 1, started_at: '2025-04-01 14:00:00', completed_at: null,                   error_count: 1, warn_count: 0 },
    ];

    test('renders one row per run', function () {
        renderRunList(runs, 1);
        expect(document.querySelectorAll('.run-row').length).toBe(2);
    });

    test('shows started_at time', function () {
        renderRunList(runs, 1);
        expect(document.querySelector('.run-row-time').textContent).toBe('2025-04-01 15:02:01');
    });

    test('shows duration for completed run', function () {
        renderRunList(runs, 1);
        expect(document.querySelectorAll('.run-row-duration')[0].textContent).toBe('15s');
    });

    test('shows "running…" for incomplete run', function () {
        renderRunList(runs, 1);
        expect(document.querySelectorAll('.run-row-duration')[1].textContent).toBe('running\u2026');
    });

    test('renders error badge when error_count > 0', function () {
        renderRunList(runs, 1);
        expect(document.querySelector('.badge-error')).not.toBeNull();
    });
});

// ── renderLogEntries ──────────────────────────────────────────────────────────

describe('renderLogEntries', function () {
    var entries = [
        { time: '15:02:01', level: 'LOG',   message: 'started' },
        { time: '15:02:07', level: 'WARN',  message: 'warned' },
        { time: '15:02:15', level: 'ERROR', message: 'failed' },
    ];

    test('renders one row per entry', function () {
        renderLogEntries(entries);
        expect(document.querySelectorAll('.log-entry').length).toBe(3);
    });

    test('applies level-log class to LOG entries', function () {
        renderLogEntries(entries);
        var levelEls = document.querySelectorAll('.log-entry-level');
        expect(levelEls[0].classList.contains('level-log')).toBe(true);
    });

    test('applies level-warn class to WARN entries', function () {
        renderLogEntries(entries);
        var levelEls = document.querySelectorAll('.log-entry-level');
        expect(levelEls[1].classList.contains('level-warn')).toBe(true);
    });

    test('applies level-error class to ERROR entries', function () {
        renderLogEntries(entries);
        var levelEls = document.querySelectorAll('.log-entry-level');
        expect(levelEls[2].classList.contains('level-error')).toBe(true);
    });

    test('renders message text', function () {
        renderLogEntries(entries);
        expect(document.querySelectorAll('.log-entry-message')[0].textContent).toBe('started');
    });

    test('renders null time as empty string', function () {
        renderLogEntries([{ time: null, level: 'LOG', message: 'raw line' }]);
        expect(document.querySelector('.log-entry-time').textContent).toBe('');
    });
});

// ── loadServices ──────────────────────────────────────────────────────────────

describe('loadServices', function () {
    test('fetches /api/service-logs and calls renderServiceCards', async function () {
        global.fetch.mockResolvedValue({
            json: function () {
                return Promise.resolve({
                    success: true,
                    data: [{ id: 1, name: 'every_minute', retention_days: 1, last_run: null }],
                });
            },
        });

        await loadServices();

        expect(global.fetch).toHaveBeenCalledWith('/api/service-logs');
        expect(document.querySelectorAll('.service-card').length).toBe(1);
    });
});

// ── loadRuns ──────────────────────────────────────────────────────────────────

describe('loadRuns', function () {
    test('fetches /api/service-logs/{id} and renders run list', async function () {
        global.fetch.mockResolvedValue({
            json: function () {
                return Promise.resolve({
                    success: true,
                    data: [
                        { id: 5, service_id: 1, started_at: '2025-04-01 15:00:00', completed_at: '2025-04-01 15:00:10', error_count: 0, warn_count: 0 },
                    ],
                });
            },
        });

        await loadRuns(1, 'every_minute');

        expect(global.fetch).toHaveBeenCalledWith('/api/service-logs/1');
        expect(document.querySelectorAll('.run-row').length).toBe(1);
        expect(document.getElementById('service-name-heading').textContent).toBe('every_minute');
        // showRunList() should have been called
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(false);
    });
});

// ── loadLogDetail ─────────────────────────────────────────────────────────────

describe('loadLogDetail', function () {
    test('fetches /api/service-logs/{sid}/{lid} and renders log entries', async function () {
        global.fetch.mockResolvedValue({
            json: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        id: 42,
                        service_id: 1,
                        started_at: '2025-04-01 15:02:01',
                        completed_at: '2025-04-01 15:02:16',
                        error_count: 1,
                        warn_count: 0,
                        entries: [
                            { time: '15:02:01', level: 'LOG',   message: 'started' },
                            { time: '15:02:15', level: 'ERROR', message: 'failed'  },
                        ],
                    },
                });
            },
        });

        await loadLogDetail(1, 42);

        expect(global.fetch).toHaveBeenCalledWith('/api/service-logs/1/42');
        expect(document.querySelectorAll('.log-entry').length).toBe(2);
        expect(document.getElementById('run-heading').textContent).toBe('2025-04-01 15:02:01');
        // showLogDetail() should have been called
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(false);
    });
});

// ── initServiceLogs ───────────────────────────────────────────────────────────

describe('initServiceLogs', function () {
    test('clicking openServiceLogs shows the service-logs page', async function () {
        global.fetch.mockResolvedValue({
            json: function () { return Promise.resolve({ success: true, data: [] }); },
        });

        initServiceLogs();
        document.getElementById('openServiceLogs').click();

        await Promise.resolve();

        expect(document.getElementById('service-logs-page').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('roomsContainer').classList.contains('hidden')).toBe(true);
    });

    test('clicking back-to-services calls showServiceList', function () {
        initServiceLogs();
        // put the run list in view first
        showRunList();
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(false);

        document.getElementById('back-to-services').click();
        expect(document.getElementById('service-list').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(true);
    });

    test('clicking back-to-runs calls showRunList', function () {
        initServiceLogs();
        showLogDetail();
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(false);

        document.getElementById('back-to-runs').click();
        expect(document.getElementById('log-run-list').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('log-detail').classList.contains('hidden')).toBe(true);
    });
});
