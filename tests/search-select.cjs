// Optional DOM interaction checks; install jsdom into the ignored cache directory.
const { JSDOM } = require('../storage/cache/select-tests/node_modules/jsdom');
const { readFileSync } = require('node:fs');
const { strict: assert } = require('node:assert');
const { resolve } = require('node:path');
const source = readFileSync(resolve(__dirname, '../public/assets/js/search-select.js'), 'utf8');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; console.log(`PASS ${message}`); }
function fixture(popover = false) {
    const dom = new JSDOM(`<form><label>Student<select name="student_id" data-searchable required><option value="">Choose a student</option><option value="1">Aye Chan / 09111</option><option value="2">May / 09222</option><option value="3">မေ / 09333</option></select></label><select name="class" aria-label="Class" data-searchable><option value="">All classes</option><option value="1">English</option></select><label>Status<select name="status"><option>Active</option></select></label><button>Save</button></form>`, { runScripts: 'outside-only' });
    const { window } = dom;
    window.HTMLElement.prototype.scrollIntoView = function() {};
    if (popover) {
        window.HTMLElement.prototype.showPopover = function() { this.dataset.open = 'true'; };
        window.HTMLElement.prototype.hidePopover = function() { delete this.dataset.open; };
        const matches = window.Element.prototype.matches;
        window.Element.prototype.matches = function(selector) { return selector === ':popover-open' ? this.dataset.open === 'true' : matches.call(this, selector); };
    }
    window.eval(source);
    const document = window.document;
    const input = document.querySelector('[role=combobox]');
    const select = document.querySelector('[name=student_id]');
    const form = document.querySelector('form');
    function type(text) { input.value = text; input.dispatchEvent(new window.Event('input', { bubbles: true })); }
    function key(key) { input.dispatchEvent(new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })); }
    return { dom, window, document, input, select, form, type, key };
}
(async () => {
    for (const popover of [false, true]) {
        const { dom, window, document, input, select, form, type, key } = fixture(popover);
        check(document.querySelectorAll('[role=combobox]').length === 2 && !document.querySelector('[name=status]').hidden, 'Only marked growing lists are enhanced');
        check(input.getAttribute('aria-label') === 'Student', 'Accessible name excludes the list of options');
        check(select.hidden && !select.required && !form.checkValidity(), 'An unselected required selector blocks submission through the visible input');
        input.focus();
        check(input.getAttribute('aria-expanded') === 'true' && document.querySelectorAll('[role=option]').length === 3, `Dropdown opens (${popover ? 'popover' : 'fallback'})`);
        type('09222');
        check(document.querySelectorAll('[role=option]').length === 1 && document.querySelector('[role=option]').textContent.includes('May'), 'Phone search narrows student options');
        key('Enter');
        check(select.value === '2' && input.value.includes('May') && form.checkValidity(), 'Enter commits the selected ID and clears required validation');
        check(new window.FormData(form).get('student_id') === '2' && !new window.FormData(form).has(input.id), 'Only the original record ID is submitted');
        type('aye 09111'); key('Enter');
        check(select.value === '1', 'Search matches multiple terms across name and phone');
        type('မေ'); key('Enter');
        check(select.value === '3', 'Myanmar student names are searchable');
        type('no such student');
        check(document.querySelectorAll('[role=option]').length === 0 && !document.querySelector('.search-select-message').hidden, 'No-match feedback is visible');
        key('Escape');
        check(select.value === '3' && input.value.includes('မေ') && input.getAttribute('aria-expanded') === 'false', 'Escape restores the committed value');
        input.click(); key('ArrowDown'); key('Enter');
        check(select.value === '1', 'Arrow navigation wraps through available options');
        input.click(); type('May'); key('Tab');
        check(select.value === '1' && input.value.includes('Aye Chan'), 'Tab discards an uncommitted search');
        input.click(); type('May');
        const click = new window.MouseEvent('click', { bubbles: true, cancelable: true });
        document.querySelector('[role=option]').dispatchEvent(click);
        check(select.value === '2' && input.getAttribute('aria-expanded') === 'false' && click.defaultPrevented, 'Pointer selection closes the menu without label activation reopening it');
        const classInput = document.querySelectorAll('[role=combobox]')[1];
        classInput.focus();
        check(input.getAttribute('aria-expanded') === 'false' && classInput.value === 'All classes', 'Optional All classes is preserved and only one list opens');
        classInput.blur();
        form.reset();
        await new Promise(resolve => window.setTimeout(resolve, 5));
        check(select.value === '' && input.value === '' && !form.checkValidity(), 'Form reset restores placeholder and required state');
        dom.window.close();
    }
    // Recreate with 2,000 options before enhancement to exercise bounded result rendering.
    const many = new JSDOM(`<form><label>Student<select name="student_id" required data-searchable><option value="">Choose student</option>${Array.from({ length: 2000 }, (_, i) => `<option value="${i + 1}">Learner ${i + 1} / phone-${i + 1}</option>`).join('')}</select></label></form>`, { runScripts: 'outside-only' });
    many.window.HTMLElement.prototype.scrollIntoView = function() {};
    many.window.eval(source);
    const largeInput = many.window.document.querySelector('input');
    largeInput.focus();
    check(many.window.document.querySelectorAll('[role=option]').length === 100 && many.window.document.querySelector('[role=status]').textContent.includes('2000'), 'Long lists render at most 100 results with narrowing guidance');
    largeInput.value = 'phone-2000';
    largeInput.dispatchEvent(new many.window.Event('input', { bubbles: true }));
    largeInput.dispatchEvent(new many.window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    check(many.window.document.querySelector('select').value === '2000', 'Search finds and selects a record beyond the initial 100 results');
    many.window.close();
    console.log(`\n${checks} selector interaction checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
