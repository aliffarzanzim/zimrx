// Performance dashboard chart hover tooltips showing patient volume and revenue.
document.addEventListener('DOMContentLoaded', () => {
    const chartWrap = document.getElementById('trend-chart-wrap');
    const tooltip = document.getElementById('chart-tooltip');
    if (!chartWrap || !tooltip) return;

    chartWrap.querySelectorAll('.chart-point').forEach(pt => {
        pt.addEventListener('mouseenter', () => {
            const data = pt.dataset;
            const dateStr = new Date(data.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            const rev = parseInt(data.rev || 0).toLocaleString();

            tooltip.textContent = '';

            const strong = document.createElement('strong');
            strong.textContent = dateStr;

            const br = document.createElement('br');

            const patLabel = document.createTextNode('Patients: ');
            const patSpan = document.createElement('span');
            patSpan.className = 'tooltip-pat';
            patSpan.textContent = data.pat;

            const sep = document.createTextNode(' \u2022 Revenue: ');
            const revSpan = document.createElement('span');
            revSpan.className = 'tooltip-rev';
            revSpan.textContent = '\u09f3' + rev;

            tooltip.append(strong, br, patLabel, patSpan, sep, revSpan);
            tooltip.style.opacity = '1';

            const rect = pt.getBoundingClientRect();
            const wrapRect = chartWrap.getBoundingClientRect();
            tooltip.style.left = (rect.left - wrapRect.left + rect.width / 2) + 'px';
            tooltip.style.top = (rect.top - wrapRect.top) + 'px';
        });

        pt.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    });
});
