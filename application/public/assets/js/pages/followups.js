function getCurrentFollowupFilter() {
    const activeChip = document.querySelector('.followup-chip-btn.active');
    return activeChip ? (activeChip.getAttribute('data-filter') || 'all') : 'all';
}

function filterFollowupStatus(status, btn) {
    if (btn) {
        document.querySelectorAll('.followup-chip-btn').forEach((chip) => chip.classList.remove('active'));
        btn.classList.add('active');
    }

    const targetFilter = status || getCurrentFollowupFilter();
    const search = (document.getElementById('followup-search-input')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#followup-table-body tr.followup-row');
    let visibleCount = 0;

    rows.forEach((row) => {
        const statusMatches = targetFilter === 'all' || row.getAttribute('data-status') === targetFilter;
        const searchMatches = !search || row.textContent.toLowerCase().includes(search);
        const visible = statusMatches && searchMatches;
        row.style.display = visible ? '' : 'none';
        if (visible) visibleCount += 1;
    });

    const countLabel = document.getElementById('followup-count-label');
    if (countLabel) {
        countLabel.textContent = `${visibleCount} scheduled follow-up${visibleCount === 1 ? '' : 's'}`;
    }
}

function quickQueueFollowup(patient) {
    const visitId = Number.parseInt(patient?.visit_id, 10);
    if (!Number.isSafeInteger(visitId) || visitId <= 0) return;
    window.location.assign(`appointments.php?queue_followup_id=${encodeURIComponent(visitId)}`);
}
