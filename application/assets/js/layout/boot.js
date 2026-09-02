function initializeDynamicDatePickers(root = document) {
  if (typeof flatpickr !== 'function') {
    return;
  }

  root.querySelectorAll('.custom-date-picker').forEach((input) => {
    if (input._flatpickr) {
      return;
    }

    flatpickr(input, {
      dateFormat: 'd/m/Y',
      allowInput: true,
      onChange: (_, dateStr, instance) => {
        instance.input.dispatchEvent(new Event('input', { bubbles: true }));
        instance.input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
}

function initializeSimplePcTableReorder(root = document) {
  const wrapperSelector = [
    '#dx-wrapper',
    '#ix-wrapper',
    '#plan-wrapper',
    '#note-wrapper',
    '#report-entry-wrapper',
    '#uploaded-reports-wrapper',
    '#pe-wrapper',
    '#pc-wrapper',
    '#oh-wrapper',
    '#history-wrapper',
    '#paediatric-history-wrapper',
    '#ph-module-wrapper',
    '#ot-wrapper',
    '#ot-note-wrapper',
    '#advice-wrapper',
    '.advice-wrapper',
    '.ot-wrapper',
    '.reports-wrapper',
    '.rx-wrapper',
    '.pc-wrapper',
    '.oh-wrapper',
    '.zrx-occ-table',
    '.zrx-table',
    '.phrase-template-card',
    '.phrase-table-wrap',
    '.template-table-container',
    '[data-zrx-reorderable]'
  ].join(',');

  const rowSelector = 'tr.pc-row, tr.rx-row, tr.oh-row, tr.ot-row, tr.phrase-row, tr.template-row, .advice-row, tr[draggable="true"]';
  const handleSelector = '.pc-row-move-btn, .rx-row-move-btn, .oh-row-move-btn, .ot-row-move-btn, .zrx-drag-handle, .pc-drag, .rx-drag, .oh-drag, .ot-drag, .adv-drag, .phrase-handle, .instruction-handle, [data-drag-handle]';

  if (document.documentElement.dataset.simplePcReorderReady === '1') {
    return;
  }
  document.documentElement.dataset.simplePcReorderReady = '1';

  let draggedRow = null;
  let draggedTbody = null;
  let floatingDragElement = null;
  let grabOffsetX = 0;
  let grabOffsetY = 0;
  let dragClassTimeout = null;

  function getWrapper(target) {
    return target?.closest?.(wrapperSelector) || null;
  }

  function updateRowNumbers(tbody) {
    if (!tbody) return;
    tbody.querySelectorAll('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl, .row-no, .sl-no').forEach((cell, index) => {
      cell.textContent = index + 1;
    });
  }

  function clearDropTargets(tbody) {
    if (!tbody) return;
    tbody.querySelectorAll('.drop-target').forEach((row) => {
      row.classList.remove('drop-target');
    });
  }

  const removeFloatingDrag = () => {
    if (dragClassTimeout) {
      clearTimeout(dragClassTimeout);
      dragClassTimeout = null;
    }
    if (floatingDragElement) {
      floatingDragElement.remove();
      floatingDragElement = null;
    }
    document.querySelectorAll('.dragging').forEach((r) => r.classList.remove('dragging'));
    document.querySelectorAll('.zrx-drag-ghost-floating').forEach((el) => el.remove());
  };

  const createFloatingDrag = (row, clientX, clientY) => {
    removeFloatingDrag();
    const rect = row.getBoundingClientRect();
    const isTable = row.tagName === 'TR';
    const table = isTable ? row.closest('table') : null;
    let ghostElement;

    if (isTable) {
      const ghostTable = document.createElement('table');
      ghostTable.className = (table ? table.className : 'pc-table') + ' zrx-drag-ghost-floating';
      ghostTable.style.position = 'fixed';
      ghostTable.style.top = `${rect.top}px`;
      ghostTable.style.left = `${rect.left}px`;
      ghostTable.style.width = `${rect.width}px`;
      ghostTable.style.tableLayout = 'fixed';
      ghostTable.style.zIndex = '999999';
      ghostTable.style.pointerEvents = 'none';
      ghostTable.style.opacity = '0.92';
      ghostTable.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.2)';
      ghostTable.style.background = '#ffffff';

      const clonedRow = row.cloneNode(true);
      clonedRow.classList.remove('dragging', 'drop-target');

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

      const origInputs = row.querySelectorAll('input, textarea, select');
      const cloneInputs = clonedRow.querySelectorAll('input, textarea, select');
      origInputs.forEach((inp, idx) => {
        if (cloneInputs[idx]) {
          cloneInputs[idx].value = inp.value;
        }
      });

      const tbody = document.createElement('tbody');
      tbody.appendChild(clonedRow);
      ghostTable.appendChild(tbody);
      ghostElement = ghostTable;
    } else {
      const clonedDiv = row.cloneNode(true);
      clonedDiv.classList.remove('dragging', 'drop-target');
      clonedDiv.classList.add('zrx-drag-ghost-floating');
      clonedDiv.style.position = 'fixed';
      clonedDiv.style.top = `${rect.top}px`;
      clonedDiv.style.left = `${rect.left}px`;
      clonedDiv.style.width = `${rect.width}px`;
      clonedDiv.style.zIndex = '999999';
      clonedDiv.style.pointerEvents = 'none';
      clonedDiv.style.opacity = '0.92';
      clonedDiv.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.2)';
      clonedDiv.style.background = '#ffffff';

      const origInputs = row.querySelectorAll('input, textarea, select');
      const cloneInputs = clonedDiv.querySelectorAll('input, textarea, select');
      origInputs.forEach((inp, idx) => {
        if (cloneInputs[idx]) {
          cloneInputs[idx].value = inp.value;
        }
      });
      ghostElement = clonedDiv;
    }

    document.body.appendChild(ghostElement);

    grabOffsetX = clientX ? (clientX - rect.left) : (rect.width / 2);
    grabOffsetY = clientY ? (clientY - rect.top) : (rect.height / 2);
    floatingDragElement = ghostElement;
  };

  const armRowForDrag = (event) => {
    const moveButton = event.target.closest(handleSelector);
    const wrapper = getWrapper(moveButton);
    if (!moveButton || !wrapper) {
      return;
    }
    moveButton.closest(rowSelector)?.setAttribute('data-drag-ready', '1');
  };

  document.addEventListener('pointerdown', armRowForDrag);
  document.addEventListener('mousedown', armRowForDrag);

  document.addEventListener('dragstart', (event) => {
    const row = event.target.closest(rowSelector);
    const wrapper = getWrapper(row);
    if (!row || !wrapper) {
      return;
    }

    if (row.dataset.dragReady !== '1') {
      const handle = event.target.closest(handleSelector);
      if (!handle) {
        event.preventDefault();
        return;
      }
    }

    draggedRow = row;
    draggedTbody = row.parentElement;
    createFloatingDrag(row, event.clientX, event.clientY);

    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.querySelector('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl')?.textContent || row.getAttribute('data-name') || '');

      try {
        const blankCanvas = document.createElement('canvas');
        blankCanvas.width = 1;
        blankCanvas.height = 1;
        event.dataTransfer.setDragImage(blankCanvas, 0, 0);
      } catch (_) {}
    }

    if (dragClassTimeout) clearTimeout(dragClassTimeout);
    dragClassTimeout = setTimeout(() => {
      if (draggedRow) draggedRow.classList.add('dragging');
    }, 0);
  });

  document.addEventListener('dragenter', (event) => {
    if (draggedRow) {
      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
      }
    }
  });

  document.addEventListener('dragover', (event) => {
    if (!draggedRow || !draggedTbody) {
      return;
    }

    event.preventDefault();
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = 'move';
    }

    if (floatingDragElement && event.clientX && event.clientY) {
      floatingDragElement.style.left = `${event.clientX - grabOffsetX}px`;
      floatingDragElement.style.top = `${event.clientY - grabOffsetY}px`;
    }

    const targetRow = event.target.closest(rowSelector);
    if (!targetRow || targetRow === draggedRow || targetRow.parentElement !== draggedTbody || !getWrapper(targetRow)) {
      return;
    }

    clearDropTargets(draggedTbody);
    targetRow.classList.add('drop-target');

    const rect = targetRow.getBoundingClientRect();
    const insertAfter = event.clientY > rect.top + rect.height / 2;
    if (insertAfter) {
      targetRow.after(draggedRow);
    } else {
      targetRow.before(draggedRow);
    }
    updateRowNumbers(draggedTbody);
  });

  document.addEventListener('drop', (event) => {
    removeFloatingDrag();
    if (draggedRow) {
      event.preventDefault();
    }
    if (draggedTbody) {
      clearDropTargets(draggedTbody);
      updateRowNumbers(draggedTbody);
      draggedTbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
    }
  });

  document.addEventListener('dragend', () => {
    removeFloatingDrag();
    draggedRow?.classList.remove('dragging');
    draggedRow?.removeAttribute('data-drag-ready');
    clearDropTargets(draggedTbody);
    updateRowNumbers(draggedTbody);
    document.querySelectorAll(rowSelector).forEach((r) => {
      r.classList.remove('dragging', 'drop-target');
      r.removeAttribute('data-drag-ready');
    });
    draggedRow = null;
    draggedTbody = null;
  });
}

