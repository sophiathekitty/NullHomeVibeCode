/**
 * Tests for BaseView — template cloning and container management.
 */

'use strict';

var BaseView = require('../../../public/js/views/base-view.js');

beforeEach(function () {
    document.body.innerHTML = [
        '<template id="test-tpl"><div class="item"><span class="label"></span></div></template>',
        '<div id="test-container"></div>'
    ].join('');
});

describe('BaseView constructor', function () {
    test('finds the template and container elements by ID', function () {
        var view = new BaseView('test-tpl', 'test-container');
        expect(view.template).toBe(document.getElementById('test-tpl'));
        expect(view.container).toBe(document.getElementById('test-container'));
    });
});

describe('BaseView.cloneTemplate', function () {
    test('returns a DocumentFragment that is a deep clone of the template content', function () {
        var view = new BaseView('test-tpl', 'test-container');
        var fragment = view.cloneTemplate();
        expect(fragment).toBeInstanceOf(DocumentFragment);
        expect(fragment.querySelector('.item')).not.toBeNull();
        expect(fragment.querySelector('.label')).not.toBeNull();
    });

    test('successive calls return independent clones', function () {
        var view = new BaseView('test-tpl', 'test-container');
        var a = view.cloneTemplate();
        var b = view.cloneTemplate();
        a.querySelector('.label').textContent = 'first';
        expect(b.querySelector('.label').textContent).toBe('');
    });
});

describe('BaseView.clear', function () {
    test('empties the container', function () {
        var view = new BaseView('test-tpl', 'test-container');
        view.container.innerHTML = '<p>existing content</p>';
        view.clear();
        expect(view.container.innerHTML).toBe('');
    });
});

describe('BaseView.render', function () {
    test('throws an error requiring subclass override', function () {
        var view = new BaseView('test-tpl', 'test-container');
        expect(function () { view.render({}); })
            .toThrow('BaseView.render() must be implemented by subclass.');
    });
});
