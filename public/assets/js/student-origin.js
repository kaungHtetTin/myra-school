(() => {
    const source = document.getElementById('student-origin-data');
    if (!source) return;
    const data = JSON.parse(source.textContent);
    const panel = document.querySelector('.student-origin-panel');
    const chart = panel.querySelector('.student-origin-pie');
    const readout = panel.querySelector('[data-origin-readout]');
    const defaultText = readout.textContent;
    const nodes = panel.querySelectorAll('[data-origin-index]');
    function show(index) {
        const item = data.items[index];
        if (!item) return;
        chart.classList.toggle('is-highlighting', item.count > 0);
        nodes.forEach(node => node.classList.toggle('is-active', Number(node.dataset.originIndex) === index));
        readout.textContent = `${item.name}: ${item.count} active ${item.count === 1 ? 'student' : 'students'} (${item.percentage}%).`;
    }
    function clear() {
        chart.classList.remove('is-highlighting');
        nodes.forEach(node => node.classList.remove('is-active'));
        readout.textContent = defaultText;
    }
    nodes.forEach(node => {
        const index = Number(node.dataset.originIndex);
        node.addEventListener('pointerenter', () => show(index));
        node.addEventListener('focus', () => show(index));
        node.addEventListener('click', () => show(index));
        node.addEventListener('pointerleave', () => {
            const focused = panel.querySelector('[data-origin-index]:focus');
            if (focused) show(Number(focused.dataset.originIndex)); else clear();
        });
        node.addEventListener('blur', clear);
    });
})();
