#!/usr/bin/env python3
"""
ZimRx Growth Chart Generator
=============================
Generates application/assets/js/data/growth_chart_data.js from WHO and CDC
clinical reference tables.

Maintains 100% synchronous browser execution speed for instant chart plotting
while compacting month-by-month lookup rows to keep repository lines clean (~1,020 lines vs 14,000 lines).

Usage:
    python generate_growth_chart_js.py
"""

import os
import json

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
APP_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "..", "application"))
TARGET_JS = os.path.join(APP_DIR, "assets", "js", "data", "growth_chart_data.js")

WHO_JSON_PATH = os.path.join(SCRIPT_DIR, "who_growth_data.json")
CDC_JSON_PATH = os.path.join(SCRIPT_DIR, "cdc_growth_data.json")
ENGINE_JS_PATH = os.path.join(SCRIPT_DIR, "growth_chart_engine.js")

def format_row(row_dict):
    """Format a single monthly record compactly on one line."""
    items = []
    for k, v in row_dict.items():
        if isinstance(v, (int, float)):
            items.append(f'"{k}": {v}')
        else:
            items.append(f'"{k}": "{v}"')
    return "      { " + ", ".join(items) + " }"

def format_dataset(dataset_dict):
    """Format a dictionary of metric tables into valid JavaScript."""
    lines = ["{\n"]
    keys = list(dataset_dict.keys())
    for k_idx, key in enumerate(keys):
        rows = dataset_dict[key]
        lines.append(f'    "{key}": [\n')
        for r_idx, row in enumerate(rows):
            comma = "," if r_idx < len(rows) - 1 else ""
            lines.append(f"{format_row(row)}{comma}\n")
        table_comma = "," if k_idx < len(keys) - 1 else ""
        lines.append(f"    ]{table_comma}\n")
    lines.append("  }")
    return "".join(lines)

def build_growth_chart_js():
    if not os.path.exists(WHO_JSON_PATH):
        raise FileNotFoundError(f"Missing WHO dataset: {WHO_JSON_PATH}")
    if not os.path.exists(CDC_JSON_PATH):
        raise FileNotFoundError(f"Missing CDC dataset: {CDC_JSON_PATH}")
    if not os.path.exists(ENGINE_JS_PATH):
        raise FileNotFoundError(f"Missing engine code: {ENGINE_JS_PATH}")

    with open(WHO_JSON_PATH, "r", encoding="utf-8") as f:
        who_data = json.load(f)
    with open(CDC_JSON_PATH, "r", encoding="utf-8") as f:
        cdc_data = json.load(f)
    with open(ENGINE_JS_PATH, "r", encoding="utf-8") as f:
        engine_code = f.read().strip()

    header = """// Child growth chart dataset and calculation engine for WHO (0-5y) and CDC (2-20y) standards.
// Generated via tools/growth-chart/generate_growth_chart_js.py.

(function(root) {
  'use strict';

  const WHO_DATA = """

    cdc_start = "\n\n  const CDC_DATA = "
    
    calc_start = "\n\n  // Clinical calculation engine and percentile/Z-score classification logic\n\n  "

    js_content = (
        header +
        format_dataset(who_data) +
        ";" +
        cdc_start +
        format_dataset(cdc_data) +
        ";" +
        calc_start +
        engine_code +
        "\n\n  root.ZimRxGrowthData = {\n" +
        "    WHO: WHO_DATA,\n" +
        "    CDC: CDC_DATA,\n" +
        "    calculateZScore,\n" +
        "    getGrowthClassification,\n" +
        "    getMUACClassification,\n" +
        "    getAgeInMonths,\n" +
        "    getLmsAtAge\n" +
        "  };\n\n" +
        "})(typeof window !== 'undefined' ? window : this);\n"
    )

    os.makedirs(os.path.dirname(TARGET_JS), exist_ok=True)
    with open(TARGET_JS, "w", encoding="utf-8", newline="\n") as f:
        f.write(js_content)

    total_lines = len(js_content.splitlines())
    size_kb = len(js_content.encode("utf-8")) / 1024
    print(f"[OK] Generated {TARGET_JS}")
    print(f"     Lines: {total_lines} | Size: {size_kb:.1f} KB")

if __name__ == "__main__":
    build_growth_chart_js()
