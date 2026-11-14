(() => {
    const dataElement = document.getElementById('finance-report-data');
    if (!dataElement) return;
    const report = JSON.parse(dataElement.textContent);
    const form = document.querySelector('[data-report-filters]');
    const month = form.querySelector('[data-report-month]');
    const year = form.querySelector('[data-report-year]');
    year.querySelector('input').min = '1000';
    const syncView = () => {
        const annual = form.elements.view.value === 'year';
        month.hidden = annual;
        year.hidden = !annual;
        month.querySelector('input').disabled = annual;
        year.querySelector('input').disabled = !annual;
    };
    form.elements.view.addEventListener('change', syncView);
    syncView();
    const output = document.querySelector('[data-report-readout]');
    const format = cents => `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(cents / 100)} ${report.currency}`;
    document.querySelectorAll('[data-report-point]').forEach(node => {
        const show = () => {
            const point = report.points[Number(node.dataset.reportPoint)];
            output.textContent = `${point.description} · Income: ${format(point.income)} · Outcome: ${format(point.expense)} · Profit: ${format(point.profit)}`;
        };
        node.addEventListener('pointerenter', show);
        node.addEventListener('focus', show);
        node.addEventListener('click', show);
    });
})();
