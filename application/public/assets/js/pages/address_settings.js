(async function () {
  const root = document.getElementById('zrx-address-settings-root');
  if (!root || !window.ZimRxAddressSettings) return;

  const escapeHtml = value => window.ZimRxUtils.escapeHtml(value);
  const lang = window.ZimRxHelpInitialLang || 'en';
  let catalog;
  try {
    const response = await fetch(`api/get_directory_settings.php?lang=${encodeURIComponent(lang)}`);
    if (!response.ok) throw new Error('Address settings catalog could not be loaded.');
    catalog = await response.json();
  } catch (error) {
    root.textContent = 'Address settings could not be loaded.';
    console.error(error);
    return;
  }

  const section = catalog.address || {};
  const ui = section.ui || {};
  const title = document.querySelector('.zrx-directory-settings-page h1');
  const description = document.querySelector('.zrx-directory-settings-page .zrx-settings-page-hero p');
  if (title) title.textContent = 'Address Settings';
  if (description) description.textContent = section.description || 'Configure address search and manage saved suggestions.';

  root.innerHTML = `
    <section class="zrx-directory-panel" aria-labelledby="zrx-address-directory-title">
      <section class="zrx-addr-section">
        <header class="zrx-addr-section-header">
          <h2 id="zrx-address-directory-title" class="zrx-addr-section-title">${ZimRxIcon.render('map-pin', 18)} ${escapeHtml(ui.section_addr_title || section.title || 'Address Directory')}</h2>
          <button type="button" id="zrx-btn-reset-addr" class="btn btn-outline btn-sm">${escapeHtml(ui.btn_reset_addr || 'Reset to Default')}</button>
        </header>
        <div class="zrx-addr-filter-row">
          <div class="zrx-addr-search-wrap"><input type="text" id="zrx-addr-search-input" placeholder="${escapeHtml(ui.addr_search_placeholder || 'Search address...')}" autocomplete="off"></div>
          <div class="zrx-addr-filter-tabs" id="zrx-addr-filter-tabs-container">
            <button type="button" class="zrx-addr-tab-btn active" data-addr-filter="all">${escapeHtml(ui.tab_all || 'All')}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="custom">${escapeHtml(ui.tab_custom || 'Custom Only')}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="system">${escapeHtml(ui.tab_system || 'System')}</button>
            <button type="button" class="zrx-addr-tab-btn" data-addr-filter="pinned">${escapeHtml(ui.tab_pinned || 'Pinned')}</button>
          </div>
        </div>
        <div class="zrx-occ-toolbar">
          <div class="zrx-occ-add-form">
            <input type="text" id="zrx-new-addr-input" placeholder="${escapeHtml(ui.new_addr_placeholder || 'Type new custom address/area...')}" autocomplete="off">
            <button type="button" id="zrx-btn-add-addr" class="btn btn-primary btn-sm">${escapeHtml(ui.btn_add_addr || 'Add')}</button>
          </div>
        </div>
        <div class="zrx-directory-table-wrap">
          <table class="zrx-occ-table">
            <thead><tr>
              <th class="zrx-help-th-actions-alt">${escapeHtml(ui.th_actions || 'Actions')}</th>
              <th class="zrx-help-th-sl-alt">${escapeHtml(ui.th_type || 'Type')}</th>
              <th class="zrx-ta-l">${escapeHtml(ui.th_address || 'Address / Combination')}</th>
              <th class="zrx-help-th-usage">${escapeHtml(ui.th_usage || 'Usage')}</th>
            </tr></thead>
            <tbody id="zrx-addr-table-body"><tr><td colspan="4" class="zrx-empty-cell">${escapeHtml(ui.addr_loading || 'Loading addresses...')}</td></tr></tbody>
          </table>
        </div>
      </section>
    </section>`;
  window.ZimRxAddressSettings.initManager(root, ui);
})();
