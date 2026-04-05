/**
 * Tests for menu.js — hamburger drawer open/close behaviour.
 */

'use strict';

var initMenu = require('../../public/js/menu.js').initMenu;

function buildDom() {
    document.body.innerHTML = [
        '<div id="drawer"></div>',
        '<div id="drawerOverlay"></div>',
        '<button id="menuToggle"></button>',
        '<button id="drawerClose"></button>'
    ].join('');
}

beforeEach(buildDom);

describe('initMenu — open drawer', function () {
    test('clicking menuToggle adds is-open to #drawer', function () {
        initMenu();
        document.getElementById('menuToggle').click();
        expect(document.getElementById('drawer').classList.contains('is-open')).toBe(true);
    });

    test('clicking menuToggle adds is-visible to #drawerOverlay', function () {
        initMenu();
        document.getElementById('menuToggle').click();
        expect(document.getElementById('drawerOverlay').classList.contains('is-visible')).toBe(true);
    });
});

describe('initMenu — close drawer', function () {
    test('clicking drawerClose removes is-open from #drawer', function () {
        initMenu();
        document.getElementById('drawer').classList.add('is-open');
        document.getElementById('drawerClose').click();
        expect(document.getElementById('drawer').classList.contains('is-open')).toBe(false);
    });

    test('clicking drawerClose removes is-visible from #drawerOverlay', function () {
        initMenu();
        document.getElementById('drawerOverlay').classList.add('is-visible');
        document.getElementById('drawerClose').click();
        expect(document.getElementById('drawerOverlay').classList.contains('is-visible')).toBe(false);
    });

    test('clicking the drawerOverlay closes the drawer', function () {
        initMenu();
        document.getElementById('drawer').classList.add('is-open');
        document.getElementById('drawerOverlay').classList.add('is-visible');
        document.getElementById('drawerOverlay').click();
        expect(document.getElementById('drawer').classList.contains('is-open')).toBe(false);
        expect(document.getElementById('drawerOverlay').classList.contains('is-visible')).toBe(false);
    });
});
