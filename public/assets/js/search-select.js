// Progressive enhancement: the original select remains the submitted source of truth.
(() => {
    let nextId = 0;
    let closeCurrent = () => {};
    const normalize = text => text.normalize('NFKC').toLocaleLowerCase().replace(/\s+/g, ' ').trim();

    document.querySelectorAll('select[data-searchable]').forEach(select => {
        const required = select.required;
        const label = select.labels?.[0];
        const id = `search-select-${++nextId}`;
        const wrapper = document.createElement('span');
        wrapper.className = 'search-select';
        if (select.parentElement.classList.contains('filters')) wrapper.classList.add('search-select-filter');
        const input = document.createElement('input');
        input.type = 'text';
        input.id = `${id}-input`;
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.required = required;
        input.disabled = select.disabled;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', `${id}-list`);
        const labelText = label && Array.from(label.childNodes).filter(node => node.nodeType === Node.TEXT_NODE).map(node => node.textContent).join(' ').trim();
        input.setAttribute('aria-label', select.getAttribute('aria-label') || labelText || 'Search options');
        const arrow = document.createElement('span');
        arrow.className = 'search-select-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        const popup = document.createElement('div');
        popup.className = 'search-select-popup';
        popup.hidden = true;
        const usePopover = typeof popup.showPopover === 'function';
        if (usePopover) popup.setAttribute('popover', 'manual');
        const list = document.createElement('div');
        list.id = `${id}-list`;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', input.getAttribute('aria-label'));
        const message = document.createElement('div');
        message.className = 'search-select-message';
        message.setAttribute('role', 'status');
        popup.append(list, message);
        select.before(wrapper);
        wrapper.append(input, arrow, select, popup);
        if (label?.htmlFor) label.htmlFor = input.id;
        select.hidden = true;
        select.required = false;
        // Do not make the wrapper's width depend on a long selected name.
        const getOptions = () => Array.from(select.options).filter(option => !option.disabled && !option.hidden && (!required || option.value !== '')).map(option => ({
            value: option.value,
            text: option.textContent,
            search: normalize(`${option.textContent} ${option.dataset.search || ''}`),
        }));
        let visible = [];
        let active = -1;
        let opened = false;

        function sync() {
            const selected = select.selectedOptions[0];
            input.value = required && !select.value ? '' : (selected?.textContent || '');
            input.placeholder = `Search ${input.getAttribute('aria-label').toLocaleLowerCase()}…`;
            input.setCustomValidity(required && !select.value ? 'Choose an option from the list.' : '');
        }
        function position() {
            if (!opened) return;
            const rect = input.getBoundingClientRect();
            const viewportHeight = window.visualViewport?.height || window.innerHeight;
            const below = viewportHeight - rect.bottom - 8;
            const above = rect.top - 8;
            const upwards = below < 180 && above > below;
            popup.style.width = `${rect.width}px`;
            popup.style.maxHeight = `${Math.max(60, Math.min(280, upwards ? above : below))}px`;
            popup.style.left = `${Math.max(4, Math.min(rect.left, window.innerWidth - rect.width - 4))}px`;
            popup.style.top = upwards ? 'auto' : `${rect.bottom + 4}px`;
            popup.style.bottom = upwards ? `${window.innerHeight - rect.top + 4}px` : 'auto';
        }
        function highlight(index) {
            active = index;
            Array.from(list.children).forEach((node, i) => node.classList.toggle('is-active', i === active));
            if (active >= 0) {
                input.setAttribute('aria-activedescendant', `${id}-option-${active}`);
                list.children[active]?.scrollIntoView({ block: 'nearest' });
            } else input.removeAttribute('aria-activedescendant');
        }
        function render(query = '') {
            const terms = normalize(query).split(' ').filter(Boolean);
            const matches = getOptions().filter(option => terms.every(term => option.search.includes(term)));
            visible = matches.slice(0, 100);
            list.replaceChildren();
            visible.forEach((option, index) => {
                const node = document.createElement('div');
                node.id = `${id}-option-${index}`;
                node.className = 'search-select-option';
                node.setAttribute('role', 'option');
                node.setAttribute('aria-selected', String(option.value === select.value));
                node.textContent = option.text;
                node.addEventListener('pointerdown', event => event.preventDefault());
                node.addEventListener('click', event => { event.preventDefault(); commit(index); });
                node.addEventListener('pointermove', () => highlight(index));
                list.append(node);
            });
            message.textContent = !matches.length ? 'No matching options.' : matches.length > visible.length ? `Showing ${visible.length} of ${matches.length}. Type to narrow the list.` : '';
            message.hidden = !message.textContent;
            highlight(query ? (visible.length ? 0 : -1) : visible.findIndex(option => option.value === select.value));
            position();
        }
        function open() {
            if (opened || input.disabled) return;
            closeCurrent();
            closeCurrent = close;
            opened = true;
            popup.hidden = false;
            if (usePopover) popup.showPopover();
            input.setAttribute('aria-expanded', 'true');
            render();
        }
        function close() {
            if (!opened) return;
            opened = false;
            if (usePopover && popup.matches(':popover-open')) popup.hidePopover();
            popup.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            sync();
        }
        function commit(index) {
            if (!visible[index]) return;
            select.value = visible[index].value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        }
        input.addEventListener('focus', () => { open(); input.select(); });
        input.addEventListener('click', open);
        input.addEventListener('input', () => { open(); render(input.value); });
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.isComposing) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const wasOpen = opened;
                open();
                if (visible.length) highlight(!wasOpen ? (active >= 0 ? active : 0) : (active + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length);
            } else if (event.key === 'Enter' && opened) {
                event.preventDefault();
                if (active >= 0) commit(active);
            } else if (event.key === 'Escape' && opened) {
                event.preventDefault();
                event.stopPropagation();
                close();
            } else if (event.key === 'Tab') close();
        });
        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => setTimeout(() => { close(); sync(); }, 0));
        select.closest('dialog')?.addEventListener('close', close);
        window.addEventListener('pageshow', () => { close(); sync(); });
        document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
        window.addEventListener('resize', position);
        window.addEventListener('scroll', event => { if (!popup.contains(event.target)) position(); }, true);
        sync();
    });
})();
