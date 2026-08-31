function initOhModule() {
  if (window.__zimrxHistoryUiInitialized) {
    initializeOhUi();
    initializeMhUi();
    refreshLmpDependentFields();
    return;
  }

  window.__zimrxHistoryUiInitialized = true;

  let ohDragRow = null;
  let ohChartDragRow = null;
  let ohNumpad = null;
  let activeOhNumpadInput = null;

  let mhDragRow = null;
  let mhNumpad = null;
  let activeMhNumpadInput = null;

  const ohNumpadSelector = '.js-oh-module .oh-duration-input, .js-oh-module .oh-value-input:not([readonly])';
  const mhNumpadSelector = '.js-mh-module .oh-value-input[data-mh-keypad="1"]';

  const getOhModule = () => document.querySelector('.js-oh-module');
  const getOhTableBody = () => getOhModule()?.querySelector('.oh-table tbody') || null;
  const getOhCalcModal = () => document.getElementById('oh-calc-modal');
  const getOhChartModal = () => document.getElementById('oh-chart-modal');
  const getOhChartTableBody = () => getOhModule()?.querySelector('.oh-chart-table tbody') || null;

  const getMhModule = () => document.querySelector('.js-mh-module');
  const getMhTableBody = () => getMhModule()?.querySelector('.oh-table tbody') || null;
  const getMhRow = (kind) => getMhModule()?.querySelector(`.oh-row[data-row-kind="${kind}"]`) || null;
  const getMhLmpInput = () => getMhRow('lmp')?.querySelector('.mh-lmp-input') || null;
  const getMhEddInput = () => getMhRow('edd')?.querySelector('.mh-edd-input') || null;

  const getCalcEddLmpInput = () => document.getElementById('calc-edd-lmp');
  const getCalcEddAgeOutput = () => document.getElementById('calc-edd-gest-age');
  const getCalcEddResultOutput = () => document.getElementById('calc-edd-result');
  const isInsideMhNumpad = (target) => Boolean(target?.closest?.('.mh-virtual-numpad'));
  const isInsideOhOnlyNumpad = (target) => Boolean(target?.closest?.('.oh-virtual-numpad'));

  const ensureDatePickers = (root = document) => {
    if (typeof flatpickr !== 'function') {
      return;
    }

    root.querySelectorAll('.custom-date-picker').forEach((input) => {
      if (input._flatpickr) {
        return;
      }

      flatpickr(input, {
        dateFormat: 'd/m/Y',
        allowInput: true
      });
    });
  };

  const createVirtualNumpad = (className) => {
    const pad = document.createElement('div');
    pad.className = className;
    pad.hidden = true;
    pad.innerHTML = `
      <button type="button" data-key="7">7</button>
      <button type="button" data-key="8">8</button>
      <button type="button" data-key="9">9</button>
      <button type="button" data-key=".">.</button>
      <button type="button" data-key="4">4</button>
      <button type="button" data-key="5">5</button>
      <button type="button" data-key="6">6</button>
      <button type="button" data-key="/">/</button>
      <button type="button" data-key="1">1</button>
      <button type="button" data-key="2">2</button>
      <button type="button" data-key="3">3</button>
      <button type="button" data-key="±">±</button>
      <button type="button" data-key="0" class="wide">0</button>
      <button type="button" data-key="+">+</button>
      <button type="button" data-key="-">-</button>
      <button type="button" data-key="back">Back</button>
      <button type="button" data-key="close" class="full">Close</button>
    `;
    document.body.appendChild(pad);
    return pad;
  };

  const positionNumpad = (pad, input) => {
    if (!pad || !input || pad.hidden) {
      return;
    }

    const rect = input.getBoundingClientRect();
    const gap = 10;
    const padWidth = pad.offsetWidth || 214;
    let left = rect.right + gap + window.scrollX;
    let top = rect.top + window.scrollY;

    if (left + padWidth > window.scrollX + window.innerWidth - 12) {
      left = Math.max(window.scrollX + 12, rect.left + window.scrollX - padWidth - gap);
    }

    const maxTop = window.scrollY + window.innerHeight - (pad.offsetHeight || 0) - 12;
    if (top > maxTop) {
      top = Math.max(window.scrollY + 12, maxTop);
    }

    pad.style.left = `${left}px`;
    pad.style.top = `${top}px`;
  };

  const insertNumpadValue = (input, key, hidePad) => {
    if (!input) {
      return;
    }

    if (key === 'close') {
      hidePad({ blur: true });
      return;
    }

    input.focus();
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? input.value.length;
    const value = input.value || '';

    if (key === 'back') {
      if (start === end && start > 0) {
        input.value = value.slice(0, start - 1) + value.slice(end);
        input.setSelectionRange(start - 1, start - 1);
      } else {
        input.value = value.slice(0, start) + value.slice(end);
        input.setSelectionRange(start, start);
      }
    } else if (key === '\u00B1' || key === '\u00C2\u00B1') {
      input.value = value.slice(0, start) + '\u00B1' + value.slice(end);
      const cursor = start + 1;
      input.setSelectionRange(cursor, cursor);
    } else {
      input.value = value.slice(0, start) + key + value.slice(end);
      const cursor = start + key.length;
      input.setSelectionRange(cursor, cursor);
    }

    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  const buildOhNumpad = () => {
    if (!ohNumpad) {
      ohNumpad = createVirtualNumpad('oh-virtual-numpad');
    }
    return ohNumpad;
  };

  const showOhNumpad = (input) => {
    if (!input?.matches(ohNumpadSelector)) {
      return;
    }

    hideMhNumpad();
    activeOhNumpadInput = input;
    const pad = buildOhNumpad();
    pad.hidden = false;
    positionNumpad(pad, input);
  };

  const hideOhNumpad = ({ blur = false } = {}) => {
    if (!ohNumpad) {
      return;
    }

    const input = activeOhNumpadInput;
    ohNumpad.hidden = true;
    activeOhNumpadInput = null;
    if (blur && input && document.activeElement === input) {
      input.blur();
    }
  };

  const buildMhNumpad = () => {
    if (!mhNumpad) {
      mhNumpad = createVirtualNumpad('mh-virtual-numpad');
    }
    return mhNumpad;
  };

  const showMhNumpad = (input) => {
    if (!input?.matches(mhNumpadSelector)) {
      return;
    }

    hideOhNumpad();
    activeMhNumpadInput = input;
    const pad = buildMhNumpad();
    pad.hidden = false;
    positionNumpad(pad, input);
  };

  const hideMhNumpad = ({ blur = false } = {}) => {
    if (!mhNumpad) {
      return;
    }

    const input = activeMhNumpadInput;
    mhNumpad.hidden = true;
    activeMhNumpadInput = null;
    if (blur && input && document.activeElement === input) {
      input.blur();
    }
  };

  const syncOhChartPlaceField = (row) => {
    const select = row?.querySelector('.oh-chart-place-select');
    const customInput = row?.querySelector('.oh-chart-place-custom');
    if (!select || !customInput) {
      return;
    }

    const isCustom = select.value === 'Custom';
    customInput.hidden = !isCustom;
    if (!isCustom) {
      customInput.value = '';
    }
  };

  const renumberRows = (tbody, rowSelector, numberSelector) => {
    tbody?.querySelectorAll(rowSelector).forEach((row, index) => {
      const numberCell = row.querySelector(numberSelector);
      if (numberCell) {
        numberCell.textContent = String(index + 1);
      }
    });
  };

  const renumberOhRows = () => {
    renumberRows(getOhTableBody(), '.oh-row', '.oh-row-no');
  };

  const renumberOhChartRows = () => {
    renumberRows(getOhChartTableBody(), '.oh-chart-row', '.oh-chart-row-no');
  };

  const renumberMhRows = () => {
    renumberRows(getMhTableBody(), '.oh-row', '.oh-row-no');
  };

  const createOhRow = () => {
    const template = document.getElementById('oh-row-template');
    const row = template?.content?.firstElementChild?.cloneNode(true);
    if (!row) {
      return null;
    }

    row.querySelectorAll('input').forEach((input) => {
      input.value = '';
    });
    return row;
  };

  const addOhRow = () => {
    const tbody = getOhTableBody();
    if (!tbody) {
      return null;
    }

    const row = createOhRow();
    if (!row) {
      return null;
    }

    tbody.appendChild(row);
    renumberOhRows();
    return row;
  };

  const ensureAtLeastOneOhRow = () => {
    const tbody = getOhTableBody();
    if (!tbody) {
      return;
    }

    if (!tbody.querySelector('.oh-row')) {
      addOhRow();
    } else {
      renumberOhRows();
    }
  };

  const createOhChartRow = () => {
    const template = document.getElementById('oh-chart-row-template');
    const row = template?.content?.firstElementChild?.cloneNode(true);
    if (!row) {
      return null;
    }

    row.querySelectorAll('input').forEach((input) => {
      input.value = '';
    });
    row.querySelectorAll('select').forEach((select) => {
      select.selectedIndex = 0;
    });
    syncOhChartPlaceField(row);
    return row;
  };

  const addOhChartRow = () => {
    const tbody = getOhChartTableBody();
    if (!tbody) {
      return null;
    }

    const row = createOhChartRow();
    if (!row) {
      return null;
    }

    tbody.appendChild(row);
    renumberOhChartRows();
    return row;
  };

  const ensureAtLeastOneOhChartRow = () => {
    const tbody = getOhChartTableBody();
    if (!tbody) {
      return;
    }

    if (!tbody.querySelector('.oh-chart-row')) {
      addOhChartRow();
    } else {
      tbody.querySelectorAll('.oh-chart-row').forEach((row) => syncOhChartPlaceField(row));
      renumberOhChartRows();
    }
  };

  const createMhRow = () => {
    const template = document.getElementById('mh-row-template');
    const row = template?.content?.firstElementChild?.cloneNode(true);
    if (!row) {
      return null;
    }

    row.querySelectorAll('input').forEach((input) => {
      input.value = '';
      input.removeAttribute('readonly');
      input.removeAttribute('data-mh-keypad');
      input.classList.remove('mh-lmp-input', 'mh-edd-input', 'custom-date-picker');
      input.removeAttribute('placeholder');
      input.removeAttribute('inputmode');
    });
    return row;
  };

  const addMhRow = () => {
    const tbody = getMhTableBody();
    if (!tbody) {
      return null;
    }

    const row = createMhRow();
    if (!row) {
      return null;
    }

    tbody.appendChild(row);
    renumberMhRows();
    return row;
  };

  const ensureAtLeastOneMhRow = () => {
    const tbody = getMhTableBody();
    if (!tbody) {
      return;
    }

    if (!tbody.querySelector('.oh-row')) {
      addMhRow();
    } else {
      renumberMhRows();
    }
  };

  const clearDropTargets = (tbody, selector) => {
    tbody?.querySelectorAll(selector).forEach((row) => {
      row.classList.remove('drop-target');
    });
  };

  const openCalcModal = () => {
    const modal = getOhCalcModal();
    if (!modal) {
      return;
    }

    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    const today = new Date();
    const todayValue = today.toISOString().slice(0, 10);
    const toInput = document.getElementById('oh-date-to');
    const fromInput = document.getElementById('oh-date-from');
    if (toInput && !toInput.value) {
      toInput.value = todayValue;
    }
    if (fromInput && !fromInput.value) {
      const past = new Date(today);
      past.setFullYear(past.getFullYear() - 1);
      fromInput.value = past.toISOString().slice(0, 10);
    }

    updateParaGravidaOutput();
    updateDateOutput();
  };

  const closeCalcModal = () => {
    const modal = getOhCalcModal();
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.body.style.overflow = '';
  };

  const openChartModal = () => {
    const modal = getOhChartModal();
    if (!modal) {
      return;
    }

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    ensureAtLeastOneOhChartRow();
  };

  const closeChartModal = () => {
    const modal = getOhChartModal();
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.body.style.overflow = '';
  };

  const getParaGravidaValues = () => {
    const para = Number(document.getElementById('oh-calc-para')?.value || 0);
    const abortion = Number(document.getElementById('oh-calc-abortion')?.value || 0);
    const currentPregnancy = Number(document.getElementById('oh-calc-current-pregnancy')?.value || 0);
    const gravida = Math.max(0, para) + Math.max(0, abortion) + Math.max(0, currentPregnancy);
    return {
      para: Math.max(0, para),
      gravida
    };
  };

  const updateParaGravidaOutput = () => {
    const output = document.getElementById('oh-calc-gravida-output');
    if (!output) {
      return;
    }

    output.textContent = String(getParaGravidaValues().gravida);
  };

  const calculateDateDuration = () => {
    const fromValue = document.getElementById('oh-date-from')?.value || '';
    const toValue = document.getElementById('oh-date-to')?.value || '';
    const fromDate = fromValue ? new Date(`${fromValue}T00:00:00`) : null;
    const toDate = toValue ? new Date(`${toValue}T00:00:00`) : null;

    if (!fromDate || !toDate || Number.isNaN(fromDate.getTime()) || Number.isNaN(toDate.getTime()) || fromDate > toDate) {
      return { value: '', unit: 'Years', display: 'Select valid dates' };
    }

    const diffDays = Math.max(0, Math.round((toDate.getTime() - fromDate.getTime()) / 86400000));
    if (diffDays >= 365) {
      const years = Math.floor(diffDays / 365);
      return { value: String(years), unit: years === 1 ? 'Year' : 'Years', display: `${years} ${years === 1 ? 'Year' : 'Years'}` };
    }
    if (diffDays >= 30) {
      const months = Math.floor(diffDays / 30);
      return { value: String(months), unit: months === 1 ? 'Month' : 'Months', display: `${months} ${months === 1 ? 'Month' : 'Months'}` };
    }
    if (diffDays >= 7) {
      const weeks = Math.floor(diffDays / 7);
      return { value: String(weeks), unit: weeks === 1 ? 'Week' : 'Weeks', display: `${weeks} ${weeks === 1 ? 'Week' : 'Weeks'}` };
    }
    return { value: String(diffDays), unit: diffDays === 1 ? 'Day' : 'Days', display: `${diffDays} ${diffDays === 1 ? 'Day' : 'Days'}` };
  };

  const updateDateOutput = () => {
    const output = document.getElementById('oh-date-output');
    if (!output) {
      return;
    }

    output.textContent = calculateDateDuration().display;
  };

  const getOhRowByKind = (kind) => {
    return getOhModule()?.querySelector(`.oh-row[data-row-kind="${kind}"]`) || null;
  };

  const applyParaGravida = () => {
    const { para, gravida } = getParaGravidaValues();
    const paraRow = getOhRowByKind('para');
    const gravidaRow = getOhRowByKind('gravida');
    const paraInput = paraRow?.querySelector('.oh-value-input');
    const gravidaInput = gravidaRow?.querySelector('.oh-value-input');
    if (paraInput) {
      paraInput.value = String(para);
    }
    if (gravidaInput) {
      gravidaInput.value = String(gravida);
    }
  };

  const applyDateDuration = () => {
    const target = document.getElementById('oh-date-target')?.value || 'married_for';
    const result = calculateDateDuration();
    if (!result.value) {
      return;
    }

    const row = getOhRowByKind(target);
    const valueInput = row?.querySelector('.oh-duration-input');
    const unitSelect = row?.querySelector('.oh-unit-select');
    if (valueInput) {
      valueInput.value = result.value;
    }
    if (unitSelect) {
      unitSelect.value = result.unit;
    }
  };

  const parseDisplayDate = (value) => {
    const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) {
      return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const date = new Date(year, month - 1, day);
    if (
      Number.isNaN(date.getTime()) ||
      date.getFullYear() !== year ||
      date.getMonth() !== month - 1 ||
      date.getDate() !== day
    ) {
      return null;
    }

    date.setHours(0, 0, 0, 0);
    return date;
  };

  const formatDisplayDate = (date) => {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${date.getFullYear()}`;
  };

  const getOrdinalSuffix = (day) => {
    if (day >= 11 && day <= 13) {
      return 'th';
    }

    switch (day % 10) {
      case 1:
        return 'st';
      case 2:
        return 'nd';
      case 3:
        return 'rd';
      default:
        return 'th';
    }
  };

  const formatLongDate = (date) => {
    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const day = date.getDate();
    return `${day}${getOrdinalSuffix(day)} ${months[date.getMonth()]} ${date.getFullYear()}`;
  };

  const addCalendarMonths = (date, months) => {
    const year = date.getFullYear();
    const monthIndex = date.getMonth() + months;
    const targetYear = year + Math.floor(monthIndex / 12);
    const targetMonth = ((monthIndex % 12) + 12) % 12;
    const lastDay = new Date(targetYear, targetMonth + 1, 0).getDate();
    const targetDay = Math.min(date.getDate(), lastDay);
    return new Date(targetYear, targetMonth, targetDay);
  };

  const calculateEddFromLmp = (lmpDate) => {
    const edd = addCalendarMonths(lmpDate, 9);
    edd.setDate(edd.getDate() + 7);
    return edd;
  };

  const calculateGestationalAge = (lmpDate) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.floor((today.getTime() - lmpDate.getTime()) / 86400000);
    if (Number.isNaN(diffDays) || diffDays < 0) {
      return '';
    }

    const weeks = Math.floor(diffDays / 7);
    const days = diffDays % 7;
    return `${weeks} week${weeks === 1 ? '' : 's'} ${days} day${days === 1 ? '' : 's'}`;
  };

  const buildLmpState = (rawValue) => {
    const trimmed = String(rawValue || '').trim();
    const parsed = parseDisplayDate(trimmed);
    if (!parsed) {
      return {
        lmp: trimmed,
        edd: '',
        gestationalAge: ''
      };
    }

    return {
      lmp: formatDisplayDate(parsed),
      edd: formatLongDate(calculateEddFromLmp(parsed)),
      gestationalAge: calculateGestationalAge(parsed)
    };
  };

  const syncCalculatorFromLmpState = (state, source) => {
    const lmpInput = getCalcEddLmpInput();
    if (lmpInput && source !== 'calculator' && document.activeElement !== lmpInput) {
      lmpInput.value = state.lmp;
    }

    const ageOutput = getCalcEddAgeOutput();
    if (ageOutput) {
      ageOutput.value = state.gestationalAge;
    }

    const eddOutput = getCalcEddResultOutput();
    if (eddOutput) {
      eddOutput.value = state.edd;
    }
  };

  const syncMhFromLmpState = (state, source) => {
    const lmpInput = getMhLmpInput();
    if (lmpInput && source !== 'mh' && document.activeElement !== lmpInput) {
      lmpInput.value = state.lmp;
    }

    const eddInput = getMhEddInput();
    if (eddInput) {
      eddInput.value = state.edd;
    }
  };

  const syncLmpAcrossUi = (rawValue, source) => {
    const state = buildLmpState(rawValue);
    syncMhFromLmpState(state, source);
    syncCalculatorFromLmpState(state, source);
    return state;
  };

  function refreshLmpDependentFields() {
    const sourceValue = getMhLmpInput()?.value || getCalcEddLmpInput()?.value || '';
    syncLmpAcrossUi(sourceValue, 'refresh');
  }

  function initializeOhUi() {
    ensureAtLeastOneOhRow();
    ensureAtLeastOneOhChartRow();
  }

  function initializeMhUi() {
    ensureDatePickers(getMhModule() || document);
    ensureAtLeastOneMhRow();
  }

  document.addEventListener('click', (event) => {
    const chartButton = event.target.closest('.js-oh-module .oh-chart-btn');
    if (chartButton) {
      event.preventDefault();
      openChartModal();
      return;
    }

    const calcButton = event.target.closest('.js-oh-module .oh-calc-btn');
    if (calcButton) {
      event.preventDefault();
      openCalcModal();
      return;
    }

    if (event.target.closest('[data-oh-chart-close]')) {
      event.preventDefault();
      closeChartModal();
      return;
    }

    if (event.target.closest('[data-oh-calc-close]')) {
      event.preventDefault();
      closeCalcModal();
      return;
    }

    const ohRemoveButton = event.target.closest('.js-oh-module .oh-del button');
    if (ohRemoveButton) {
      event.preventDefault();
      ohRemoveButton.closest('.oh-row')?.remove();
      renumberOhRows();
      return;
    }

    const mhRemoveButton = event.target.closest('.js-mh-module .oh-del button');
    if (mhRemoveButton) {
      event.preventDefault();
      const row = mhRemoveButton.closest('.oh-row');
      row?.remove();
      renumberMhRows();
      refreshLmpDependentFields();
      return;
    }

    const addOhRowButton = event.target.closest('.js-oh-module .oh-add-row-btn');
    if (addOhRowButton) {
      event.preventDefault();
      addOhRow();
      return;
    }

    const addMhRowButton = event.target.closest('.js-mh-module .mh-add-row-btn');
    if (addMhRowButton) {
      event.preventDefault();
      addMhRow();
      return;
    }

    const chartRemoveButton = event.target.closest('.js-oh-module .oh-chart-del button');
    if (chartRemoveButton) {
      event.preventDefault();
      chartRemoveButton.closest('.oh-chart-row')?.remove();
      renumberOhChartRows();
      return;
    }

    const chartAddRowButton = event.target.closest('.js-oh-module .oh-chart-add-row-btn');
    if (chartAddRowButton) {
      event.preventDefault();
      addOhChartRow();
      return;
    }

    const applyButton = event.target.closest('[data-oh-calc-apply]');
    if (applyButton) {
      event.preventDefault();
      if (applyButton.dataset.ohCalcApply === 'para-gravida') {
        applyParaGravida();
      } else if (applyButton.dataset.ohCalcApply === 'date') {
        applyDateDuration();
      }
      return;
    }

    if (ohNumpad && !ohNumpad.hidden && !isInsideOhOnlyNumpad(event.target) && !event.target.closest(ohNumpadSelector)) {
      hideOhNumpad({ blur: true });
    }

    if (mhNumpad && !mhNumpad.hidden && !isInsideMhNumpad(event.target) && !event.target.closest(mhNumpadSelector)) {
      hideMhNumpad({ blur: true });
    }
  });

  document.addEventListener('mousedown', (event) => {
    const mhNumpadButton = event.target.closest('.mh-virtual-numpad button');
    if (mhNumpadButton && mhNumpad?.contains(mhNumpadButton)) {
      event.preventDefault();
      insertNumpadValue(activeMhNumpadInput, mhNumpadButton.dataset.key || '', hideMhNumpad);
      positionNumpad(mhNumpad, activeMhNumpadInput);
      return;
    }

    const ohNumpadButton = event.target.closest('.oh-virtual-numpad button');
    if (ohNumpadButton && ohNumpad?.contains(ohNumpadButton) && !isInsideMhNumpad(ohNumpadButton)) {
      event.preventDefault();
      insertNumpadValue(activeOhNumpadInput, ohNumpadButton.dataset.key || '', hideOhNumpad);
      positionNumpad(ohNumpad, activeOhNumpadInput);
      return;
    }
  });

  document.addEventListener('pointerdown', (event) => {
    if (mhNumpad && !mhNumpad.hidden && !isInsideMhNumpad(event.target) && !event.target.closest(mhNumpadSelector)) {
      hideMhNumpad({ blur: true });
    }

    if (ohNumpad && !ohNumpad.hidden && !isInsideOhOnlyNumpad(event.target) && !event.target.closest(ohNumpadSelector)) {
      hideOhNumpad({ blur: true });
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('#oh-calc-para, #oh-calc-abortion')) {
      updateParaGravidaOutput();
      return;
    }

    if (event.target.matches('#oh-date-from, #oh-date-to')) {
      updateDateOutput();
      return;
    }

    if (event.target.matches('#calc-edd-lmp')) {
      syncLmpAcrossUi(event.target.value, 'calculator');
      return;
    }

    if (event.target.matches('.js-mh-module .mh-lmp-input')) {
      syncLmpAcrossUi(event.target.value, 'mh');
      return;
    }

    if (event.target.matches(ohNumpadSelector)) {
      positionNumpad(ohNumpad, activeOhNumpadInput);
      return;
    }

    if (event.target.matches(mhNumpadSelector)) {
      positionNumpad(mhNumpad, activeMhNumpadInput);
    }
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches('#oh-calc-current-pregnancy')) {
      updateParaGravidaOutput();
      return;
    }

    if (event.target.matches('.js-oh-module .oh-chart-place-select')) {
      syncOhChartPlaceField(event.target.closest('.oh-chart-row'));
      return;
    }

    if (event.target.matches('#oh-date-target, #oh-date-from, #oh-date-to')) {
      updateDateOutput();
      return;
    }

    if (event.target.matches('#calc-edd-lmp')) {
      syncLmpAcrossUi(event.target.value, 'calculator');
      return;
    }

    if (event.target.matches('.js-mh-module .mh-lmp-input')) {
      const state = syncLmpAcrossUi(event.target.value, 'mh');
      event.target.value = state.lmp;
    }
  });

  document.addEventListener('focus', (event) => {
    const ohInput = event.target.closest(ohNumpadSelector);
    if (ohInput) {
      showOhNumpad(ohInput);
      return;
    }

    const mhInput = event.target.closest(mhNumpadSelector);
    if (mhInput) {
      showMhNumpad(mhInput);
    }
  }, true);

  document.addEventListener('focusin', (event) => {
    if (ohNumpad && !ohNumpad.hidden && !event.target.closest(ohNumpadSelector) && !isInsideOhOnlyNumpad(event.target)) {
      hideOhNumpad();
    }

    if (mhNumpad && !mhNumpad.hidden && !event.target.closest(mhNumpadSelector) && !isInsideMhNumpad(event.target)) {
      hideMhNumpad();
    }
  });

  document.addEventListener('focusout', (event) => {
    if (event.target.matches(mhNumpadSelector)) {
      setTimeout(() => {
        if (!document.activeElement?.closest(mhNumpadSelector) && !isInsideMhNumpad(document.activeElement)) {
          hideMhNumpad();
        }
      }, 0);
      return;
    }

    if (event.target.matches(ohNumpadSelector)) {
      setTimeout(() => {
        if (!document.activeElement?.closest(ohNumpadSelector) && !isInsideOhOnlyNumpad(document.activeElement)) {
          hideOhNumpad();
        }
      }, 0);
    }
  });

  document.addEventListener('blur', (event) => {
    if (event.target.matches(mhNumpadSelector)) {
      setTimeout(() => {
        if (!document.activeElement?.closest(mhNumpadSelector) && !isInsideMhNumpad(document.activeElement)) {
          hideMhNumpad();
        }
      }, 0);
      return;
    }

    if (event.target.matches(ohNumpadSelector)) {
      setTimeout(() => {
        if (!document.activeElement?.closest(ohNumpadSelector) && !isInsideOhOnlyNumpad(document.activeElement)) {
          hideOhNumpad();
        }
      }, 0);
    }
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !getOhCalcModal()?.hidden) {
      closeCalcModal();
      return;
    }

    if (event.key === 'Escape' && !getOhChartModal()?.hidden) {
      closeChartModal();
      return;
    }

    if (event.key === 'Escape') {
      hideOhNumpad();
      hideMhNumpad();
    }
  });

  document.addEventListener('mousedown', (event) => {
    const ohMoveButton = event.target.closest('.js-oh-module .oh-row-move-btn');
    if (ohMoveButton) {
      ohMoveButton.closest('.oh-row')?.setAttribute('data-drag-ready', '1');
      return;
    }

    const ohChartMoveButton = event.target.closest('.js-oh-module .oh-chart-row-move-btn');
    if (ohChartMoveButton) {
      ohChartMoveButton.closest('.oh-chart-row')?.setAttribute('data-drag-ready', '1');
      return;
    }

    const mhMoveButton = event.target.closest('.js-mh-module .oh-row-move-btn');
    if (mhMoveButton) {
      mhMoveButton.closest('.oh-row')?.setAttribute('data-drag-ready', '1');
    }
  });

  document.addEventListener('mouseup', () => {
    getOhTableBody()?.querySelectorAll('.oh-row[data-drag-ready="1"]').forEach((row) => {
      row.removeAttribute('data-drag-ready');
    });
    getOhChartTableBody()?.querySelectorAll('.oh-chart-row[data-drag-ready="1"]').forEach((row) => {
      row.removeAttribute('data-drag-ready');
    });
    getMhTableBody()?.querySelectorAll('.oh-row[data-drag-ready="1"]').forEach((row) => {
      row.removeAttribute('data-drag-ready');
    });
  });

  let ohFloatingDragElement = null;
  let ohGrabOffsetX = 0;
  let ohGrabOffsetY = 0;

  const removeOhFloatingDrag = () => {
    if (ohFloatingDragElement) {
      ohFloatingDragElement.remove();
      ohFloatingDragElement = null;
    }
  };

  const createOhFloatingDrag = (row, tableClass, clientX, clientY) => {
    removeOhFloatingDrag();
    const rect = row.getBoundingClientRect();
    const ghostTable = document.createElement('table');
    ghostTable.className = `${tableClass} zrx-drag-ghost-floating`;
    ghostTable.style.position = 'fixed';
    ghostTable.style.top = `${rect.top}px`;
    ghostTable.style.left = `${rect.left}px`;
    ghostTable.style.width = `${rect.width}px`;
    ghostTable.style.tableLayout = 'fixed';
    ghostTable.style.zIndex = '999999';
    ghostTable.style.pointerEvents = 'none';

    const clonedRow = row.cloneNode(true);
    clonedRow.classList.remove('dragging');

    // Copy exact column widths
    const origCells = row.children;
    const cloneCells = clonedRow.children;
    for (let i = 0; i < origCells.length; i++) {
      const origTd = origCells[i];
      const cloneTd = cloneCells[i];
      if (origTd && cloneTd) {
        const cellRect = origTd.getBoundingClientRect();
        cloneTd.style.width = `${cellRect.width}px`;
        cloneTd.style.minWidth = `${cellRect.width}px`;
        cloneTd.style.maxWidth = `${cellRect.width}px`;
        cloneTd.style.boxSizing = 'border-box';
      }
    }

    const origInputs = row.querySelectorAll('input, textarea');
    const cloneInputs = clonedRow.querySelectorAll('input, textarea');
    origInputs.forEach((inp, idx) => {
      if (cloneInputs[idx]) {
        cloneInputs[idx].value = inp.value;
      }
    });

    const tbody = document.createElement('tbody');
    tbody.appendChild(clonedRow);
    ghostTable.appendChild(tbody);
    document.body.appendChild(ghostTable);

    ohGrabOffsetX = clientX ? (clientX - rect.left) : (rect.width / 2);
    ohGrabOffsetY = clientY ? (clientY - rect.top) : (rect.height / 2);
    ohFloatingDragElement = ghostTable;
  };

  document.addEventListener('dragstart', (event) => {
    const ohRow = event.target.closest('.js-oh-module .oh-row');
    if (ohRow) {
      if (ohRow.getAttribute('data-drag-ready') !== '1') {
        event.preventDefault();
        return;
      }

      ohDragRow = ohRow;
      createOhFloatingDrag(ohRow, 'oh-table', event.clientX, event.clientY);

      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', ohRow.querySelector('.oh-row-no')?.textContent || '');

        try {
          const blankCanvas = document.createElement('canvas');
          blankCanvas.width = 1;
          blankCanvas.height = 1;
          event.dataTransfer.setDragImage(blankCanvas, 0, 0);
        } catch (_) {}
      }
      setTimeout(() => {
        if (ohDragRow) ohDragRow.classList.add('dragging');
      }, 0);
      return;
    }

    const chartRow = event.target.closest('.js-oh-module .oh-chart-row');
    if (chartRow) {
      if (chartRow.getAttribute('data-drag-ready') !== '1') {
        event.preventDefault();
        return;
      }

      ohChartDragRow = chartRow;
      createOhFloatingDrag(chartRow, 'oh-chart-table', event.clientX, event.clientY);

      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', chartRow.querySelector('.oh-chart-row-no')?.textContent || '');

        try {
          const blankCanvas = document.createElement('canvas');
          blankCanvas.width = 1;
          blankCanvas.height = 1;
          event.dataTransfer.setDragImage(blankCanvas, 0, 0);
        } catch (_) {}
      }
      setTimeout(() => {
        if (ohChartDragRow) ohChartDragRow.classList.add('dragging');
      }, 0);
      return;
    }

    const mhRow = event.target.closest('.js-mh-module .oh-row');
    if (!mhRow) {
      return;
    }

    if (mhRow.getAttribute('data-drag-ready') !== '1') {
      event.preventDefault();
      return;
    }

    mhDragRow = mhRow;
    createOhFloatingDrag(mhRow, 'oh-table', event.clientX, event.clientY);

    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', mhRow.querySelector('.oh-row-no')?.textContent || '');

      try {
        const blankCanvas = document.createElement('canvas');
        blankCanvas.width = 1;
        blankCanvas.height = 1;
        event.dataTransfer.setDragImage(blankCanvas, 0, 0);
      } catch (_) {}
    }
    setTimeout(() => {
      if (mhDragRow) mhDragRow.classList.add('dragging');
    }, 0);
  });

  document.addEventListener('dragenter', (event) => {
    if (ohDragRow || ohChartDragRow || mhDragRow) {
      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
      }
    }
  });

  document.addEventListener('dragover', (event) => {
    if (ohDragRow || ohChartDragRow || mhDragRow) {
      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
      }
    }

    if (ohFloatingDragElement && event.clientX && event.clientY) {
      ohFloatingDragElement.style.left = `${event.clientX - ohGrabOffsetX}px`;
      ohFloatingDragElement.style.top = `${event.clientY - ohGrabOffsetY}px`;
    }

    const ohTargetRow = event.target.closest('.js-oh-module .oh-row');
    const ohTbody = getOhTableBody();
    if (ohDragRow && ohTargetRow && ohTbody && ohTargetRow !== ohDragRow) {
      clearDropTargets(ohTbody, '.oh-row.drop-target');

      const rect = ohTargetRow.getBoundingClientRect();
      const insertAfter = event.clientY > rect.top + rect.height / 2;
      if (insertAfter) {
        ohTargetRow.after(ohDragRow);
      } else {
        ohTargetRow.before(ohDragRow);
      }
      renumberOhRows();
      return;
    }

    const ohChartTargetRow = event.target.closest('.js-oh-module .oh-chart-row');
    const ohChartTbody = getOhChartTableBody();
    if (ohChartDragRow && ohChartTargetRow && ohChartTbody && ohChartTargetRow !== ohChartDragRow) {
      event.preventDefault();
      clearDropTargets(ohChartTbody, '.oh-chart-row.drop-target');

      const rect = ohChartTargetRow.getBoundingClientRect();
      const insertAfter = event.clientY > rect.top + rect.height / 2;
      if (insertAfter) {
        ohChartTargetRow.after(ohChartDragRow);
      } else {
        ohChartTargetRow.before(ohChartDragRow);
      }
      renumberOhChartRows();
      return;
    }

    const mhTargetRow = event.target.closest('.js-mh-module .oh-row');
    const mhTbody = getMhTableBody();
    if (!mhDragRow || !mhTargetRow || !mhTbody || mhTargetRow === mhDragRow) {
      return;
    }

    event.preventDefault();
    clearDropTargets(mhTbody, '.oh-row.drop-target');

    const rect = mhTargetRow.getBoundingClientRect();
    const insertAfter = event.clientY > rect.top + rect.height / 2;
    if (insertAfter) {
      mhTargetRow.after(mhDragRow);
    } else {
      mhTargetRow.before(mhDragRow);
    }
    renumberMhRows();
  });

  document.addEventListener('drop', (event) => {
    removeOhFloatingDrag();
    if (ohDragRow) {
      event.preventDefault();
      clearDropTargets(getOhTableBody(), '.oh-row.drop-target');
      renumberOhRows();
      return;
    }

    if (ohChartDragRow) {
      event.preventDefault();
      clearDropTargets(getOhChartTableBody(), '.oh-chart-row.drop-target');
      renumberOhChartRows();
      return;
    }

    if (!mhDragRow) {
      return;
    }

    event.preventDefault();
    clearDropTargets(getMhTableBody(), '.oh-row.drop-target');
    renumberMhRows();
  });

  document.addEventListener('dragend', () => {
    removeOhFloatingDrag();
    ohDragRow?.classList.remove('dragging');
    ohDragRow?.removeAttribute('data-drag-ready');
    ohDragRow = null;
    clearDropTargets(getOhTableBody(), '.oh-row.drop-target');
    renumberOhRows();

    ohChartDragRow?.classList.remove('dragging');
    ohChartDragRow?.removeAttribute('data-drag-ready');
    ohChartDragRow = null;
    clearDropTargets(getOhChartTableBody(), '.oh-chart-row.drop-target');
    renumberOhChartRows();

    mhDragRow?.classList.remove('dragging');
    mhDragRow?.removeAttribute('data-drag-ready');
    mhDragRow = null;
    clearDropTargets(getMhTableBody(), '.oh-row.drop-target');
    renumberMhRows();
  });

  window.addEventListener('resize', () => {
    positionNumpad(ohNumpad, activeOhNumpadInput);
    positionNumpad(mhNumpad, activeMhNumpadInput);
  });

  window.addEventListener('scroll', () => {
    positionNumpad(ohNumpad, activeOhNumpadInput);
    positionNumpad(mhNumpad, activeMhNumpadInput);
  }, true);

  initializeOhUi();
  initializeMhUi();
  refreshLmpDependentFields();
}
