    function splitClassLabels(cls) {
        if (!cls) return [];

        const seen = new Set();
        return cls
            .split(',')
            .map(part => part.replace(/\s+/g, ' ').trim())
            .filter(part => {
                if (!part) return false;

                const key = part.toLowerCase();
                if (seen.has(key)) return false;

                seen.add(key);
                return true;
            });
    }

    function getPrimaryClassLabel(cls) {
        const labels = splitClassLabels(cls);
        return labels.length ? labels[0] : (cls || '');
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function formatMoaText(text) {
        return escapeHtml(text).replace(/\r\n/g, '<br>').replace(/\n/g, '<br>');
    }

    function renderClassLinks(cls) {
        const labels = splitClassLabels(cls);
        if (!labels.length) return 'N/A';

        return `<div class="class-link-list">${labels.map(label => `
            <span class="class-link" onclick="openMoaTable('${label.replace(/'/g, "\\'")}')">${escapeHtml(label)}</span>
        `).join('')}</div>`;
    }

    function toggleSidebarMoaButton(show) {
        $('#sidebarMoaBtn').css('display', show ? 'inline-flex' : 'none');
    }

    function setCurrentClassId(cls) {
        currentClassId = getPrimaryClassLabel(cls);
        currentClassMoaRows = [];
    }
