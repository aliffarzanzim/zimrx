import { createGrowthData } from './growth_chart_engine.js';
import { drawGrowthChart } from './chart.js';
import { adjustHeightMeasurement } from './measurement.js';
import { selectGrowthSeries } from './series.js';

const form = document.getElementById('growth-form');
const svg = document.getElementById('growth-chart');
const status = document.getElementById('chart-status');
const fields = {
    gender: document.getElementById('gender'),
    metric: document.getElementById('metric'),
    standard: document.getElementById('standard'),
    ageMonths: document.getElementById('age-months'),
    heightPosition: document.getElementById('height-position'),
    value: document.getElementById('measured-value'),
};
const heightPositionControl = document.getElementById('height-position-control');
const heightGuidance = document.getElementById('height-guidance');
const extendedReferenceOption = fields.standard.querySelector('option[value="cdc_extended"]');

function updateExtendedReferenceAvailability() {
    const isBmi = fields.metric.value === 'bmi';
    extendedReferenceOption.disabled = !isBmi;
    if (!isBmi && fields.standard.value === 'cdc_extended') {
        fields.standard.value = 'cdc';
    }
}

function updateHeightPositionAvailability() {
    const isHeight = fields.metric.value === 'height';
    heightPositionControl.hidden = !isHeight;
    heightGuidance.hidden = !isHeight;
}

fields.metric.addEventListener('change', updateExtendedReferenceAvailability);
fields.metric.addEventListener('change', updateHeightPositionAvailability);
updateExtendedReferenceAvailability();
updateHeightPositionAvailability();

async function initialize() {
    try {
        const dataUrl = new URL('../../data/growth_chart_data.json', import.meta.url);
        const response = await fetch(dataUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Reference data request failed (${response.status}).`);
        const data = createGrowthData(await response.json());

        const render = () => {
            const ageInput = fields.ageMonths.value.trim();
            if (ageInput === '') {
                status.textContent = "Enter the child's age in months to display the reference chart.";
                svg.replaceChildren();
                return;
            }

            const ageMonths = Number(ageInput);
            if (!Number.isFinite(ageMonths) || ageMonths < 0) {
                status.textContent = 'Enter a valid age in months.';
                svg.replaceChildren();
                return;
            }

            const series = selectGrowthSeries(data, {
                ageMonths,
                metric: fields.metric.value,
                gender: fields.gender.value,
                standard: fields.standard.value,
            });
            if (series.error) {
                status.textContent = series.error;
                svg.replaceChildren();
                return;
            }

            const enteredValue = fields.value.value === '' ? null : Number(fields.value.value);
            let measuredValue = enteredValue;
            let positionAdjustment = 0;
            if (fields.metric.value === 'height') {
                const adjusted = adjustHeightMeasurement(
                    enteredValue,
                    ageMonths,
                    fields.heightPosition.value,
                    series.reference,
                );
                if (adjusted.error) {
                    status.textContent = adjusted.error;
                    svg.replaceChildren();
                    return;
                }
                measuredValue = adjusted.value;
                positionAdjustment = adjusted.adjustment;
            } else if (enteredValue !== null && (!Number.isFinite(enteredValue) || enteredValue <= 0)) {
                status.textContent = 'Enter a measured value greater than zero.';
                svg.replaceChildren();
                return;
            }

            status.textContent = series.label + ', ' + (fields.gender.value === 'Male' ? 'boys' : 'girls') + '.';
            if (positionAdjustment !== 0) {
                const sign = positionAdjustment > 0 ? '+' : '';
                status.textContent += ' Position adjustment: ' + sign + positionAdjustment
                    + ' cm; plotted value: ' + measuredValue.toFixed(2) + ' cm.';
            }
            drawGrowthChart(svg, series.rows, {
                ageMonths: series.ageMonths,
                value: measuredValue,
                unit: series.unit,
                curves: series.curves,
                status,
            });
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            render();
        });
        window.addEventListener('resize', render);
        render();
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Unable to load growth reference data.';
    }
}

initialize();
