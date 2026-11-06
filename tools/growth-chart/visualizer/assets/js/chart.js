const CURVES = [
    { percentile: 'p3', standardDeviation: 'sd_minus_2', label: 'P3', dash: [5, 4] },
    { percentile: 'p50', standardDeviation: 'sd_0', label: 'P50', dash: [] },
    { percentile: 'p97', standardDeviation: 'sd_plus_2', label: 'P97', dash: [2, 3] },
];

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

function appendSvgElement(svg, name, attributes = {}) {
    const element = document.createElementNS(SVG_NAMESPACE, name);
    Object.entries(attributes).forEach(([attribute, value]) => {
        element.setAttribute(attribute, String(value));
    });
    svg.append(element);
    return element;
}

function appendText(svg, value, x, y, anchor = 'middle') {
    const text = appendSvgElement(svg, 'text', {
        x,
        y,
        fill: 'currentColor',
        'text-anchor': anchor,
    });
    text.textContent = String(value);
    return text;
}

export function drawGrowthChart(svg, rows, options) {
    if (!svg || typeof svg.getBoundingClientRect !== 'function') return;
    const bounds = svg.getBoundingClientRect();
    if (bounds.width <= 0 || bounds.height <= 0) return;

    const width = bounds.width;
    const height = bounds.height;
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.replaceChildren();
    const title = appendSvgElement(svg, 'title');
    title.textContent = 'Growth reference chart';
    const description = appendSvgElement(svg, 'desc');
    description.textContent = 'Percentile curves are labelled at their right endpoints.';

    if (!Array.isArray(rows) || rows.length < 2) {
        options.status.textContent = 'No reference series is available for this selection.';
        return;
    }

    const curves = (options.curves ?? CURVES).map((curve) => ({
        ...curve,
        key: curve.key ?? curve.percentile,
    }));
    if (curves.some((curve) => !rows.some((row) => Number.isFinite(row[curve.key])))) {
        options.status.textContent = 'The selected reference is missing a required percentile curve.';
        return;
    }

    const minAge = rows[0].m;
    const maxAge = rows[rows.length - 1].m;
    const hasMeasurement = Number.isFinite(options.value) && options.value > 0;
    if (hasMeasurement && (!Number.isFinite(options.ageMonths)
        || options.ageMonths < minAge || options.ageMonths > maxAge)) {
        options.status.textContent = 'The measurement age is outside the displayed reference range.';
        return;
    }

    let rawMin = Infinity;
    let rawMax = -Infinity;
    rows.forEach((row) => curves.forEach((curve) => {
        const value = row[curve.key];
        if (!Number.isFinite(value)) return;
        rawMin = Math.min(rawMin, value);
        rawMax = Math.max(rawMax, value);
    }));
    if (hasMeasurement) {
        rawMin = Math.min(rawMin, options.value);
        rawMax = Math.max(rawMax, options.value);
    }
    if (!Number.isFinite(rawMin) || !Number.isFinite(rawMax)) {
        options.status.textContent = 'The selected series contains no plottable values.';
        return;
    }

    const margin = { top: 16, right: 48, bottom: 42, left: 68 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;
    if (plotWidth <= 0 || plotHeight <= 0) return;

    const padding = Math.max((rawMax - rawMin) * 0.08, rawMax * 0.02, 0.1);
    const minValue = Math.max(0, rawMin - padding);
    const maxValue = rawMax + padding;
    const ageToX = (age) => margin.left + ((age - minAge) / (maxAge - minAge)) * plotWidth;
    const valueToY = (value) => margin.top + ((maxValue - value) / (maxValue - minValue)) * plotHeight;

    const grid = [];
    const yLabels = [];
    for (let tick = 0; tick <= 5; tick++) {
        const value = minValue + ((maxValue - minValue) * tick) / 5;
        const y = valueToY(value);
        grid.push(`M ${margin.left} ${y} H ${width - margin.right}`);
        yLabels.push({ value: value.toFixed(1), y });
    }

    const tickStep = maxAge <= 60 ? 12 : maxAge <= 120 ? 24 : 60;
    const xLabels = [];
    for (let age = Math.ceil(minAge / tickStep) * tickStep; age <= maxAge; age += tickStep) {
        const x = ageToX(age);
        grid.push(`M ${x} ${margin.top} V ${margin.top + plotHeight}`);
        xLabels.push({ value: age < 24 ? `${age} mo` : `${age / 12} yr`, x });
    }
    appendSvgElement(svg, 'path', {
        d: grid.join(' '),
        fill: 'none',
        stroke: 'currentColor',
        'stroke-opacity': '0.35',
    });
    yLabels.forEach(({ value, y }) => appendText(svg, value, margin.left - 8, y + 4, 'end'));
    xLabels.forEach(({ value, x }) => appendText(svg, value, x, height - margin.bottom + 20));

    curves.forEach((curve) => {
        const points = [];
        let started = false;
        let endpoint = null;
        rows.forEach((row) => {
            const value = row[curve.key];
            if (!Number.isFinite(value)) return;
            const x = ageToX(row.m);
            const y = valueToY(value);
            points.push(`${started ? 'L' : 'M'} ${x} ${y}`);
            started = true;
            endpoint = { x, y };
        });
        appendSvgElement(svg, 'path', {
            d: points.join(' '),
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': curve.percentile === 'p50' ? 2 : 1,
            ...(curve.dash.length ? { 'stroke-dasharray': curve.dash.join(' ') } : {}),
        });
        if (endpoint) {
            const label = appendText(svg, curve.label, endpoint.x + 6, endpoint.y + 4, 'start');
            label.setAttribute('font-size', '11');
        }
    });

    if (Number.isFinite(options.value) && options.value > 0
        && options.ageMonths >= minAge && options.ageMonths <= maxAge) {
        appendSvgElement(svg, 'circle', {
            cx: ageToX(options.ageMonths),
            cy: valueToY(options.value),
            r: 5,
            fill: 'currentColor',
        });
    }

    appendText(svg, 'Age', margin.left + plotWidth / 2, height - 5);
    const unitLabel = appendText(svg, options.unit, 16, margin.top + plotHeight / 2);
    unitLabel.setAttribute('transform', `rotate(-90 16 ${margin.top + plotHeight / 2})`);
}
