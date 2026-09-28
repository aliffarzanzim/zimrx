(function() {
    const wrapper = document.getElementById('note-wrapper');
    const tbody = document.getElementById('note-tbody');
    const template = document.getElementById('note-row-template');
    const addBtn = wrapper.querySelector('.note-add-row-btn');

    // --- Row Management ---
    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.pc-row');
        rows.forEach((row, index) => {
            const noCell = row.querySelector('.pc-row-no');
            if (noCell) noCell.textContent = index + 1;
        });
    }

    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const tr = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(tr);
        updateRowNumbers();

        const textarea = tr.querySelector('.note-input');
        if (textarea) {
            autoResizeTextarea(textarea);
            textarea.focus();
        }
    });

    wrapper.addEventListener('click', (e) => {
        const delBtn = e.target.closest('.pc-del button');
        if (delBtn) {
            e.stopPropagation();
            const row = delBtn.closest('tr');
            row.remove();
            updateRowNumbers();
        }
    });

    // --- Textarea Auto-Resize ---
    function autoResizeTextarea(textarea) {
        if (!textarea || textarea.tagName !== 'TEXTAREA') return;
        textarea.style.transition = 'none';
        textarea.style.height = '0';
        const natural = Math.max(30, textarea.scrollHeight);
        textarea.style.height = natural + 'px';
        requestAnimationFrame(() => { textarea.style.transition = ''; });
    }

    // Initialize textareas on load
    document.querySelectorAll('#note-table textarea').forEach(autoResizeTextarea);

    // Dynamic resize while typing
    wrapper.addEventListener('input', (e) => {
        if (e.target.tagName === 'TEXTAREA') {
            autoResizeTextarea(e.target);
        }
    });
})();
