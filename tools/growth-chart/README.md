# ZimRx Growth Chart Reference Dataset & Generator Tool

This directory contains the clinical reference datasets and reproducible build tooling for ZimRx's pediatric growth chart system.

The generated runtime asset is saved to:
`application/public/assets/js/data/growth_chart_data.js`

---

## Pediatric "Month-by-Month" Structure

* **Age Bins, Not Calendar Updates**: In pediatrics, "monthly dataset" refers to the child's chronological age in completed months (`m: 0` at birth up to `m: 240` at age 20), rather than a calendar update frequency.
* **Rapid Infancy Trajectories**: An infant at month 2 has vastly different physiological reference thresholds than an infant at month 3. The dataset provides exact LMS parameters for each month of life.
* **Permanent Clinical Standards**: The WHO 2006 Standards and CDC 2000 Reference curves are fixed global physiological baselines (comparable to standard normal human vital sign ranges) and do not fluctuate on a monthly basis.

---

## Primary Data Sources & Provenance

### 1. WHO Child Growth Standards (0 to 60 Months / 0 to 5 Years)
* **Official Primary Source**: World Health Organization (Geneva, Switzerland).
* **Official Portal**: [https://www.who.int/tools/child-growth-standards/standards](https://www.who.int/tools/child-growth-standards/standards)
* **Underlying Clinical Trial**: WHO Multicentre Growth Reference Study (MGRS), 1997-2003. Enrolled 8,440 healthy breastfed infants across 6 diverse international cohorts (Brazil, Ghana, India, Norway, Oman, USA).
* **Raw Reference Tables**: Downloaded directly from the WHO expanded statistical tables:
  * Weight-for-age (expanded LMS and Z-score tables for boys and girls, birth to 5 years).
  * Length/height-for-age (expanded LMS and Z-score tables for boys and girls, birth to 5 years).
  * Head circumference-for-age (expanded LMS and Z-score tables for boys and girls, birth to 5 years).
  * BMI-for-age (expanded LMS and Z-score tables for boys and girls, birth to 5 years).
* **Citation**: WHO Multicentre Growth Reference Study Group. *WHO Child Growth Standards: Length/height-for-age, weight-for-age, weight-for-length, weight-for-height and body mass index-for-age: Methods and development.* Geneva: World Health Organization, 2006.

### 2. CDC Growth Reference (24 to 240 Months / 2 to 20 Years)
* **Official Primary Source**: US Centers for Disease Control and Prevention (CDC) National Center for Health Statistics (NCHS).
* **Official Portal**: [https://www.cdc.gov/growthcharts/percentile_data_files.htm](https://www.cdc.gov/growthcharts/percentile_data_files.htm)
* **Underlying Clinical Trial**: National Health Examination Surveys (NHES II/III and NHANES I/II/III).
* **Extended BMI Update**: Incorporates CDC 2022 Extended BMI curves for severe childhood obesity ($Z > +2$ to $Z > +4$).
* **Citation**: Kuczmarski RJ, Ogden CL, Guo SS, et al. *2000 CDC Growth Charts for the United States: Methods and Development.* Vital and Health Statistics, Series 11, No. 246, 2002.

---

## Licensing & Legal Terms

1. **CDC 2000 / 2022 Reference Curves**:
   * **Status**: Public Domain (US Government work).
   * **Terms**: Free of copyright restrictions worldwide.
2. **WHO 2006 Child Growth Standards**:
   * **Status**: Open source (GNU GPL-3.0-or-later).
   * **Terms**: Standard tables and calculation algorithms are distributed under GPL-3 (from the official `WorldHealthOrganization/anthro` package).

---

## Data Schema & Mathematical Parameters

Each monthly record in `who_growth_data.json` and `cdc_growth_data.json` contains:

| Parameter | Type | Description |
|---|---|---|
| `m` | Integer | Chronological child age in completed months (0 to 240) |
| `L` | Float | Box-Cox power transformation ($L$) defining skewness |
| `M` | Float | Median value ($M$) of the reference population |
| `S` | Float | Generalized coefficient of variation ($S$) |
| `sd_minus_3` .. `sd_plus_3` | Float | Standard deviation boundary cutoffs (-3 SD to +3 SD) |
| `p3` .. `p97` | Float | Reference clinical percentiles (3rd, 5th, 10th, 25th, 50th, 75th, 90th, 95th, 97th) |

---

* `raw/`: Local cache of official raw CDC and WHO public health tables.
* `who_growth_data.json`: WHO 0-60 month clinical reference tables.
* `cdc_growth_data.json`: CDC 24-240 month clinical reference tables.
* `fetch_and_build_reference_json.php`: Pure PHP tool that fetches raw clinical tables from CDC and WHO public endpoints, mathematically verifies Cole's LMS transformations across all 9,408 statistical points, and validates datasets.
* `growth_chart_engine.js`: Clinical calculation algorithms:
  * Cole's LMS transformation formula: $Z = \frac{(X/M)^L - 1}{L \times S}$
  * WHO 2006 restricted standard deviation adjustment for extreme Z-scores ($Z > +3$ or $Z < -3$).
  * CDC 2022 Extended BMI classifications for severe childhood obesity.
  * Abramowitz & Stegun polynomial approximation for normal cumulative distribution function (CDF).
  * WHO/UNICEF Mid-Upper Arm Circumference (MUAC) triage bands.
* `generate_growth_chart_js.php`: Pure PHP deterministic build script that compiles datasets into `application/public/assets/js/data/growth_chart_data.js`.

---

## Step-by-Step Guide: How to Reproduce or Verify

To reproduce the compiled runtime dataset and verify clinical accuracy from scratch:

1. **Fetch and Verify Raw Tables**:
   Run the automated reference toolchain from the repository root:
   ```bash
   php tools/growth-chart/fetch_and_build_reference_json.php
   ```
   This command:
   * Checks or downloads the 7 official raw tables into `tools/growth-chart/raw/`.
   * Mathematically evaluates all percentiles and standard deviations via Cole's LMS formula ($X = M(1 + LSZ)^{1/L}$).
   * Automatically executes `generate_growth_chart_js.php` to regenerate `application/public/assets/js/data/growth_chart_data.js`.

2. **Command Options**:
   * `--fetch`: Download raw tables from official CDC and WHO public endpoints.
   * `--force`: Force re-download all raw tables even if cached locally.
   * `--verify`: Run mathematical precision verification on all series and rows.
   * `--compile`: Rebuild the production JavaScript asset.

3. **Run Test Suite**:
   Execute the automated test suite to verify calculation precision, LMS interpolation, and classification thresholds:
   ```bash
   php application/tests/run_tests.php
   ```


