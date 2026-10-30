// Module availability and defaults are generated from validated server-side manifests.
const dashboardModuleConfigNode = document.getElementById('dashboard-module-config');
if (!dashboardModuleConfigNode) {
  throw new Error('Dashboard module configuration is missing.');
}
const dashboardModuleConfig = JSON.parse(dashboardModuleConfigNode.textContent);
const availableLeftModules = dashboardModuleConfig.available.left;
const availableRightModules = dashboardModuleConfig.available.right;
const defaultLeftLayout = dashboardModuleConfig.defaults.left;
const defaultRightLayout = dashboardModuleConfig.defaults.right;

const storageKeys = {
  leftLayout: 'zimrx_left_layout',
  rightLayout: 'zimrx_right_layout',
  historyLayout: 'zimrx_history_layout',
  previewDrugs: 'zimrx_preview_drugs',
  previewSnapshot: 'zimrx_preview_snapshot',
  adviceTemplates: 'zimrx_static_advice_templates'
};