// Auto-initialize based on the DOM elements present.
document.addEventListener('DOMContentLoaded', async () => {
  document.addEventListener('click', (event) => {
    const interactiveTarget = event.target.closest('input, textarea, select, button, a, .rx-dropdown, .patient-lookup-list');
    if (!interactiveTarget) {
      const cell = event.target.closest('.rx-table td, .pc-table td');
      const field = cell?.querySelector(':scope > .rx-input, :scope > .pc-input');
      if (field && !field.disabled && !field.readOnly) {
        field.focus({ preventScroll: true });
        if (typeof field.setSelectionRange === 'function' && typeof field.value === 'string') {
          const caret = field.value.length;
          field.setSelectionRange(caret, caret);
        }
        return;
      }
    }

    const trigger = event.target.closest('.zimrx-date-trigger');
    if (!trigger) {
      return;
    }

    const input = trigger.parentElement?.querySelector('.custom-date-picker');
    if (!input) {
      return;
    }

    event.preventDefault();
    input.focus();
    input._flatpickr?.open();
  });

  if (typeof openPrescriptionPreview === 'function') {
    document.addEventListener('click', openPrescriptionPreview);
  }

  if (typeof learnRxRegimensOnSave === 'function') {
    document.addEventListener('click', learnRxRegimensOnSave);
  }

  // Initialize core input and form handlers immediately
  initializeDynamicDatePickers();
  initializeSimplePcTableReorder();
  initializeUniversalGridNavigation();

  if (typeof initPatientReferredByControl === 'function') {
    initPatientReferredByControl();
  }

  if (document.getElementById('sidebar-modules')) {
    try {
      await renderMainUI();
    } catch (err) {
      console.error('renderMainUI error:', err);
    }

    if (typeof initRxAutocomplete === 'function') {
      initRxAutocomplete();
    }

    if (typeof initPcAutocomplete === 'function') {
      initPcAutocomplete();
    }

    if (typeof initOhModule === 'function') {
      initOhModule();
    }

    if (typeof initPaediatricModule === 'function') {
      initPaediatricModule();
    }

    if (typeof initHoDietDropdown === 'function') {
      initHoDietDropdown();
    }
  }

  if (document.getElementById('left-side-setup')) {
    // Selects are pre-rendered by PHP — just wire the buttons
    const saveBtn = document.getElementById('btn-save-settings');
    const resetBtn = document.getElementById('btn-reset-settings');
    if (saveBtn) saveBtn.addEventListener('click', saveSettings);
    if (resetBtn) resetBtn.addEventListener('click', resetSettings);
  }

  initializeHelpGuidelineModals();
});

