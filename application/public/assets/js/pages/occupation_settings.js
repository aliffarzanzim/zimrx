(async function () {
  const root = document.getElementById('zrx-occupation-settings-root');
  if (!root || !window.ZimRxOccupationSettings) return;

  const escapeHtml = value => window.ZimRxUtils.escapeHtml(value);
  const lang = window.ZimRxHelpInitialLang || 'en';
  let catalog;
  try {
    const response = await fetch(`api/get_directory_settings.php?lang=${encodeURIComponent(lang)}`);
    if (!response.ok) throw new Error('Occupation settings catalog could not be loaded.');
    catalog = await response.json();
  } catch (error) {
    root.textContent = 'Occupation settings could not be loaded.';
    console.error(error);
    return;
  }

  const section = catalog.occupation || {};
  const ui = section.ui || {};
  const title = document.querySelector('.zrx-directory-settings-page h1');
  const description = document.querySelector('.zrx-directory-settings-page .zrx-settings-page-hero p');
  if (title) title.textContent = 'Occupation Settings';
  if (description) description.textContent = section.description || '';

  root.innerHTML = `
    <section class="zrx-directory-panel" aria-labelledby="zrx-occupation-directory-title">
      <h2 id="zrx-occupation-directory-title">${escapeHtml(section.title || 'Occupation Directory')}</h2>
      <div class="zrx-occ-toolbar">
        <div class="zrx-occ-add-form">
          <input type="text" id="zrx-new-occ-input" placeholder="${escapeHtml(ui.placeholder_input || 'Type new occupation...')}" autocomplete="off">
          <button type="button" id="zrx-btn-add-occ" class="btn btn-primary btn-sm">${escapeHtml(ui.btn_add || 'Add')}</button>
        </div>
        <button type="button" id="zrx-btn-reset-occ" class="btn btn-outline btn-sm">${escapeHtml(ui.btn_reset || 'Reset to Default')}</button>
      </div>
      <div class="zrx-directory-table-wrap">
        <table class="zrx-occ-table">
          <thead><tr>
            <th class="zrx-help-th-drag">${escapeHtml(ui.th_move || 'Move')}</th>
            <th class="zrx-help-th-actions">${escapeHtml(ui.th_actions || 'Actions')}</th>
            <th class="zrx-help-th-sl">${escapeHtml(ui.th_type || 'Type')}</th>
            <th class="zrx-ta-l">${escapeHtml(ui.th_name || 'Occupation Name')}</th>
            <th class="zrx-help-th-usage">${escapeHtml(ui.th_usage || 'Usage')}</th>
          </tr></thead>
          <tbody id="zrx-occ-table-body"><tr><td colspan="5" class="zrx-empty-cell">${escapeHtml(ui.loading || 'Loading occupations...')}</td></tr></tbody>
        </table>
      </div>
    </section>`;
  window.ZimRxOccupationSettings.initManager(root, ui);
})();
