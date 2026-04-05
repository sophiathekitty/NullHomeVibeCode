/**
 * Tests for wemo-scan.js — Wemo scan overlay state flows and scan loop.
 */

'use strict';

var initWemoScan = require('../../public/js/wemo-scan.js').initWemoScan;

function buildDom() {
    document.body.innerHTML = [
        '<button id="openWemoScan"></button>',
        '<div id="wemoScanOverlay" hidden></div>',
        '<p id="wemoScanStatus"></p>',
        '<div id="wemoScanProgressWrap" hidden></div>',
        '<div id="wemoScanProgressFill" style="width:0%"></div>',
        '<span id="wemoScanProgressLabel"></span>',
        '<ul id="wemoScanFoundList" hidden></ul>',
        '<button id="wemoScanStart"></button>',
        '<button id="wemoScanCancel" hidden></button>',
        '<button id="wemoScanClose" hidden></button>',
        '<div id="drawer"></div>',
        '<div id="drawerOverlay"></div>'
    ].join('');
}

beforeEach(function () {
    buildDom();
    global.fetch = jest.fn();
    jest.useFakeTimers();
});

afterEach(function () {
    jest.useRealTimers();
    jest.clearAllMocks();
});

describe('initWemoScan — overlay open', function () {
    test('clicking openWemoScan shows the overlay', function () {
        initWemoScan();
        document.getElementById('openWemoScan').click();
        expect(document.getElementById('wemoScanOverlay').hidden).toBe(false);
    });

    test('overlay opens with "Ready to scan" status', function () {
        initWemoScan();
        document.getElementById('openWemoScan').click();
        expect(document.getElementById('wemoScanStatus').textContent).toBe('Ready to scan');
    });

    test('overlay shows Start button and hides Cancel and Close', function () {
        initWemoScan();
        document.getElementById('openWemoScan').click();
        expect(document.getElementById('wemoScanStart').hidden).toBe(false);
        expect(document.getElementById('wemoScanCancel').hidden).toBe(true);
        expect(document.getElementById('wemoScanClose').hidden).toBe(true);
    });
});

describe('initWemoScan — scan start (reset failure)', function () {
    test('shows error status when reset API call fails', async function () {
        global.fetch.mockResolvedValue({
            ok: false,
            json: function () { return Promise.resolve({ error: 'Reset failed' }); }
        });
        initWemoScan();
        document.getElementById('wemoScanStart').click();

        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('wemoScanStatus').textContent).toContain('Error:');
        expect(document.getElementById('wemoScanClose').hidden).toBe(false);
    });

    test('shows "No hosts found" when reset returns queued:0', async function () {
        global.fetch.mockResolvedValue({
            ok: true,
            json: function () { return Promise.resolve({ success: true, data: { queued: 0 } }); }
        });
        initWemoScan();
        document.getElementById('wemoScanStart').click();

        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('wemoScanStatus').textContent).toBe('No hosts found on network.');
        expect(document.getElementById('wemoScanClose').hidden).toBe(false);
    });
});

describe('initWemoScan — scan complete', function () {
    test('shows "Scan complete." when step returns done:true', async function () {
        global.fetch
            // reset call
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { queued: 1 } }); }
            })
            // scan/next/wemo call
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { done: true } }); }
            });

        initWemoScan();
        document.getElementById('wemoScanStart').click();

        // Resolve reset fetch and setTimeout(runNextStep, 0)
        await Promise.resolve();
        await Promise.resolve();
        jest.runAllTimers();
        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('wemoScanStatus').textContent).toBe('Scan complete.');
        expect(document.getElementById('wemoScanClose').hidden).toBe(false);
    });
});

describe('initWemoScan — cancel', function () {
    test('clicking Cancel sets status to "Scan cancelled."', async function () {
        global.fetch
            // reset call
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { queued: 5 } }); }
            })
            // first scan step (resolves after cancel is clicked)
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { done: false, remaining: 4 } }); }
            });

        initWemoScan();
        document.getElementById('wemoScanStart').click();

        // Resolve the reset fetch
        await Promise.resolve();
        await Promise.resolve();
        // runNextStep is now awaiting scan/next/wemo

        // Cancel before the step fetch resolves
        document.getElementById('wemoScanCancel').click();

        // Resolve the scan step fetch
        await Promise.resolve();
        await Promise.resolve();
        // runNextStep finishes the step and schedules setTimeout(runNextStep, 0)

        // Fire the next runNextStep — cancelled is now true
        jest.runAllTimers();

        expect(document.getElementById('wemoScanStatus').textContent).toBe('Scan cancelled.');
        expect(document.getElementById('wemoScanClose').hidden).toBe(false);
    });
});

describe('initWemoScan — close', function () {
    test('clicking Close hides the overlay', function () {
        initWemoScan();
        document.getElementById('wemoScanOverlay').hidden = false;
        document.getElementById('wemoScanClose').hidden = false;
        document.getElementById('wemoScanClose').click();
        expect(document.getElementById('wemoScanOverlay').hidden).toBe(true);
    });
});