function showHelpGuidelineModal(type) {
  const guidelines = {
    'pres-reg': {
      title: 'রেজিস্ট্রেশন নম্বর (Reg No) নির্দেশিকা',
      badge: 'প্রেসক্রিপশন পেজ',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে রেজিস্ট্রেশন নম্বর লিখে সার্চ করুন বা বারকোড স্ক্যান করুন বা রেজিস্ট্রেশন নম্বর কাছে না পেলে, ফোন নম্বরের ফিল্ড থেকেও সার্চ করতে পারেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে ব্লাঙ্ক রাখুন এবং অন্যান্য particulars পূরণ করুন বা পুরো প্রেসক্রিপশনটাই লিখে ফেলুন। <strong>Save & Print</strong> বা <strong>Save Only</strong> করার পরে অটোমেটিক রেজিস্ট্রেশন নাম্বার assign হবে।
          </div>
        </div>
      `
    },
    'appt-reg': {
      title: 'রেজিস্ট্রেশন নম্বর (Reg No) নির্দেশিকা',
      badge: 'অ্যাপয়েন্টমেন্ট পেজ',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে রেজিস্ট্রেশন নম্বর লিখে সার্চ করুন বা বারকোড স্ক্যান করুন বা রেজিস্ট্রেশন নম্বর কাছে না পেলে, ফোন নম্বরের ফিল্ড থেকেও সার্চ করতে পারেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে ব্লাঙ্ক রাখুন এবং অন্যান্য particulars পূরণ করুন। <strong>Save Appointment</strong> করার পরে অটোমেটিক রেজিস্ট্রেশন নাম্বার assign হবে।
          </div>
        </div>
      `
    },
    'mobile': {
      title: 'ফোন নম্বর (Mobile) নির্দেশিকা',
      badge: 'সার্চ ও রেজিস্ট্রেশন',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে ফোন নম্বর লিখে সার্চ করুন, রেজিস্ট্রেশন নম্বর থাকলে তা দিয়েও সার্চ করতে পারেন বা বারকোড স্ক্যান করুন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে নতুন ফোন নম্বর লিখে ফেলুন, একই নম্বরে অলরেডি আরেকজনের রেজিস্ট্রেশন থেকে থাকলে ড্রপডাউন থেকে <strong>It's a new patient</strong> এ ক্লিক করুন, তাহলে একই নাম্বারে দুই পেশেন্টই রেজিস্ট্রার্ড থাকবে, তবে রেজিস্ট্রেশন নম্বর আলাদা হবে।
          </div>
        </div>
      `
    },
    'col-resize': {
      title: 'টেবিল লেআউট নির্দেশিকা',
      badge: 'কলাম রিসাইজ ও লেআউট',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            Resize করা এই লেআউটটি সেইভ করতে <strong>Save layout</strong> করুন। এবং Default Layout এ যেতে <strong>Reset</strong> করুন।
          </div>
        </div>
      `
    },
    'dob': {
      title: 'জন্মতারিখ ও বয়স (DOB & Age) নির্দেশিকা',
      badge: 'পেশেন্ট পার্টিকুলার্স ও ক্যালকুলেশন',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">★</div>
          <div class="zrx-help-step-text">
            <strong>Age বা DOB যেকোনো একটি ইনপুট দিলেই হবে।</strong> যেকোনো একটি ইনপুট দিলে অন্যটি স্বয়ংক্রিয়ভাবে সেট হয়ে যাবে।
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>DOB এন্ট্রি করলে Age ও Unit অটো-ক্যালকুলেট হবে:</strong><br>
            ক্যালেন্ডার থেকে Date of Birth সিলেক্ট করলে, সিস্টেম নিজে থেকেই রোগীর বয়স এবং সঠিক Unit নির্ধারণ করে নিবে:
            <ul style="margin: 6px 0 0 16px; padding: 0; line-height: 1.6;">
              <li><strong>১ বছর বা তার বেশি (&ge; 1 Year):</strong> Age দেখাবে পূর্ণ বছরে এবং <code>Unit = "Years"</code> সিলেক্ট হবে।</li>
              <li><strong>১ বছরের কম, কিন্তু ১ মাসের বেশি:</strong> Age দেখাবে মাসে এবং <code>Unit = "Months"</code> সিলেক্ট হবে।</li>
              <li><strong>১ মাসের কম, কিন্তু ৭ দিনের বেশি:</strong> Age দেখাবে সপ্তাহে এবং <code>Unit = "Weeks"</code> সিলেক্ট হবে।</li>
              <li><strong>৭ দিনের কম (নবজাতক):</strong> Age দেখাবে এক্স্যাক্ট দিনে এবং <code>Unit = "Days"</code> সিলেক্ট হবে।</li>
            </ul>
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>Age এন্ট্রি করলে আনুমানিক DOB সেট হবে:</strong><br>
            আপনি যদি শুধু বয়স টাইপ করে Unit সিলেক্ট করেন, তবে সিস্টেম ব্যাকএন্ডে একটি আনুমানিক (Estimated) DOB তৈরি করে নিবে:
            <ul style="margin: 6px 0 0 16px; padding: 0; line-height: 1.6;">
              <li><strong>Years (যেমন: 25 Years):</strong> ওই হিসাবকৃত বছরের শুরুর তারিখটিকে (যেমন: ০১/০১/XXXX) DOB হিসেবে সেট করবে।</li>
              <li><strong>Months (যেমন: 6 Months):</strong> ৬ মাস আগের মাসের ১ তারিখকে DOB হিসেবে ধরবে।</li>
              <li><strong>Weeks (যেমন: 3 Weeks):</strong> বর্তমান তারিখ থেকে ২১ দিন (৩ &times; ৭) মাইনাস করে DOB সেট করবে।</li>
              <li><strong>Days (যেমন: 5 Days):</strong> বর্তমান তারিখ থেকে ৫ দিন মাইনাস করে DOB সেট করবে।</li>
            </ul>
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            <strong>ভবিষ্যৎ ভিজিট ও পিডিয়াট্রিক সুবিধা:</strong> পরবর্তীতে এই সেইম রোগীই আসলে ডায়নামিক ভাবে বয়স হিসাব করে প্রেস্ক্রিপশনে বসিয়ে দিবে অটোমেটিক। এছাড়া বাচ্চাদের গ্রোথ চার্ট (Growth chart) এবং ভ্যাকসিনেশনের (Vaccination) সিডিউল তৈরিতেও হেল্প করবে।
          </div>
        </div>
      `
    },
    'visit-id': {
      title: 'ভিজিট আইডি (Visit ID) নির্দেশিকা',
      badge: 'প্রেসক্রিপশন ও EMR রেকর্ড',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>প্রেসক্রিপশন ট্র্যাকিং ও হিস্ট্রি:</strong> ভিজিট আইডিটি প্রেসক্রিপশনের সাথে জড়িত। আপনার লেখা প্রতিটি প্রেসক্রিপশনের একটি ইউনিক ভিজিট আইডি আছে যা দিয়ে এক্সাক্টলি ঐ কনসালটেশনের সময় কি প্রেসক্রিপশন করেছিলেন তা দেখতে পাবেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>স্বয়ংক্রিয় ইউনিক কোড:</strong> প্রেসক্রিপশন সংরক্ষণ (Save) করার সময় সিস্টেম নিজে থেকেই এই ইউনিক কোড তৈরি করে নেয়। এটি সম্পূর্ণরূপে স্বয়ংক্রিয় হওয়ায় এটি ম্যানুয়ালি এন্ট্রি বা পরিবর্তন করার প্রয়োজন নেই।
          </div>
        </div>
      `
    },
    'occupation': {
      title: 'পেশা (Occupation) নির্দেশিকা ও ম্যানেজমেন্ট',
      badge: 'পেশেন্ট পার্টিকুলার্স ও EMR',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            রেগুলার প্র্যাক্টিসের সময় সাধারণত Occupational History রেকর্ড করে না, ভার্বালি জেনে নেয়া হয়, অন্যান্য প্রেসক্রিপশন সফটওয়্যারেও এই ফিচারটি নেই। তবে একটি Comprehensive EMR-এর জন্য ZimRx-এ এটি অ্যাড করা হয়েছে। এর মাধ্যমে পেশেন্টের Socioeconomic অবস্থার পাশাপাশি, তার Presenting Complaints-এর সাথে কোনো Occupational Relation আছে কি না, তা সহজেই অ্যাসেস করা যাবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আপনার সুবিধার্থে ড্রপ-ডাউন মেনুতে কিছু কমন Occupation দেওয়া আছে। চাইলে ম্যানুয়ালি টাইপ করেও নতুন Occupation এন্ট্রি করতে পারবেন। একবার এন্ট্রি করলে এটি অটো-সেইভ হয়ে থাকবে এবং পরবর্তীতে আপনার ভবিষ্যতের অন্যান্য সকল প্রেসক্রিপশনে তা Show করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            ড্রপ-ডাউন মেনুর sorting মেইনলি Usage এর উপর নির্ভর করবে, যেটা যত বেশি বার ইউস হয়েছে তা তত উপরে শো করবে। Usage দেখার জন্য নিচের টেবিলটি দেখুন, এখান থেকে Reset to default করতে পারবেন, ডিলিট করতে পারবেন, নতুন এন্ট্রি সরাসরি এখান থেকেই যোগ করতে পারবেন, বা কোনোটা সবসময় প্রথমে দেখাতে চাইলে pin করে রাখতে পারবেন।
          </div>
        </div>

        <div class="zrx-occ-toolbar">
          <div class="zrx-occ-add-form">
            <input type="text" id="zrx-new-occ-input" placeholder="নতুন পেশা (New Occupation) লিখুন..." autocomplete="off">
            <button type="button" id="zrx-btn-add-occ" class="zrx-btn-primary">+ Add</button>
          </div>
          <button type="button" id="zrx-btn-reset-occ" class="zrx-btn-outline" title="Reset all occupation customizations to default">Reset to Default</button>
        </div>

        <table class="zrx-occ-table">
          <thead>
            <tr>
              <th style="width: 44px; text-align: center;">Move</th>
              <th style="width: 190px; text-align: center;">Actions</th>
              <th style="width: 85px; text-align: center;">Type</th>
              <th style="text-align: left;">Occupation Name</th>
              <th style="width: 65px; text-align: center;">Usage</th>
            </tr>
          </thead>
          <tbody id="zrx-occ-table-body">
            <tr><td colspan="5" style="text-align:center; padding:15px; color:#64748b;">Loading occupations...</td></tr>
          </tbody>
        </table>
      `
    },
    'address': {
      title: 'ঠিকানা (Address) নির্দেশিকা ও ম্যানেজমেন্ট',
      badge: 'পেশেন্ট পার্টিকুলার্স ও জিওগ্রাফি',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>কমা (,) ম্যাজিক ও Smart Prediction:</strong> আপনি জায়গার নাম লিখে কমা (<code>,</code>) দিলে সিস্টেম অটোমেটিক্যালি এর পরের প্যারেন্ট লোকেশনটি বুঝে যায়। যেমন, আপনি <code>Savar</code> টাইপ করার সাথে সাথেই সিস্টেম নিজে থেকে <code>Dhaka</code> সাজেস্ট করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>জাতীয় ডাটাবেস ও কাস্টম এড্রেস:</strong> সাজেশন লিস্টটি একসাথে দুটি জায়গা থেকে ডাটা দেখায়:
            <ul style="margin: 4px 0 0 16px; padding: 0; line-height: 1.5;">
              <li>বাংলাদেশের সমস্ত জেলা, উপজেলা, থানা, ইউনিয়ন ও পোস্ট কোড (English &amp; বাংলা)।</li>
              <li>আপনার নিজের সেইভ করা বা বেশি ব্যবহৃত কাস্টম ঠিকানাগুলো।</li>
            </ul>
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            <strong>ব্যবহার অনুযায়ী স্মার্ট র‍্যাংকিং:</strong> আপনি যে ঠিকানাগুলো সবচেয়ে বেশি ব্যবহার করেন (Usage frequency) বা যেগুলো হুবহু মিলে যায় (Exact match), সেগুলো স্বয়ংক্রিয়ভাবে সাজেশনের একেবারে উপরে (Top) শো করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৪</div>
          <div class="zrx-help-step-text">
            <strong>প্র্যাক্টিস জেলা ফিল্টারিং ও ম্যানেজমেন্ট:</strong> এছাড়া আপনি জাস্ট নির্দিষ্ট একটা জেলাতে প্র্যাক্টিস করতে চাইলে জাস্ট ঐ জেলা বা আশে-পাশের জেলাগুলোও সিলেক্ট করে রাখতে পারেন, তাহলে অন্যান্য জেলা আর দেখাবে না। নিচের টেবিল থেকে আপনি যায়গার নাম ডিলিট, এডিট, রিসেট, পিন এবং কাস্টম এড্রেসও যুক্ত করতে পারেন।
          </div>
        </div>

        <!-- Section A: Preferred Districts -->
        <div class="zrx-addr-section">
          <div class="zrx-addr-section-header">
            <div class="zrx-addr-section-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              প্র্যাক্টিস জেলা ফিল্টার (Practice Districts Filter)
            </div>
            <div style="display:flex;gap:6px;">
              <button type="button" id="zrx-btn-dist-all" class="zrx-btn-outline" style="height:28px;font-size:0.75rem;padding:0 8px;">All (সমগ্র বাংলাদেশ)</button>
              <button type="button" id="zrx-btn-dist-save" class="zrx-btn-primary" style="height:28px;font-size:0.75rem;padding:0 10px;">Save Districts</button>
            </div>
          </div>
          <div class="zrx-district-search-bar">
            <input type="text" id="zrx-district-filter-input" placeholder="জেলা সার্চ করুন (Search District)..." autocomplete="off">
          </div>
          <div class="zrx-district-chips-wrap" id="zrx-district-chips-container">
            <span style="color:#64748b;font-size:0.8rem;">Loading districts...</span>
          </div>
        </div>

        <!-- Section B: Custom & System Addresses Table -->
        <div class="zrx-addr-section">
          <div class="zrx-addr-section-header">
            <div class="zrx-addr-section-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              ঠিকানা ডিরেক্টরি ও ম্যানেজমেন্ট (Address Directory — Top 100)
            </div>
            <button type="button" id="zrx-btn-reset-addr" class="zrx-btn-outline" style="height:28px;font-size:0.75rem;padding:0 10px;" title="Reset all address customizations to default">Reset to Default</button>
          </div>

          <!-- Search & Filter Controls -->
          <div class="zrx-addr-filter-row">
            <div class="zrx-addr-search-wrap">
              <input type="text" id="zrx-addr-search-input" placeholder="ঠিকানা সার্চ করুন (Search locality, thana, upazila, district)..." autocomplete="off">
            </div>
            <div class="zrx-addr-filter-tabs" id="zrx-addr-filter-tabs-container">
              <button type="button" class="zrx-addr-tab-btn active" data-addr-filter="all">All (Top 100)</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="custom">Custom Only</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="system">System (National)</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="pinned">Pinned</button>
            </div>
          </div>

          <div class="zrx-occ-toolbar" style="margin-top:8px;">
            <div class="zrx-occ-add-form">
              <input type="text" id="zrx-new-addr-input" placeholder="নতুন কাস্টম ঠিকানা (New Address/Area) লিখুন..." autocomplete="off">
              <button type="button" id="zrx-btn-add-addr" class="zrx-btn-primary">+ Add</button>
            </div>
          </div>

          <table class="zrx-occ-table" style="margin-top:8px;">
            <thead>
              <tr>
                <th style="width: 170px; text-align: center;">Actions</th>
                <th style="width: 80px; text-align: center;">Type</th>
                <th style="text-align: left;">Address / Combination</th>
                <th style="width: 65px; text-align: center;">Usage</th>
              </tr>
            </thead>
            <tbody id="zrx-addr-table-body">
              <tr><td colspan="4" style="text-align:center; padding:15px; color:#64748b;">Loading addresses...</td></tr>
            </tbody>
          </table>
        </div>
      `
    }
  };

  const data = guidelines[type];
  if (!data) return;

  let overlay = document.getElementById('zrx-help-modal-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'zrx-help-modal-overlay';
    overlay.className = 'zrx-help-modal-overlay';
    overlay.innerHTML = `
      <div class="zrx-help-modal-card" id="zrx-help-modal-card" role="dialog" aria-modal="true">
        <div class="zrx-help-modal-header">
          <div class="zrx-help-modal-title-wrap">
            <span class="zrx-help-modal-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </span>
            <div>
              <h3 id="zrx-help-modal-title"></h3>
              <span id="zrx-help-modal-badge" class="zrx-help-modal-badge"></span>
            </div>
          </div>
          <button type="button" class="zrx-help-modal-close" id="zrx-help-modal-close-btn" aria-label="Close">✕</button>
        </div>
        <div class="zrx-help-modal-body" id="zrx-help-modal-body"></div>
        <div class="zrx-help-modal-footer">
          <button type="button" class="zrx-help-modal-btn" id="zrx-help-modal-ok-btn">বুঝেছি</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const closeHelp = () => {
      overlay.classList.remove('active');
    };

    overlay.querySelector('#zrx-help-modal-close-btn').addEventListener('click', closeHelp);
    overlay.querySelector('#zrx-help-modal-ok-btn').addEventListener('click', closeHelp);
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeHelp();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('active')) closeHelp();
    });
  }

  const card = overlay.querySelector('#zrx-help-modal-card');
  if (card) {
    if (data.wide) card.classList.add('zrx-help-modal-wide');
    else card.classList.remove('zrx-help-modal-wide');
  }

  document.getElementById('zrx-help-modal-title').textContent = data.title;
  document.getElementById('zrx-help-modal-badge').textContent = data.badge;
  document.getElementById('zrx-help-modal-body').innerHTML = data.body;
  overlay.classList.add('active');

  if (type === 'occupation') {
    const escapeHtml = (value) => {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    };

    let draggingRow = null;

    const loadOccupationTable = () => {
      const tbody = document.getElementById('zrx-occ-table-body');
      if (!tbody) return;

      fetch('api/occupation_settings.php?action=list')
        .then(res => res.json())
        .then(resData => {
          const list = resData.occupations || [];
          if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:15px; color:#64748b;">No occupations found.</td></tr>';
            return;
          }

          tbody.innerHTML = list.map(occ => {
            const isPinned = Number(occ.is_pinned) === 1;
            const isHidden = Number(occ.is_hidden) === 1;
            const isSystem = occ.kind === 'system';
            const moveIcon = typeof ZimRxIcon !== 'undefined' 
              ? ZimRxIcon.render('move', 14) 
              : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>';
            
            return `
              <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-name="${escapeHtml(occ.name)}" draggable="true">
                <td class="pc-action pc-move phrase-handle" style="position: relative; width: 44px; text-align: center; padding: 0 !important; height: 100%;">
                  ${isPinned ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned" style="position:absolute;top:4px;left:4px;width:14px;height:14px;object-fit:contain;pointer-events:none;z-index:2;">' : ''}
                  <button type="button" class="pc-row-move-btn zrx-drag-handle" title="Move Row">${moveIcon}</button>
                </td>
                <td style="text-align: center;">
                  <div class="phrase-actions" style="display:flex;gap:4px;justify-content:center;">
                    <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-occ-action="pin" data-name="${escapeHtml(occ.name)}" style="${isPinned ? 'background:#fef3c7;border-color:#f59e0b;color:#b45309;font-weight:700;' : ''}">${isPinned ? 'Unpin' : 'Pin'}</button>
                    <button type="button" class="phrase-btn" data-occ-action="edit" data-name="${escapeHtml(occ.name)}">Edit</button>
                    ${isSystem
                      ? (isHidden
                          ? `<button type="button" class="phrase-btn primary" data-occ-action="toggle_hide" data-name="${escapeHtml(occ.name)}">Restore</button>`
                          : `<button type="button" class="phrase-btn danger" data-occ-action="toggle_hide" data-name="${escapeHtml(occ.name)}">Remove</button>`)
                      : `<button type="button" class="phrase-btn danger" data-occ-action="delete" data-name="${escapeHtml(occ.name)}">Delete</button>`
                    }
                  </div>
                </td>
                <td style="text-align: center;">
                  <div class="phrase-tags" style="justify-content: center;">
                    <span class="phrase-tag ${isSystem ? 'system' : 'custom'}">${isSystem ? 'System' : 'Custom'}</span>
                    ${isHidden ? '<span class="phrase-tag hidden" style="background:#fee2e2;color:#b91c1c;margin-left:4px;">Hidden</span>' : ''}
                  </div>
                </td>
                <td>
                  <div class="phrase-text" style="font-weight:600;color:${isHidden ? '#94a3b8' : '#0f172a'};">${escapeHtml(occ.name)}</div>
                </td>
                <td class="phrase-usage" style="text-align: center; font-weight: 700; color: #1d4ed8;">
                  ${Number(occ.usage_count || 0)}
                </td>
              </tr>
            `;
          }).join('');

          if (typeof window.refreshOccupations === 'function') {
            window.refreshOccupations();
          }

          // Bind Action Buttons
          tbody.querySelectorAll('[data-occ-action="pin"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_pin', name })
              }).then(() => loadOccupationTable());
            };
          });

          tbody.querySelectorAll('[data-occ-action="edit"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              const newName = prompt('Edit occupation name:', name);
              if (newName && newName.trim() && newName.trim() !== name) {
                fetch('api/occupation_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'edit', name, new_name: newName.trim() })
                }).then(() => loadOccupationTable());
              }
            };
          });

          tbody.querySelectorAll('[data-occ-action="toggle_hide"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_hide', name })
              }).then(() => loadOccupationTable());
            };
          });

          tbody.querySelectorAll('[data-occ-action="delete"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              if (confirm(`Are you sure you want to delete "${name}"?`)) {
                fetch('api/occupation_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete', name })
                }).then(() => loadOccupationTable());
              }
            };
          });

          // Row drag-and-drop
          tbody.querySelectorAll('tr.phrase-row').forEach(row => {
            row.addEventListener('dragstart', (e) => {
              draggingRow = row;
              e.dataTransfer.effectAllowed = 'move';
              row.classList.add('dragging');
            });

            row.addEventListener('dragover', (e) => {
              e.preventDefault();
              e.dataTransfer.dropEffect = 'move';
              const targetRow = e.target.closest('tr.phrase-row');
              if (targetRow && targetRow !== draggingRow) {
                const rect = targetRow.getBoundingClientRect();
                const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                tbody.insertBefore(draggingRow, next ? targetRow.nextSibling : targetRow);
              }
            });

            row.addEventListener('dragend', () => {
              if (draggingRow) {
                draggingRow.classList.remove('dragging');
                draggingRow = null;
                tbody.dispatchEvent(new CustomEvent('zrx:reordered'));
              }
            });
          });

          tbody.onreorder = () => {

            fetch('api/occupation_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'reorder', names })
            }).then(() => {
              if (typeof window.refreshOccupations === 'function') {
                window.refreshOccupations();
              }
            });
          };

          tbody.addEventListener('zrx:reordered', tbody.onreorder);
        })
        .catch(() => {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:15px; color:#ef4444;">Failed to load occupations.</td></tr>';
        });
    };

    loadOccupationTable();

    const addBtn = overlay.querySelector('#zrx-btn-add-occ');
    const addInput = overlay.querySelector('#zrx-new-occ-input');
    if (addBtn && addInput) {
      const doAdd = () => {
        const name = addInput.value.trim();
        if (!name) return;
        fetch('api/occupation_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', name })
        }).then(() => {
          addInput.value = '';
          loadOccupationTable();
        });
      };
      addBtn.onclick = doAdd;
      addInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          doAdd();
        }
      };
    }

    const resetBtn = overlay.querySelector('#zrx-btn-reset-occ');
    if (resetBtn) {
      resetBtn.onclick = () => {
        if (confirm('Reset all occupation customizations to default? This will unpin, unhide, and restore all system occupations.')) {
          fetch('api/occupation_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
          }).then(() => loadOccupationTable());
        }
      };
    }
  }

  if (type === 'address') {
    const escapeHtml = (value) => {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    };

    let allDistricts = [];
    let selectedDistrictNames = [];
    let currentFilter = 'all';
    let currentSearchQ = '';
    let searchDebounceTimer = null;

    const loadAddressSettings = () => {
      const tbody = document.getElementById('zrx-addr-table-body');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px; color:#64748b;">Loading addresses (Top 100)...</td></tr>';
      }

      const url = `api/address_settings.php?action=list&q=${encodeURIComponent(currentSearchQ)}&filter=${encodeURIComponent(currentFilter)}`;

      fetch(url)
        .then(res => res.json())
        .then(resData => {
          if (!resData.ok) return;

          // 1. Render Districts Chips (only if not already loaded)
          if (!allDistricts.length) {
            allDistricts = resData.districts || [];
            selectedDistrictNames = resData.preferred_districts || [];
            renderDistrictChips();
          }

          // 2. Render Custom & System Addresses Table (Top 100)
          const list = resData.addresses || [];
          if (!tbody) return;

          if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px; color:#64748b;">No matching addresses found. Try typing another search keyword or add a new custom address.</td></tr>';
            return;
          }

          tbody.innerHTML = list.map(addr => {
            const isPinned = Number(addr.is_pinned) === 1;
            const isHidden = Number(addr.is_hidden) === 1;
            const isSystem = addr.kind === 'system';

            const actionsHtml = isSystem
              ? `<span style="color:#94a3b8;font-size:0.75rem;font-style:italic;">National Database</span>`
              : `
                <div class="phrase-actions" style="display:flex;gap:4px;justify-content:center;">
                  <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-addr-action="pin" data-name="${escapeHtml(addr.name)}" style="${isPinned ? 'background:#fef3c7;border-color:#f59e0b;color:#b45309;font-weight:700;' : ''}">${isPinned ? 'Unpin' : 'Pin'}</button>
                  <button type="button" class="phrase-btn" data-addr-action="edit" data-name="${escapeHtml(addr.name)}">Edit</button>
                  <button type="button" class="phrase-btn ${isHidden ? 'primary' : ''}" data-addr-action="toggle_hide" data-name="${escapeHtml(addr.name)}">${isHidden ? 'Unhide' : 'Hide'}</button>
                  <button type="button" class="phrase-btn danger" data-addr-action="delete" data-name="${escapeHtml(addr.name)}">Delete</button>
                </div>
              `;

            return `
              <tr class="pc-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}">
                <td style="text-align: center;">
                  ${actionsHtml}
                </td>
                <td style="text-align: center;">
                  <div class="phrase-tags" style="justify-content: center;">
                    <span class="phrase-tag ${isSystem ? 'system' : 'custom'}">${isSystem ? 'System' : 'Custom'}</span>
                    ${isHidden ? '<span class="phrase-tag hidden" style="background:#fee2e2;color:#b91c1c;margin-left:4px;">Hidden</span>' : ''}
                  </div>
                </td>
                <td>
                  <div class="phrase-text" style="font-weight:600;color:${isHidden ? '#94a3b8' : '#0f172a'};">${escapeHtml(addr.name)}</div>
                </td>
                <td style="text-align: center; font-weight: 700; color: #1d4ed8;">
                  ${isSystem ? '—' : Number(addr.usage_count || 0)}
                </td>
              </tr>
            `;
          }).join('');

          // Bind Custom Address Actions
          tbody.querySelectorAll('[data-addr-action="pin"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/address_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_pin', name })
              }).then(() => loadAddressSettings());
            };
          });

          tbody.querySelectorAll('[data-addr-action="edit"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              const newName = prompt('Edit address/locality name:', name);
              if (newName && newName.trim() && newName.trim() !== name) {
                fetch('api/address_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'edit', name, new_name: newName.trim() })
                }).then(() => loadAddressSettings());
              }
            };
          });

          tbody.querySelectorAll('[data-addr-action="toggle_hide"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/address_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_hide', name })
              }).then(() => loadAddressSettings());
            };
          });

          tbody.querySelectorAll('[data-addr-action="delete"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              if (confirm(`Are you sure you want to delete "${name}"?`)) {
                fetch('api/address_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete', name })
                }).then(() => loadAddressSettings());
              }
            };
          });
        })
        .catch(() => {
          if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px; color:#ef4444;">Failed to load address settings.</td></tr>';
        });
    };

    const renderDistrictChips = () => {
      const chipsWrap = document.getElementById('zrx-district-chips-container');
      const searchInput = document.getElementById('zrx-district-filter-input');
      const filterText = (searchInput?.value || '').toLowerCase().trim();
      if (!chipsWrap) return;

      const filtered = allDistricts.filter(d => {
        if (!filterText) return true;
        return d.name.toLowerCase().includes(filterText) || (d.bn_name && d.bn_name.toLowerCase().includes(filterText));
      });

      if (!filtered.length) {
        chipsWrap.innerHTML = '<span style="color:#64748b;font-size:0.8rem;padding:4px;">No matching districts.</span>';
        return;
      }

      chipsWrap.innerHTML = filtered.map(d => {
        const isSel = selectedDistrictNames.length === 0 || selectedDistrictNames.includes(d.name);
        return `
          <span class="zrx-district-chip ${isSel ? 'selected' : ''}" data-dist-name="${escapeHtml(d.name)}">
            ${escapeHtml(d.name)}${d.bn_name ? ` (${escapeHtml(d.bn_name)})` : ''}
            ${isSel ? '✓' : '+'}
          </span>
        `;
      }).join('');

      chipsWrap.querySelectorAll('.zrx-district-chip').forEach(chip => {
        chip.onclick = () => {
          const dName = chip.getAttribute('data-dist-name');
          if (selectedDistrictNames.length === 0) {
            selectedDistrictNames = [dName];
          } else if (selectedDistrictNames.includes(dName)) {
            selectedDistrictNames = selectedDistrictNames.filter(n => n !== dName);
          } else {
            selectedDistrictNames.push(dName);
          }
          renderDistrictChips();
        };
      });
    };

    const distSearchInput = overlay.querySelector('#zrx-district-filter-input');
    if (distSearchInput) {
      distSearchInput.oninput = () => renderDistrictChips();
    }

    const distAllBtn = overlay.querySelector('#zrx-btn-dist-all');
    if (distAllBtn) {
      distAllBtn.onclick = () => {
        selectedDistrictNames = [];
        renderDistrictChips();
      };
    }

    const distSaveBtn = overlay.querySelector('#zrx-btn-dist-save');
    if (distSaveBtn) {
      distSaveBtn.onclick = () => {
        distSaveBtn.disabled = true;
        distSaveBtn.textContent = 'Saving...';
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'save_districts', districts: selectedDistrictNames })
        })
        .then(res => res.json())
        .then(data => {
          distSaveBtn.disabled = false;
          if (data.ok) {
            distSaveBtn.textContent = 'Saved ✓';
            setTimeout(() => { distSaveBtn.textContent = 'Save Districts'; }, 2000);
          }
        })
        .catch(() => {
          distSaveBtn.disabled = false;
          distSaveBtn.textContent = 'Save Districts';
        });
      };
    }

    // Address Search with Debounce
    const addrSearchInput = overlay.querySelector('#zrx-addr-search-input');
    if (addrSearchInput) {
      addrSearchInput.oninput = () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
          currentSearchQ = addrSearchInput.value.trim();
          loadAddressSettings();
        }, 250);
      };
    }

    // Address Filter Tabs
    const filterTabsContainer = overlay.querySelector('#zrx-addr-filter-tabs-container');
    if (filterTabsContainer) {
      filterTabsContainer.querySelectorAll('.zrx-addr-tab-btn').forEach(tabBtn => {
        tabBtn.onclick = () => {
          filterTabsContainer.querySelectorAll('.zrx-addr-tab-btn').forEach(b => b.classList.remove('active'));
          tabBtn.classList.add('active');
          currentFilter = tabBtn.getAttribute('data-addr-filter') || 'all';
          loadAddressSettings();
        };
      });
    }

    const addAddrBtn = overlay.querySelector('#zrx-btn-add-addr');
    const addAddrInput = overlay.querySelector('#zrx-new-addr-input');
    if (addAddrBtn && addAddrInput) {
      const doAdd = () => {
        const name = addAddrInput.value.trim();
        if (!name) return;
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', name })
        }).then(() => {
          addAddrInput.value = '';
          loadAddressSettings();
        });
      };
      addAddrBtn.onclick = doAdd;
      addAddrInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          doAdd();
        }
      };
    }

    const resetAddrBtn = overlay.querySelector('#zrx-btn-reset-addr');
    if (resetAddrBtn) {
      resetAddrBtn.onclick = () => {
        if (confirm('Reset all address customizations and district preferences to default?')) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
          }).then(() => {
            allDistricts = [];
            selectedDistrictNames = [];
            loadAddressSettings();
          });
        }
      };
    }

    loadAddressSettings();
  }
}

function initializeHelpGuidelineModals() {
  const handleHelpTrigger = (e) => {
    const btn = e.target?.closest?.('.zrx-help-icon-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    const type = btn.getAttribute('data-help-type');
    if (type) {
      showHelpGuidelineModal(type);
    }
  };

  document.addEventListener('click', handleHelpTrigger, true);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      const btn = e.target?.closest?.('.zrx-help-icon-btn');
      if (btn) {
        handleHelpTrigger(e);
      }
    }
  }, true);
}

function showZrxAlert(message, options = {}) {
  const type = options.type || 'warning';
  const title = options.title || (type === 'warning' ? 'Attention Required' : (type === 'error' ? 'Error' : 'Notification'));
  const btnText = options.btnText || 'Okay';
  const onOk = typeof options.onOk === 'function' ? options.onOk : null;

  let overlay = document.getElementById('zrx-alert-modal');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'zrx-alert-modal';
    overlay.className = 'zrx-alert-modal-overlay';
    overlay.innerHTML = `
      <div class="zrx-alert-card" role="dialog" aria-modal="true">
        <div class="zrx-alert-icon-wrap" id="zrx-alert-icon-wrap"></div>
        <div class="zrx-alert-content">
          <strong class="zrx-alert-title" id="zrx-alert-title"></strong>
          <p class="zrx-alert-message" id="zrx-alert-message"></p>
        </div>
        <div class="zrx-alert-footer">
          <button type="button" class="zrx-alert-ok-btn" id="zrx-alert-ok-btn">Okay</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  const card = overlay.querySelector('.zrx-alert-card');
  const iconWrap = overlay.querySelector('#zrx-alert-icon-wrap');
  const titleEl = overlay.querySelector('#zrx-alert-title');
  const msgEl = overlay.querySelector('#zrx-alert-message');
  const okBtn = overlay.querySelector('#zrx-alert-ok-btn');

  card.setAttribute('data-type', type);
  titleEl.textContent = title;
  msgEl.textContent = message;
  okBtn.textContent = btnText;

  if (type === 'warning') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
  } else if (type === 'error') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  } else if (type === 'success') {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  } else {
    iconWrap.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }

  overlay.classList.add('active');
  okBtn.focus();

  const close = () => {
    overlay.classList.remove('active');
    if (onOk) onOk();
  };

  okBtn.onclick = close;
  overlay.onclick = (e) => {
    if (e.target === overlay) close();
  };
  const onKey = (e) => {
    if (e.key === 'Escape' || e.key === 'Enter') {
      if (overlay.classList.contains('active')) {
        e.preventDefault();
        close();
        document.removeEventListener('keydown', onKey);
      }
    }
  };
  document.addEventListener('keydown', onKey);
}

