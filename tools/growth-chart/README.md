# ZimRx Growth Chart Reference Dataset & Generator Tool

This directory contains the clinical reference datasets and reproducible build tooling for ZimRx's pediatric growth chart system.

The generated runtime asset is saved to:
`application/assets/js/data/growth_chart_data.js`

---

## Architecture & Design Rationale

1. **Deterministic, Reproducible Builds**: All WHO/CDC percentiles and LMS parameters are stored as auditable, transparent JSON tables (`who_growth_data.json` and `cdc_growth_data.json`).
2. **Synchronous Zero-Latency Plotting**: The build tool compiles the data and calculation engine directly into a standard JavaScript file. When a doctor writes a pediatric prescription or opens the growth module, chart plotting executes with 0 ms latency without async fetch delays or network dependency.
3. **Compact Source**: Each month's dataset record is compacted onto a single clean line, keeping the runtime file at ~1,020 lines (down from 13,972 lines) while retaining 100% numerical precision.

---

## File Contents

* `who_growth_data.json` - WHO Child Growth Standards (0 to 60 Months / 0 to 5 Years) for Weight-for-Age, Height/Length-for-Age, Head Circumference-for-Age, and BMI-for-Age.
* `cdc_growth_data.json` - CDC Growth Reference (24 to 240 Months / 2 to 20 Years) for older children and adolescents.
* `growth_chart_engine.js` - Core calculation algorithms:
  * Cole's LMS transformation formula: $Z = \frac{(X/M)^L - 1}{L \times S}$
  * WHO 2006 restricted standard deviation adjustment for extreme Z-scores ($Z > +3$ or $Z < -3$).
  * CDC 2022 Extended BMI classifications for severe childhood obesity.
  * Abramowitz & Stegun polynomial approximation for normal cumulative distribution function (CDF).
  * WHO/UNICEF Mid-Upper Arm Circumference (MUAC) triage bands.
* `generate_growth_chart_js.py` - The build tool that generates `application/assets/js/data/growth_chart_data.js`.

---

## Re-building or Updating Datasets

To update the tables or add new regional growth references:

1. Update the JSON files or modify `generate_growth_chart_js.py`.
2. Run the generator script:
   ```bash
   python generate_growth_chart_js.py
   ```
3. The script will automatically recompile and format `application/assets/js/data/growth_chart_data.js`.

