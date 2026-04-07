/**
 * Tests for nullhub-scan.js — NullHub scan overlay state flows and scan loop.
 */

'use strict';

var initNullHubScan = require('../../public/js/nullhub-scan.js').initNullHubScan;

function buildDom() {
    document.body.innerHTML = [
        '<button id="openNullHubScan"></button>',
        '<div id="nullhubScanOverlay" hidden></div>',
        '<p id="nullhubScanStatus"></p>',
        '<div id="nullhubScanProgressWrap" hidden></div>',
        '<div id="nullhubScanProgressFill" style="width:0%"></div>',
        '<span id="nullhubScanProgressLabel"></span>',
        '<ul id="nullhubScanFoundList" hidden></ul>',
        '<button id="nullhubScanStart"></button>',
        '<button id="nullhubScanCancel" hidden></button>',
        '<button id="nullhubScanClose" hidden></button>',
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

describe('initNullHubScan — overlay open', function () {
    test('clicking openNullHubScan shows the overlay', function () {
        initNullHubScan();
        document.getElementById('openNullHubScan').click();
        expect(document.getElementById('nullhubScanOverlay').hidden).toBe(false);
    });

    test('overlay opens with "Ready to scan" status', function () {
        initNullHubScan();
        document.getElementById('openNullHubScan').click();
        expect(document.getElementById('nullhubScanStatus').textContent).toBe('Ready to scan');
    });

    test('overlay shows Start button and hides Cancel and Close', function () {
        initNullHubScan();
        document.getElementById('openNullHubScan').click();
        expect(document.getElementById('nullhubScanStart').hidden).toBe(false);
        expect(document.getElementById('nullhubScanCancel').hidden).toBe(true);
        expect(document.getElementById('nullhubScanClose').hidden).toBe(true);
    });

    test('clicking openNullHubScan closes the drawer', function () {
        initNullHubScan();
        document.getElementById('drawer').classList.add('is-open');
        document.getElementById('drawerOverlay').classList.add('is-visible');
        document.getElementById('openNullHubScan').click();
        expect(document.getElementById('drawer').classList.contains('is-open')).toBe(false);
        expect(document.getElementById('drawerOverlay').classList.contains('is-visible')).toBe(false);
    });
});

describe('initNullHubScan — scan start (reset failure)', function () {
    test('shows error status when reset API call fails', async function () {
        global.fetch.mockResolvedValue({
            ok: false,
            json: function () { return Promise.resolve({ error: 'Reset failed' }); }
        });
        initNullHubScan();
        document.getElementById('nullhubScanStart').click();

        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('nullhubScanStatus').textContent).toContain('Error:');
        expect(document.getElementById('nullhubScanClose').hidden).toBe(false);
    });

    test('shows "No hosts found" when reset returns queued:0', async function () {
        global.fetch.mockResolvedValue({
            ok: true,
            json: function () { return Promise.resolve({ success: true, data: { queued: 0 } }); }
        });
        initNullHubScan();
        document.getElementById('nullhubScanStart').click();

        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('nullhubScanStatus').textContent).toBe('No hosts found on network.');
        expect(document.getElementById('nullhubScanClose').hidden).toBe(false);
    });
});

describe('initNullHubScan — scan complete', function () {
    test('shows "Scan complete." when step returns done:true', async function () {
        global.fetch
            // reset call
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { queued: 1 } }); }
            })
            // scan/next/nullhub call
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { done: true } }); }
            });

        initNullHubScan();
        document.getElementById('nullhubScanStart').click();

        // Resolve reset fetch and setTimeout(runNextStep, 0)
        await Promise.resolve();
        await Promise.resolve();
        jest.runAllTimers();
        await Promise.resolve();
        await Promise.resolve();

        expect(document.getElementById('nullhubScanStatus').textContent).toBe('Scan complete.');
        expect(document.getElementById('nullhubScanClose').hidden).toBe(false);
    });

    test('found NullHub is appended to the list', async function () {
        global.fetch
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { queued: 1 } }); }
            })
            .mockResolvedValueOnce({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { done: false, remaining: 0, result: 'found_nullhub', ip: '192.168.86.90', name: 'null pi' }
                    });
                }
            })
            .mockResolvedValueOnce({
                ok: true,
                json: function () { return Promise.resolve({ success: true, data: { done: true } }); }
            });

        initNullHubScan();
        document.getElementById('nullhubScanStart').click();

        await Promise.resolve();
        await Promise.resolve();
        jest.runAllTimers();
        await Promise.resolve();
        await Promise.resolve();
        jest.runAllTimers();
        await Promise.resolve();
        await Promise.resolve();

        var list = document.getElementById('nullhubScanFoundList');
        expect(list.hidden).toBe(false);
        expect(list.querySelectorAll('li').length).toBe(1);
        expect(list.querySelector('.nullhub-scan-found-name').textContent).toBe('null pi');
        expect(list.querySelector('.nullhub-scan-found-ip').textContent).toBe('192.168.86.90');
    });
});

describe('initNullHubScan — cancel', function () {
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

        initNullHubScan();
        document.getElementById('nullhubScanStart').click();

        // Resolve the reset fetch
        await Promise.resolve();
        await Promise.resolve();

        // Cancel before the step fetch resolves
        document.getElementById('nullhubScanCancel').click();

        // Resolve the scan step fetch
        await Promise.resolve();
        await Promise.resolve();

        // Fire the next runNextStep — cancelled is now true
        jest.runAllTimers();

        expect(document.getElementById('nullhubScanStatus').textContent).toBe('Scan cancelled.');
        expect(document.getElementById('nullhubScanClose').hidden).toBe(false);
    });
});

describe('initNullHubScan — close', function () {
    test('clicking Close hides the overlay', function () {
        initNullHubScan();
        document.getElementById('nullhubScanOverlay').hidden = false;
        document.getElementById('nullhubScanClose').hidden = false;
        document.getElementById('nullhubScanClose').click();
        expect(document.getElementById('nullhubScanOverlay').hidden).toBe(true);
    });
});