window.showZrxAlert = showZrxAlert;

function zrxShowFieldValidation(input, message = 'Please fill out this field.') {
  if (!input) return;

  // Clean up any existing validation tooltips
  const existing = document.querySelectorAll('.zrx-field-tooltip');
  existing.forEach((el) => el.remove());
  document.querySelectorAll('.zrx-input-invalid').forEach((el) => el.classList.remove('zrx-input-invalid'));

  input.focus();
  input.classList.add('zrx-input-invalid');

  const tooltip = document.createElement('div');
  tooltip.className = 'zrx-field-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.innerHTML = `
    <div class="zrx-field-tooltip-arrow"></div>
    <div class="zrx-field-tooltip-icon">!</div>
    <span class="zrx-field-tooltip-text">${String(message)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')}</span>
  `;

  document.body.appendChild(tooltip);

  function position() {
    const rect = input.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    let top = rect.bottom + window.scrollY + 6;
    let left = rect.left + window.scrollX;

    if (left + tooltipRect.width > window.innerWidth - 12) {
      left = Math.max(12, window.innerWidth - tooltipRect.width - 12);
    }

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
  }

  position();

  let isDismissed = false;
  let autoTimer = null;

  function dismiss() {
    if (isDismissed) return;
    isDismissed = true;
    clearTimeout(autoTimer);
    input.classList.remove('zrx-input-invalid');
    tooltip.classList.add('hiding');
    setTimeout(() => {
      tooltip.remove();
    }, 180);
    input.removeEventListener('input', dismiss);
    input.removeEventListener('focus', dismiss);
    document.removeEventListener('click', onDocClick);
    window.removeEventListener('resize', position);
    window.removeEventListener('scroll', position, true);
  }

  function onDocClick(e) {
    if (e.target !== input) {
      dismiss();
    }
  }

  input.addEventListener('input', dismiss, { once: true });
  input.addEventListener('focus', dismiss, { once: true });
  setTimeout(() => {
    document.addEventListener('click', onDocClick, { once: true });
  }, 50);

  window.addEventListener('resize', position, { passive: true });
  window.addEventListener('scroll', position, { passive: true, capture: true });

  autoTimer = setTimeout(dismiss, 4000);
}

window.zrxShowFieldValidation = zrxShowFieldValidation;

function autoResizeTableTextareas(table) {
  if (!table) return;
  const isRxTable = table.classList.contains('rx-table');
  if (isRxTable && !document.body.classList.contains('rx-auto-row-size-enabled')) {
    return;
  }

  const textareas = table.querySelectorAll('tbody textarea');
  textareas.forEach((ta) => {
    if (!ta.value && !ta.placeholder) {
      ta.style.height = '36px';
      return;
    }
    ta.style.transition = 'none';
    ta.style.height = '0px';
    const minH = ta.classList.contains('pe-input') || ta.classList.contains('plan-input') || ta.classList.contains('note-input') ? 30 : 36;
    const natural = Math.max(minH, ta.scrollHeight);
    ta.style.height = natural + 'px';
    requestAnimationFrame(() => {
      ta.style.removeProperty('transition');
    });
  });
}

function ensureTableLayoutActions(table, tableKey) {
  if (!table || !tableKey) return;
  const wrapper = table.closest('.module-card, .pc-wrapper, .rx-wrapper, .oh-wrapper, .history-treatment-wrapper, .history-dh-wrapper, .ot-wrapper, .ph-history-wrapper, .reports-wrapper') || table.parentElement;
  if (!wrapper) return;

  const footer = wrapper.querySelector('.pc-footer, .rx-add-row, .oh-footer, .ot-footer, .reports-footer, .ph-add-row, .reports-add-row, [class*="-footer"], [class*="-add-row"]');
  if (!footer) return;

  let actions = footer.querySelector('.zrx-tbl-layout-actions');
  if (!actions) {
    actions = document.createElement('div');
    actions.className = 'zrx-tbl-layout-actions';
    actions.innerHTML = `
      <button type="button" class="zrx-btn-layout-save" title="Save this customized column layout">Save layout</button>
      <button type="button" class="zrx-btn-layout-reset" title="Reset column widths to default">Reset</button>
      <span role="button" tabindex="0" class="zrx-help-icon-btn zrx-tbl-layout-help" data-help-type="col-resize" title="টেবিল লেআউট নির্দেশিকা" aria-label="Layout Help">
        ${typeof ZimRxIcon !== 'undefined' ? ZimRxIcon.render('help-circle', 14) : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'}
      </span>
    `;

    const saveBtn = actions.querySelector('.zrx-btn-layout-save');
    const resetBtn = actions.querySelector('.zrx-btn-layout-reset');

    saveBtn.addEventListener('click', () => {
      const allThs = Array.from(table.querySelectorAll('thead th'));
      const widths = allThs.map(th => Math.round(th.getBoundingClientRect().width));
      
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      fetch('api/save_interface_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          table_columns: {
            [tableKey]: widths
          }
        })
      })
      .then(res => res.json())
      .then(data => {
        saveBtn.disabled = false;
        if (data.ok) {
          saveBtn.classList.add('saved');
          saveBtn.textContent = 'Saved ✓';
          if (!window.ZimRxSavedTableColumns) window.ZimRxSavedTableColumns = {};
          window.ZimRxSavedTableColumns[tableKey] = widths;
          try {
            localStorage.setItem('zimrx_tbl_cols_' + tableKey, JSON.stringify(widths));
          } catch(e) {}

          // Disappear smoothly 5 seconds after saving
          setTimeout(() => {
            if (actions.parentElement) {
              actions.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              actions.style.opacity = '0';
              actions.style.transform = 'scale(0.95)';
              setTimeout(() => {
                actions.remove();
              }, 350);
            }
          }, 5000);
        } else {
          saveBtn.textContent = 'Save layout';
          alert('Failed to save layout: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(err => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save layout';
        console.error('Error saving table layout:', err);
      });
    });

    resetBtn.addEventListener('click', () => {
      resetBtn.disabled = true;
      resetBtn.textContent = 'Resetting...';

      fetch('api/save_interface_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          reset_table_column: tableKey
        })
      })
      .then(res => res.json())
      .then(data => {
        resetBtn.disabled = false;
        if (data.ok) {
          if (window.ZimRxSavedTableColumns) {
            delete window.ZimRxSavedTableColumns[tableKey];
          }
          try {
            localStorage.removeItem('zimrx_tbl_cols_' + tableKey);
          } catch(e) {}

          // Restore pristine default colgroup if saved
          if (table.dataset.zrxDefaultColgroup) {
            const currentCg = table.querySelector('colgroup');
            if (currentCg) {
              currentCg.outerHTML = table.dataset.zrxDefaultColgroup;
            }
          } else {
            const allCurrentCols = table.querySelectorAll('colgroup col');
            allCurrentCols.forEach(c => c.style.removeProperty('width'));
          }

          // Restore default th widths from dataset or standard defaults
          if (table.dataset.zrxDefaultThWidths) {
            try {
              const defaultWidths = JSON.parse(table.dataset.zrxDefaultThWidths);
              const allCurrentThs = table.querySelectorAll('thead th');
              allCurrentThs.forEach((t, idx) => {
                if (defaultWidths[idx]) {
                  t.style.width = defaultWidths[idx];
                } else {
                  t.style.removeProperty('width');
                }
              });
            } catch(e) {
              const allCurrentThs = table.querySelectorAll('thead th');
              allCurrentThs.forEach(t => t.style.removeProperty('width'));
            }
          } else if (table.classList.contains('rx-table')) {
            const rxDefaults = ['32px', '36px', '38px', '', '18%', '16%', '20%', '12%'];
            const allCurrentThs = table.querySelectorAll('thead th');
            allCurrentThs.forEach((t, idx) => {
              if (rxDefaults[idx]) t.style.width = rxDefaults[idx];
              else t.style.removeProperty('width');
            });
          } else {
            const allCurrentThs = table.querySelectorAll('thead th');
            allCurrentThs.forEach(t => t.style.removeProperty('width'));
          }

          autoResizeTableTextareas(table);

          resetBtn.classList.add('saved');
          resetBtn.textContent = 'Reset ✓';

          // Disappear smoothly 5 seconds after resetting
          setTimeout(() => {
            if (actions.parentElement) {
              actions.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
              actions.style.opacity = '0';
              actions.style.transform = 'scale(0.95)';
              setTimeout(() => {
                actions.remove();
              }, 350);
            }
          }, 5000);
        } else {
          resetBtn.textContent = 'Reset';
          alert('Failed to reset layout: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(err => {
        resetBtn.disabled = false;
        resetBtn.textContent = 'Reset';
        console.error('Error resetting table layout:', err);
      });
    });

    footer.appendChild(actions);
  }
}

function initializeUniversalColumnResize(root = document) {
  const tableSelector = '.pc-table, .rx-table, .oh-table, .ot-table, .zrx-table, #reports-table, .reports-table, .template-table-container table';
  const tables = root.querySelectorAll(tableSelector);

  tables.forEach((table) => {
    if (table.dataset.zrxResizableInit === 'true') {
      return;
    }
    table.dataset.zrxResizableInit = 'true';

    // Store pristine default colgroup & th structure for accurate reset
    const defaultCg = table.querySelector('colgroup');
    if (defaultCg && !table.dataset.zrxDefaultColgroup) {
      table.dataset.zrxDefaultColgroup = defaultCg.outerHTML;
    }
    if (!table.dataset.zrxDefaultThWidths) {
      const initialThs = table.querySelectorAll('thead th');
      const defaultWidths = Array.from(initialThs).map(th => th.style.width || '');
      table.dataset.zrxDefaultThWidths = JSON.stringify(defaultWidths);
    }

    const getTableKey = () => {
      if (table.id) return table.id;
      const parentWithId = table.closest('[id]');
      if (parentWithId) return parentWithId.id + '_' + (table.className || 'tbl').split(' ')[0];
      return (table.className || 'tbl').split(' ')[0];
    };

    const tableKey = getTableKey();

    // Restore saved custom column widths from database or localStorage
    try {
      const savedWidths = (window.ZimRxSavedTableColumns && window.ZimRxSavedTableColumns[tableKey])
        || JSON.parse(localStorage.getItem('zimrx_tbl_cols_' + tableKey) || 'null');

      if (savedWidths && Array.isArray(savedWidths) && savedWidths.length > 0) {
        const initialThs = table.querySelectorAll('thead th');
        const initialCols = table.querySelectorAll('colgroup col');
        savedWidths.forEach((w, idx) => {
          if (w && initialThs[idx]) {
            initialThs[idx].style.width = w + 'px';
          }
          if (w && initialCols[idx]) {
            initialCols[idx].style.width = w + 'px';
          }
        });
      }
    } catch (e) {}

    const ths = table.querySelectorAll('thead th');
    const cols = table.querySelectorAll('colgroup col');

    ths.forEach((th, idx) => {
      // Auto-wrap raw text inside th in a clean truncating span with tooltip
      const rawText = th.textContent.trim();
      if (!th.querySelector('.zrx-th-text') && !th.querySelector('.pc-header-flex') && !th.querySelector('.pc-settings-btn') && !th.querySelector('button') && rawText) {
        th.title = rawText;
        const span = document.createElement('span');
        span.className = 'zrx-th-text';
        span.textContent = rawText;
        th.innerHTML = '';
        th.appendChild(span);
      } else if (rawText && !th.title) {
        th.title = rawText;
      }

      // Don't add resizer on the very last column
      if (idx === ths.length - 1) return;

      let resizer = th.querySelector('.zrx-col-resizer');
      if (!resizer) {
        resizer = document.createElement('div');
        resizer.className = 'zrx-col-resizer';
        resizer.title = 'Drag to resize adjacent columns';
        th.appendChild(resizer);
      }

      let startX = 0;
      let isDragging = false;

      const onMouseDown = (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        isDragging = true;
        startX = e.pageX;

        // Lock current rendered pixel widths on all columns before dragging
        const allThs = Array.from(table.querySelectorAll('thead th'));
        const allCols = Array.from(table.querySelectorAll('colgroup col'));
        const currentRects = allThs.map(t => t.getBoundingClientRect().width);

        allThs.forEach((t, i) => {
          const w = Math.round(currentRects[i]);
          t.style.width = w + 'px';
          if (allCols[i]) {
            allCols[i].style.width = w + 'px';
          }
        });

        const nextTh = ths[idx + 1];
        if (!nextTh) return;

        const startWidthA = currentRects[idx];
        const startWidthB = currentRects[idx + 1];
        const combinedWidth = startWidthA + startWidthB;
        const minWidth = 28;

        resizer.classList.add('is-resizing');
        th.classList.add('zrx-th-resizing');
        document.body.classList.add('zrx-column-resizing');

        const onMouseMove = (ev) => {
          if (!isDragging) return;
          const deltaX = ev.pageX - startX;

          const minDelta = -(startWidthA - minWidth);
          const maxDelta = (startWidthB - minWidth);
          const clampedDelta = Math.max(minDelta, Math.min(maxDelta, deltaX));

          const newWidthA = Math.round(startWidthA + clampedDelta);
          const newWidthB = combinedWidth - newWidthA;

          th.style.width = newWidthA + 'px';
          nextTh.style.width = newWidthB + 'px';

          if (cols[idx]) {
            cols[idx].style.width = newWidthA + 'px';
          }
          if (cols[idx + 1]) {
            cols[idx + 1].style.width = newWidthB + 'px';
          }

          autoResizeTableTextareas(table);
        };

        const onMouseUp = () => {
          if (!isDragging) return;
          isDragging = false;
          resizer.classList.remove('is-resizing');
          th.classList.remove('zrx-th-resizing');
          document.body.classList.remove('zrx-column-resizing');

          window.removeEventListener('mousemove', onMouseMove);
          window.removeEventListener('mouseup', onMouseUp);

          autoResizeTableTextareas(table);
          ensureTableLayoutActions(table, tableKey);
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
      };

      resizer.addEventListener('mouseenter', () => th.classList.add('zrx-th-resizing'));
      resizer.addEventListener('mouseleave', () => {
        if (!isDragging) th.classList.remove('zrx-th-resizing');
      });
      resizer.addEventListener('mousedown', onMouseDown);

      // Double click to reset column widths to default immediately
      resizer.addEventListener('dblclick', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const allCurrentThs = table.querySelectorAll('thead th');
        const allCurrentCols = table.querySelectorAll('colgroup col');
        allCurrentThs.forEach(t => t.style.removeProperty('width'));
        allCurrentCols.forEach(c => c.style.removeProperty('width'));
        autoResizeTableTextareas(table);
      });
    });
  });
}

window.initializeUniversalColumnResize = initializeUniversalColumnResize;

// =======================================================
// Universal Prescription Grid Keyboard Navigation Engine
// Excel/Vim-grade navigation unified across all ZimRx tables
// =======================================================
function initializeUniversalGridNavigation(root = document) {
  if (document.documentElement.dataset.universalGridNavReady === '1') {
    return;
  }
  document.documentElement.dataset.universalGridNavReady = '1';

  let isModifierDown = false;
  let navSuppressTimeout = null;

  const setDropdownSuppression = (active) => {
    if (active) {
      window.ZimRxNavSuppressDropdown = true;
      document.body.classList.add('zrx-suppress-dropdowns');
    } else {
      if (navSuppressTimeout) clearTimeout(navSuppressTimeout);
      navSuppressTimeout = setTimeout(() => {
        if (!isModifierDown) {
          window.ZimRxNavSuppressDropdown = false;
          document.body.classList.remove('zrx-suppress-dropdowns');
        }
      }, 150);
    }
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Alt' || e.key === 'Shift') {
      isModifierDown = true;
      setDropdownSuppression(true);
    }
  }, true);

  document.addEventListener('keyup', (e) => {
    if (e.key === 'Alt' || e.key === 'Shift') {
      if (!e.altKey && !e.shiftKey) {
        isModifierDown = false;
        setDropdownSuppression(false);
      }
    }
  }, true);

  const isGridEditableCell = (el) => {
    if (!el || !el.matches('input, textarea, select')) return false;
    if (el.type === 'hidden' || el.disabled || el.readOnly) return false;
    const tr = el.closest('tr');
    if (!tr) return false;
    const table = tr.closest('table');
    if (!table) return false;
    // Exclude settings modals or dialog popups
    if (el.closest('.pc-settings-modal, .rx-settings-modal, .modal, [role="dialog"]')) return false;
    // Exclude table action buttons, delete triggers, row move handles
    if (el.closest('.pc-del, .rx-del, .oh-del, .pc-drag, .rx-drag, .oh-drag, .pc-action, .rx-action, .pc-row-no, .rx-no, .oh-row-no')) return false;
    return true;
  };

  const getRowCells = (tr) => {
    if (!tr) return [];
    return Array.from(tr.querySelectorAll(
      'input:not([type="hidden"]):not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly]), select:not([disabled]):not([readonly])'
    )).filter((el) => {
      if (el.closest('.pc-del, .rx-del, .oh-del, .pc-drag, .rx-drag, .oh-drag, .pc-action, .rx-action, .pc-row-no, .rx-no, .oh-row-no')) return false;
      return el.offsetParent !== null; // visible
    });
  };

  const getTableRows = (table) => {
    if (!table) return [];
    const tbody = table.querySelector('tbody') || table;
    return Array.from(tbody.querySelectorAll('tr')).filter((tr) => {
      if (tr.closest('template') || tr.classList.contains('zrx-drag-ghost') || tr.classList.contains('zrx-drag-ghost-floating')) return false;
      return tr.offsetParent !== null;
    });
  };

  const updateGridRowNumbers = (tbody) => {
    if (!tbody) return;
    tbody.querySelectorAll('.pc-row-no, .rx-no, .oh-row-no, .phrase-sl, .row-no, .sl-no').forEach((cell, index) => {
      cell.textContent = index + 1;
    });
  };

  const findAddRowButton = (table) => {
    const container = table.closest(
      '#dx-wrapper, #ix-wrapper, #plan-wrapper, #note-wrapper, #pe-wrapper, #pc-wrapper, #oh-wrapper, #history-wrapper, .advice-wrapper, .reports-wrapper, .rx-wrapper, .pc-wrapper, .oh-wrapper, .module-card, section'
    ) || table.parentElement;
    if (!container) return null;
    return container.querySelector(
      '.pc-add-row-btn, .rx-add-row-btn, #rx-add-more-btn, .oh-add-row-btn, .ot-add-row-btn, .reports-add-row-btn, .zrx-add-row-btn, [data-add-row]'
    );
  };

  const focusCell = (target) => {
    if (!target) return false;
    try {
      target.focus({ preventScroll: true });
    } catch (_) {
      target.focus();
    }
    if (target.tagName === 'INPUT' && typeof target.select === 'function' && target.value) {
      try { target.select(); } catch (_) {}
    }
    return true;
  };

  const triggerAddRowAndFocus = (table) => {
    const addBtn = findAddRowButton(table);
    if (!addBtn) return false;
    const rowsBefore = getTableRows(table).length;
    addBtn.click();
    let attempts = 0;
    const poll = () => {
      const rowsAfter = getTableRows(table);
      if (rowsAfter.length > rowsBefore) {
        const newRow = rowsAfter[rowsAfter.length - 1];
        const cells = getRowCells(newRow);
        if (cells.length) {
          focusCell(cells[0]);
          return;
        }
      }
      if (++attempts < 15) {
        requestAnimationFrame(poll);
      }
    };
    requestAnimationFrame(poll);
    return true;
  };

  const getOpenDropdown = () => {
    const openLists = document.querySelectorAll(
      '.zrx-dropdown.show, .rx-dropdown.show, .autocomplete-list.show, .appointment-lookup-list.show, ul.show, .rx-dropdown:not([hidden]):not([style*="display: none"])'
    );
    for (const list of openLists) {
      if (list.offsetParent !== null) {
        const activeItem = list.querySelector('.zrx-dropdown-item.active, .rx-dropdown-item.active, li.active');
        return { list, activeItem };
      }
    }
    return null;
  };

  document.addEventListener('keydown', (e) => {
    const input = e.target;
    if (!isGridEditableCell(input)) return;

    const row = input.closest('tr');
    const table = row ? row.closest('table') : null;
    if (!row || !table) return;

    const rowCells = getRowCells(row);
    const cellIdx = rowCells.indexOf(input);
    if (cellIdx === -1) return;

    const allRows = getTableRows(table);
    const rowIdx = allRows.indexOf(row);
    if (rowIdx === -1) return;

    const isFirstCol = (cellIdx === 0);
    const isLastCol = (cellIdx === rowCells.length - 1);
    const isFirstRow = (rowIdx === 0);
    const isLastRow = (rowIdx === allRows.length - 1);

    const openDd = getOpenDropdown();

    // 1. ESCAPE KEY: Blur input if dropdown is closed
    if (e.key === 'Escape') {
      if (openDd) {
        return; // Let dropdown handler close it
      }
      e.preventDefault();
      input.blur();
      return;
    }

    // 2. MULTI-LINE TEXTAREA NEWLINE: Alt + Enter or Ctrl + Shift + Enter
    if (input.tagName === 'TEXTAREA' && ((e.altKey && e.key === 'Enter') || (e.ctrlKey && e.shiftKey && e.key === 'Enter'))) {
      e.preventDefault();
      const start = input.selectionStart || 0;
      const end = input.selectionEnd || 0;
      const val = input.value;
      input.value = val.substring(0, start) + "\n" + val.substring(end);
      input.selectionStart = input.selectionEnd = start + 1;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }

    // 3. CTRL + ENTER: Typewriter Carriage Return -> Column 1 of NEXT row
    if (e.ctrlKey && !e.shiftKey && !e.altKey && e.key === 'Enter') {
      e.preventDefault();
      if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // 4. SHIFT + ENTER: Reverse Return -> Column 1 of PREVIOUS row
    if (e.shiftKey && !e.ctrlKey && !e.altKey && e.key === 'Enter') {
      e.preventDefault();
      if (!isFirstRow) {
        const prevRow = allRows[rowIdx - 1];
        const prevCells = getRowCells(prevRow);
        if (prevCells.length) focusCell(prevCells[0]);
      }
      return;
    }

    // 5. ALT + DELETE: Instantly delete current row with re-indexing & refocusing
    if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'Delete' || e.key === 'Del')) {
      e.preventDefault();
      const tbody = table.querySelector('tbody') || table;
      const targetRow = (!isLastRow) ? allRows[rowIdx + 1] : (allRows.length > 1 ? allRows[rowIdx - 1] : null);
      const targetCells = targetRow ? getRowCells(targetRow) : null;
      const targetInput = targetCells ? targetCells[Math.min(cellIdx, targetCells.length - 1)] : null;

      const delBtn = row.querySelector(
        '.pc-del button, .rx-del button, .oh-del button, .del-row-btn, button[title*="Remove Row" i], button[title*="Delete" i]'
      );
      if (delBtn) {
        delBtn.click();
      } else {
        row.remove();
      }

      updateGridRowNumbers(tbody);
      tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));

      if (targetInput) {
        setTimeout(() => focusCell(targetInput), 20);
      }
      return;
    }

    // 6. ALT + SHIFT + UP / DOWN: Slide entire row up or down without leaving input!
    if (e.altKey && e.shiftKey && !e.ctrlKey) {
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isFirstRow) {
          const tbody = table.querySelector('tbody') || table;
          const prevRow = allRows[rowIdx - 1];
          const caret = (typeof input.selectionStart === 'number') ? input.selectionStart : null;
          tbody.insertBefore(row, prevRow);
          updateGridRowNumbers(tbody);
          tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
          input.focus({ preventScroll: true });
          if (caret !== null && typeof input.setSelectionRange === 'function') {
            try { input.setSelectionRange(caret, caret); } catch (_) {}
          }
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!isLastRow) {
          const tbody = table.querySelector('tbody') || table;
          const nextRow = allRows[rowIdx + 1];
          const caret = (typeof input.selectionStart === 'number') ? input.selectionStart : null;
          tbody.insertBefore(nextRow, row);
          updateGridRowNumbers(tbody);
          tbody.dispatchEvent(new CustomEvent('zrx:reordered', { bubbles: true }));
          input.focus({ preventScroll: true });
          if (caret !== null && typeof input.setSelectionRange === 'function') {
            try { input.setSelectionRange(caret, caret); } catch (_) {}
          }
        }
        return;
      }
    }

    // 7. ALT + ARROW KEYS: Free Spatial Movement across grid (traps browser history)
    if (e.altKey && !e.ctrlKey && !e.shiftKey) {
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const target = prevCells[Math.min(cellIdx, prevCells.length - 1)];
          focusCell(target);
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          const target = nextCells[Math.min(cellIdx, nextCells.length - 1)];
          focusCell(target);
        }
        return;
      }
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        if (!isFirstCol) {
          focusCell(rowCells[cellIdx - 1]);
        } else if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
        }
        return;
      }
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        if (!isLastCol) {
          focusCell(rowCells[cellIdx + 1]);
        } else if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          if (nextCells.length) focusCell(nextCells[0]);
        }
        return;
      }
    }

    // 6. TAB: Move ONLY (never select dropdown items, simply advance)
    if (e.key === 'Tab' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
      e.preventDefault();
      // If a dropdown is currently open, close it without selecting anything
      if (openDd?.list) {
        openDd.list.classList.remove('show');
        openDd.list.style.display = 'none';
      }

      if (!isLastCol) {
        focusCell(rowCells[cellIdx + 1]);
      } else if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // 7. ENTER: Select active dropdown item (if open), otherwise move to next cell
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
      if (openDd?.activeItem) {
        // Let the autocomplete module's own keydown handler select the item and advance
        return;
      }

      e.preventDefault();
      if (!isLastCol) {
        focusCell(rowCells[cellIdx + 1]);
      } else if (!isLastRow) {
        const nextRow = allRows[rowIdx + 1];
        const nextCells = getRowCells(nextRow);
        if (nextCells.length) focusCell(nextCells[0]);
      } else {
        triggerAddRowAndFocus(table);
      }
      return;
    }

    // 8. SHIFT + TAB: Backward Step (move only, never select)
    if (e.shiftKey && e.key === 'Tab' && !e.ctrlKey && !e.altKey) {
      e.preventDefault();
      if (openDd?.list) {
        openDd.list.classList.remove('show');
        openDd.list.style.display = 'none';
      }

      if (!isFirstCol) {
        focusCell(rowCells[cellIdx - 1]);
      } else if (!isFirstRow) {
        const prevRow = allRows[rowIdx - 1];
        const prevCells = getRowCells(prevRow);
        if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
      }
      return;
    }

    // 8. ARROW UP / DOWN (When dropdown is NOT open)
    if (!openDd && !e.altKey && !e.ctrlKey && !e.shiftKey) {
      if (e.key === 'ArrowUp') {
        if (!isFirstRow) {
          e.preventDefault();
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const target = prevCells[Math.min(cellIdx, prevCells.length - 1)];
          focusCell(target);
          return;
        }
      }
      if (e.key === 'ArrowDown') {
        if (!isLastRow) {
          e.preventDefault();
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          const target = nextCells[Math.min(cellIdx, nextCells.length - 1)];
          focusCell(target);
          return;
        }
      }
    }

    // 9. ARROW LEFT / RIGHT (Boundary Escape)
    if (!e.altKey && !e.shiftKey && !e.ctrlKey) {
      const len = input.value.length;
      const start = input.selectionStart;
      const end = input.selectionEnd;

      if (e.key === 'ArrowLeft' && start === 0 && end === 0) {
        e.preventDefault();
        if (!isFirstCol) {
          focusCell(rowCells[cellIdx - 1]);
        } else if (!isFirstRow) {
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          if (prevCells.length) focusCell(prevCells[prevCells.length - 1]);
        }
        return;
      }

      if (e.key === 'ArrowRight' && start === len && end === len) {
        e.preventDefault();
        if (!isLastCol) {
          focusCell(rowCells[cellIdx + 1]);
        } else if (!isLastRow) {
          const nextRow = allRows[rowIdx + 1];
          const nextCells = getRowCells(nextRow);
          if (nextCells.length) focusCell(nextCells[0]);
        }
        return;
      }
    }

    // 10. BACKSPACE: Mouseless Empty Row Deletion
    if (e.key === 'Backspace' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
      if (isFirstCol && input.selectionStart === 0 && input.selectionEnd === 0) {
        const isBlankRow = rowCells.every((c) => !c.value.trim());
        if (isBlankRow && allRows.length > 1 && !isFirstRow) {
          e.preventDefault();
          const prevRow = allRows[rowIdx - 1];
          const prevCells = getRowCells(prevRow);
          const targetCell = prevCells.length ? prevCells[prevCells.length - 1] : null;

          const delBtn = row.querySelector(
            '.pc-del button, .rx-del button, .oh-del button, .del-row-btn, button[title*="Remove Row" i], button[title*="Delete" i]'
          );
          if (delBtn) {
            delBtn.click();
          } else {
            row.remove();
          }

          if (targetCell) {
            setTimeout(() => focusCell(targetCell), 20);
          }
          return;
        }
      }
    }
  });
}

window.initializeUniversalGridNavigation = initializeUniversalGridNavigation;

initializeHelpGuidelineModals();
initializeUniversalColumnResize();
initializeUniversalGridNavigation();

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initializeUniversalColumnResize();
    initializeUniversalGridNavigation();
  });
}

