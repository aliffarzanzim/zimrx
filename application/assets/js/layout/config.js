const availableLeftModules =[
  "P/C", "AI Analyzer", "History", "P/E", "Dx", "Ix",
  "Plan", "Note", "O/H", "M/H", "Paediatric History", "Bangla Converter", ""
];

const availableRightModules =[
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Text Pad", "OT Note", "Font Format", ""
];

const defaultLeftLayout =[
  "P/C", "AI Analyzer", "History", "P/E", "Dx", "Ix",
  "Plan", "Note", "O/H", "M/H", "Paediatric History", "Bangla Converter"
];

const defaultRightLayout =[
  "Rx", "Drug Summary & Interaction", "Advice", "Report Entry", "Upload Reports & Documents", "Calculators",
  "Text Pad", "OT Note", "Font Format"
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
  "P/C": "p_c.php",
  "AI Analyzer": "ai_analyzer.php",
  "History": "history.php",
  "P/E": "p_e.php",
  "O/E": "p_e.php",
  "Dx": "dx.php",
  "Ix": "ix.php",
  "Plan": "plan.php",
  "Note": "note.php",
  "O/H": "o_h.php",
  "M/H": "m_h.php",
  "Paediatric History": "paediatric_history.php",
  "Bangla Converter": "bangla_converter.php",
  "Rx": "rx.php",
  "Drug Summary & Interaction": "drug_summary_interaction.php",
  "Advice": "advice.php",
  "Report Entry": "report_entry.php",
  "Upload Reports & Documents": "uploaded_reports.php",
  "Uploaded Reports": "uploaded_reports.php",
  "Reports": "reports.php",
  "Calculators": "calculators.php",
  "Text Pad": "text_pad.php",
  "OT Note": "ot_note.php",
  "Font Format": "font_format.php"
};

/**
 * Get current layout configuration from localStorage or defaults
 */
