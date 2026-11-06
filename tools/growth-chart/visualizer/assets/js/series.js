const METRICS = {
    weight: { label: 'weight-for-age', unit: 'kg', who: 'wfa', who2007: 'wfa', cdc: 'wfa' },
    height: { label: 'length/height-for-age', unit: 'cm', who: 'lhfa', who2007: 'hfa', cdc: 'hfa' },
    hc: { label: 'head circumference-for-age', unit: 'cm', who: 'hcfa', who2007: null, cdc: null },
    bmi: { label: 'BMI-for-age', unit: 'kg/m²', who: 'bmifa', who2007: 'bmifa', cdc: 'bmifa' },
};

const CDC_EXTENDED_BMI_CURVES = [
    { percentile: 'p3', key: 'p3', label: 'P3', dash: [5, 4] },
    { percentile: 'p50', key: 'p50', label: 'P50', dash: [] },
    { percentile: 'p95', key: 'p95', label: 'P95', dash: [2, 3] },
    { percentile: 'p98', key: 'p98', label: 'P98', dash: [8, 3] },
    { percentile: 'p99', key: 'p99', label: 'P99', dash: [4, 2] },
    { percentile: 'p99_9', key: 'p99_9', label: 'P99.9', dash: [1, 2] },
    { percentile: 'p99_99', key: 'p99_99', label: 'P99.99', dash: [7, 2, 1, 2] },
];

function unsupported(message) {
    return { rows: [], error: message };
}

export function selectGrowthSeries(data, options) {
    if (!data || typeof data !== 'object' || !options || typeof options !== 'object') {
        return unsupported('Growth reference data or chart selection is invalid.');
    }
    const { ageMonths, metric, gender, standard } = options;
    if (!Number.isFinite(ageMonths) || ageMonths < 0) {
        return unsupported('Enter a valid age in months.');
    }

    const definition = METRICS[metric];
    if (!definition) return unsupported('Select a valid measure.');
    if (gender !== 'Male' && gender !== 'Female') return unsupported('Select a valid sex.');
    if (standard !== 'auto' && standard !== 'who' && standard !== 'cdc' && standard !== 'cdc_extended') {
        return unsupported('Select a valid reference.');
    }

    const useWho = standard === 'who' || (standard === 'auto' && ageMonths <= 60);
    let family;
    let metricKey;
    let source;
    let reference;
    let minAge;
    let maxAge;
    let curves;

    if (standard === 'cdc_extended') {
        if (metric !== 'bmi') return unsupported('CDC 2022 Extended is available for BMI-for-age only.');
        family = data.CDCEXTENDED;
        metricKey = 'bmifa';
        source = 'CDC 2022 Extended';
        reference = 'cdc2022Extended';
        minAge = 24;
        maxAge = 240.5;
        curves = CDC_EXTENDED_BMI_CURVES;
    } else if (useWho) {
        const useWho2007 = metric !== 'hc' && ageMonths > 60;
        family = useWho2007 ? data.WHO2007 : data.WHO;
        metricKey = useWho2007 ? definition.who2007 : definition.who;
        source = useWho2007 ? 'WHO 2007' : 'WHO 2006';
        reference = useWho2007 ? 'who2007' : 'who2006';
        minAge = useWho2007 ? 61 : 0;
        maxAge = useWho2007 ? (metric === 'weight' ? 120 : 228) : 60;
    } else {
        if (definition.cdc === null) {
            return unsupported('No CDC 2000 head circumference reference is available.');
        }
        family = data.CDC;
        metricKey = definition.cdc;
        source = 'CDC 2000';
        reference = 'cdc2000';
        minAge = 24;
        maxAge = metric === 'bmi' ? 240.5 : 240;
    }

    if (ageMonths < minAge || ageMonths > maxAge) {
        return unsupported(source + ' ' + definition.label + ' data cover ages ' + minAge + ' to ' + maxAge + ' months.');
    }

    const suffix = gender === 'Male' ? '_boys' : '_girls';
    const sourceRows = family?.[metricKey + suffix];
    if (!Array.isArray(sourceRows)) {
        return unsupported('No ' + source + ' ' + definition.label + ' series is available.');
    }

    const rows = sourceRows.filter((row) => Number.isFinite(row?.m)
        && row.m >= minAge
        && row.m <= maxAge);
    if (rows.length < 2) return unsupported('No ' + source + ' ' + definition.label + ' series is available.');

    return {
        rows,
        ageMonths,
        minAge,
        maxAge,
        unit: definition.unit,
        reference,
        label: source + ': ' + (metric === 'height' && reference === 'who2006' && ageMonths < 24
            ? 'length-for-age'
            : metric === 'height' ? 'height-for-age' : definition.label),
        curves,
        error: null,
    };
}
