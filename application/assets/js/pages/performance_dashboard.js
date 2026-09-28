/**
 * performance_dashboard.js - Interactive SVG chart hover tooltips
 */
document.addEventListener('DOMContentLoaded', () => {
    const chartWrap = document.getElementById('trend-chart-wrap');
    const tooltip = document.getElementById('chart-tooltip');
    if (!chartWrap || !tooltip) return;

    chartWrap.querySelectorAll('.chart-point').forEach(pt => {
        pt.addEventListener('mouseenter', (e) => {
            const data = pt.dataset;
            const dateStr = new Date(data.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            tooltip.innerHTML = `<strong>${dateStr}</strong><br>Patients: <span style="color:#60a5fa">${data.pat}</span> • Revenue: <span style="color:#34d399">৳${parseInt(data.rev || 0).toLocaleString()}</span>`;
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
