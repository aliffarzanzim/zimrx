(async function () {
  const root = document.getElementById('zrx-directory-settings-root');
  if (!root || !window.ZimRxDirectorySettings) return;

  const escapeHtml = (value) => window.ZimRxUtils.escapeHtml(value);
  const lang = window.ZimRxHelpInitialLang || 'en';
  let catalog;
  try {
    const response = await fetch(`api/get_directory_settings.php?lang=${encodeURIComponent(lang)}`);
    if (!response.ok) throw new Error('Directory settings catalog could not be loaded.');
    catalog = await response.json();
  } catch (error) {
    root.textContent = 'Directory settings could not be loaded.';
    console.error(error);
    return;
  }

  const page = catalog.page || {};
  const backLink = document.querySelector('.zrx-directory-settings-page .zrx-settings-back-link');
  const pageHeader = document.querySelector('.zrx-directory-settings-page .zrx-settings-index-header');
  if (backLink) backLink.textContent = page.back || 'Back to Settings';
  if (pageHeader) {
    pageHeader.querySelector('h1').textContent = page.title || 'Address and Occupation Directories';
    pageHeader.querySelector('p').textContent = page.intro || '';
  }
  document.querySelectorAll('[data-directory-tab]').forEach((tab) => {
    tab.textContent = catalog.tabs?.[tab.dataset.directoryTab] || tab.textContent;
  });

  const labels = {
    occupation: catalog.occupation || {},
    address: catalog.address || {}
  };

  const buildOccupationPanel = () => {
    const section = labels.occupation;
    const ui = section.ui || {};
    root.innerHTML = `
      <section class="zrx-directory-panel" aria-labelledby="zrx-occupation-directory-title">
        <h2 id="zrx-occupation-directory-title">${escapeHtml(section.title || 'Occupation Directory')}</h2>
        <p>${escapeHtml(section.description || '')}</p>
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
    window.ZimRxDirectorySettings.initOccupationManager(root, ui);
  };

  const buildAddressPanel = () => {
    const section = labels.address;
    const ui = section.ui || {};
    root.innerHTML = `
      <section class="zrx-directory-panel" aria-labelledby="zrx-address-directory-title">
        <h2 id="zrx-address-directory-title">${escapeHtml(section.title || 'Address Directory')}</h2>
        <p>${escapeHtml(section.description || '')}</p>
        <section class="zrx-addr-section">
          <header class="zrx-addr-section-header">
            <h3 class="zrx-addr-section-title">${ZimRxIcon.render('globe', 16)} ${escapeHtml(ui.section_districts_title || 'Practice Districts Filter')}</h3>
            <div class="zrx-help-flex-gap6">
              <button type="button" id="zrx-btn-dist-all" class="btn btn-outline btn-sm">${escapeHtml(ui.btn_dist_all || 'All Districts')}</button>
              <button type="button" id="zrx-btn-dist-save" class="btn btn-primary btn-sm">${escapeHtml(ui.btn_dist_save || 'Save Districts')}</button>
            </div>
          </header>
          <div class="zrx-district-search-bar"><input type="text" id="zrx-district-filter-input" placeholder="${escapeHtml(ui.district_search_placeholder || 'Search District...')}" autocomplete="off"></div>
          <div class="zrx-district-chips-wrap" id="zrx-district-chips-container"><span class="zrx-help-text-meta">${escapeHtml(ui.districts_loading || 'Loading districts...')}</span></div>
        </section>
        <section class="zrx-addr-section">
          <header class="zrx-addr-section-header">
            <h3 class="zrx-addr-section-title">${ZimRxIcon.render('map-pin', 16)} ${escapeHtml(ui.section_addr_title || 'Address Directory')}</h3>
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
            <table class="zrx-occ-table"><thead><tr>
              <th class="zrx-help-th-actions-alt">${escapeHtml(ui.th_actions || 'Actions')}</th>
              <th class="zrx-help-th-sl-alt">${escapeHtml(ui.th_type || 'Type')}</th>
              <th class="zrx-ta-l">${escapeHtml(ui.th_address || 'Address / Combination')}</th>
              <th class="zrx-help-th-usage">${escapeHtml(ui.th_usage || 'Usage')}</th>
            </tr></thead><tbody id="zrx-addr-table-body"><tr><td colspan="4" class="zrx-empty-cell">${escapeHtml(ui.addr_loading || 'Loading addresses...')}</td></tr></tbody></table>
          </div>
        </section>
      </section>`;
    window.ZimRxDirectorySettings.initAddressManager(root, ui);
  };

  const tabs = document.querySelectorAll('[data-directory-tab]');
  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((item) => {
        const active = item === tab;
        item.classList.toggle('active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      if (tab.dataset.directoryTab === 'address') buildAddressPanel();
      else buildOccupationPanel();
    });
  });

  buildOccupationPanel();
})();
