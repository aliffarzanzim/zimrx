// Contextual help guide controller.

let currentHelpModalType = null;
let currentHelpModalLang = null;
let helpLanguageHandlerBound = false;
window.ZimRxHelpGuidelinesCatalogs = window.ZimRxHelpGuidelinesCatalogs || {};

function getActiveHelpLanguage() {
  if (typeof window.ZimRxHelpInitialLang === 'string' && window.ZimRxHelpInitialLang !== '') {
    return window.ZimRxHelpInitialLang;
  }
  const saved = localStorage.getItem('zimrx_help_lang');
  if (saved === 'bn' || saved === 'en') {
    return saved;
  }
  const isBd = (window.ZimRxPracticeCountry === 'BD') ||
               (window.ZimRxState && window.ZimRxState.practiceCountry === 'BD') ||
               (document.documentElement.lang === 'bn');
  return isBd ? 'bn' : 'en';
}

async function loadHelpGuidelinesCatalog(lang) {
  if (window.ZimRxHelpGuidelinesCatalogs[lang] && Object.keys(window.ZimRxHelpGuidelinesCatalogs[lang]).length > 0) {
    return window.ZimRxHelpGuidelinesCatalogs[lang];
  }
  try {
    const res = await fetch(`api/get_help_guidelines.php?lang=${encodeURIComponent(lang)}`);
    if (res.ok) {
      const data = await res.json();
      if (data && typeof data === 'object') {
        window.ZimRxHelpGuidelinesCatalogs[lang] = data;
        return data;
      }
    }
  } catch (e) {
    console.error('Failed to load help guidelines catalog:', e);
  }
  return window.ZimRxHelpGuidelinesCatalogs['en'] || window.ZimRxHelpGuidelinesCatalogs['bn'] || {};
}

async function showHelpGuidelineModal(type, requestedLang) {
  currentHelpModalType = type;
  const lang = requestedLang || getActiveHelpLanguage();
  currentHelpModalLang = lang;

  const catalog = await loadHelpGuidelinesCatalog(lang);
  const data = catalog[type] || {};
  if (!data || !data.title) return;

  const modalUi = catalog.modal_ui || {};

  const overlay = document.getElementById('zrx-help-modal-overlay');
  if (!overlay) return;
  const wasHidden = overlay.hidden;
  const dialog = overlay.querySelector('[role="dialog"]');
  if (dialog) dialog.classList.toggle('zrx-help-modal-wide', Boolean(data.wide));

  document.getElementById('zrx-help-modal-title').textContent = data.title;
  const languageSelect = overlay.querySelector('[data-zrx-help-language]');
  if (languageSelect && languageSelect.value !== lang) {
    languageSelect.value = lang;
    languageSelect.dispatchEvent(new Event('change'));
  }

  const okBtn = overlay.querySelector('#zrx-help-modal-ok-btn');
  if (okBtn) {
    okBtn.textContent = modalUi.btn_ok || ((lang === 'bn') ? 'বুঝেছি' : 'Got it');
  }

  let bodyHtml = (data.steps || []).map(step => `
    <div class="zrx-help-step">
      <div class="zrx-help-step-icon">${step.icon || '•'}</div>
      <div class="zrx-help-step-text">${step.text || ''}</div>
    </div>
  `).join('');

  // SAFETY: bodyHtml is constructed from sanitized localized JSON catalog text
  // and local layout controls.
  document.getElementById('zrx-help-modal-body').innerHTML = bodyHtml;
  if (wasHidden) window.ZimRxModal.open(overlay);

}



function initializeHelpGuidelineModals() {
  if (!helpLanguageHandlerBound) {
    document.addEventListener('change', async (event) => {
      const select = event.target?.closest?.('[data-zrx-help-language]');
      if (!select || !Array.from(select.options).some((option) => option.value === select.value)) return;

      const targetLang = select.value;
      const previousLang = window.ZimRxHelpInitialLang;
      const picker = select.closest('.zrx-help-language-picker');
      picker?.classList.add('is-saving');

      try {
        const response = await fetch('api/help_language.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.ZimRxCsrfToken
          },
          body: JSON.stringify({ language: targetLang })
        });
        const result = await response.json();
        if (!response.ok || result.ok !== true) throw new Error('Help language could not be saved.');

        window.ZimRxHelpInitialLang = targetLang;
        localStorage.setItem('zimrx_help_lang', targetLang);
        if (currentHelpModalType) await showHelpGuidelineModal(currentHelpModalType, targetLang);
      } catch (error) {
        select.value = previousLang;
        select.dispatchEvent(new Event('change'));
        console.error('Failed to save help language:', error);
      } finally {
        picker?.classList.remove('is-saving');
      }
    });
    helpLanguageHandlerBound = true;
  }

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
