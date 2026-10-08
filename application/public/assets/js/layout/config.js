// Default module configurations, storage keys, and module-to-file path mappings for the clinical workspace.
const availableLeftModules =[
  "P/C", "AI Analyzer", "History", "P/E", "Breast Examination", "Local Examination",
  "Burn Assessment", "ENT Examination", "Dental Chart", "Diabetic Foot", "Dermatology", "Psychiatry", "Orthopaedics", "Urology", "Neurology", "Cardiology", "Pulmonology", "Endocrinology",
  "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History", ""
];

const availableRightModules =[
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Ophthalmology", "Text Pad", "OT Note", "Font Format", ""
];

const defaultLeftLayout =[
  "P/C", "AI Analyzer", "History", "P/E", "Breast Examination", "Local Examination",
  "Burn Assessment", "ENT Examination", "Dental Chart", "Diabetic Foot", "Dermatology", "Psychiatry", "Orthopaedics", "Urology", "Neurology", "Cardiology", "Pulmonology", "Endocrinology",
  "Dx", "Ix", "Plan", "Note", "O/H", "M/H", "Paediatric History"
];

const defaultRightLayout =[
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Ophthalmology", "Text Pad", "OT Note", "Font Format"
];

const storageKeys = {
  leftLayout: 'zimrx_left_layout',
  rightLayout: 'zimrx_right_layout',
  historyLayout: 'zimrx_history_layout',
  dropdownTheme: 'zimrx_dropdown_theme',
  dropdownHoverBg: 'zimrx_dropdown_hover_bg',
  dropdownHoverText: 'zimrx_dropdown_hover_text',
  previewDrugs: 'zimrx_preview_drugs',
  previewSnapshot: 'zimrx_preview_snapshot',
  adviceTemplates: 'zimrx_static_advice_templates'
};

const moduleFileMap = {
  "P/C": "pc.php",
  "AI Analyzer": "ai_analyzer.php",
  "History": "history.php",
  "P/E": "pe.php",
  "O/E": "pe.php",
  "Breast Examination": "exam_breast.php",
  "Local Examination": "exam_local.php",
  "Burn Assessment": "exam_burn.php",
  "ENT Examination": "exam_ent.php",
  "Dental Chart": "exam_dental.php",
  "Diabetic Foot": "exam_diabetic_foot.php",
  "Dermatology": "exam_dermatology.php",
  "Psychiatry": "exam_psychiatry.php",
  "Orthopaedics": "exam_orthopaedics.php",
  "Urology": "exam_urology.php",
  "Neurology": "exam_neurology.php",
  "Cardiology": "exam_cardiology.php",
  "Pulmonology": "exam_pulmonology.php",
  "Endocrinology": "exam_endocrinology.php",
  "Dx": "dx.php",
  "Ix": "ix.php",
  "Plan": "plan.php",
  "Note": "note.php",
  "O/H": "oh.php",
  "D/H": "dh.php",
  "M/H": "m_h.php",
  "Paediatric History": "paediatric.php",
  "Rx": "rx.php",
  "Drug Summary & Interaction": "drug_summary.php",
  "Advice": "advice.php",
  "Report Entry": "report_entry.php",
  "Upload Reports & Documents": "uploaded_reports.php",
  "Uploaded Reports": "uploaded_reports.php",
  "Reports": "reports.php",
  "Calculators": "calculators.php",
  "Ophthalmology": "exam_ophthalmology.php",
  "Text Pad": "text_pad.php",
  "OT Note": "ot_note.php",
  "Font Format": "font_format.php"
};
