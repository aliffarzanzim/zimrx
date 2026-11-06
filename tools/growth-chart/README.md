# Growth chart tool

This tool contains growth reference data, the build script, and a basic visualizer for reviewing charts.

The data has been gathered and prepared for future use in the paediatrics module. Before integration, the charts need further development and real-world testing with paediatric and endocrine doctors and trainees in wards and outpatient departments (OPDs). This visualizer is for development and review, not clinical use.

## Visualizer

Run it from the repository root:

```sh
php -S 127.0.0.1:8090 -t tools/growth-chart/visualizer
```

Choose sex, measure, reference, and age in months. A measured value is optional and appears on the reference chart when entered.

For height-for-age, choose the recorded measurement position when it differs from the age-appropriate standard. The visualizer shows the reference-specific conversion in the chart status and documents its sources on the data-sources page.

## Reference data

Source URLs and SHA-256 hashes are recorded in `raw/source-manifest.json`.

- WHO Child Growth Standards, birth to 60 months, from the WHO Anthro repository.
- WHO Growth Reference 2007, ages 5 to 19 years, for weight, height, and BMI.
- CDC 2000 growth references, ages 2 to 20 years, for weight, stature, and BMI.
- CDC 2022 Extended BMI-for-Age reference, ages 2 to 20 years, with BMI curves through the 99.99th percentile.

Source URLs, SHA-256 hashes, citations, attribution, and rights status are recorded in raw/source-manifest.json.

The visualizer only plots ages supported by the selected measure and reference. WHO 2006 covers 0-60 months and retains its daily source rows, mapped to decimal months using 30.4375 days per month. WHO 2007 covers 61-228 months for height and BMI, and 61-120 months for weight. CDC 2000 files cover 24-240 months, with BMI data through 240.5 months.

CDC identifies its 2000 growth-chart report as public domain. When using CDC material, credit CDC/NCHS, state that CDC does not endorse this tool, do not substantively alter CDC material, and state that the source is available from CDC at no charge.

The 2022 extended reference is available for BMI-for-age only. CDC recommends it for children and adolescents with BMI above the 97th percentile. Its curves extend to the 99.99th percentile. The reference file and method are documented on CDC's [extended BMI data page](https://www.cdc.gov/growthcharts/extended-bmi-data-files.htm).

Generated CDC Extended records retain the source `sigma` and `pct120ofP95` fields for future implementation work. The current visualizer plots the supplied percentile curves and does not classify BMI severity.

WHO given permission the ZimRx Growth Reference Visualizer to use the WHO 2006 Child Growth Standards and the 2007 growth reference for noncommercial use. The authorization covers the JSON and age conversions disclosed in the permission application. The tables must not be distributed as a standalone dataset or made available for unrestricted reuse. Do not use them to market or promote ZimRx or imply WHO endorsement. Other changes to the licensed values require prior written WHO authorization. Required acknowledgements are listed in [Data sources and licensing](visualizer/data-sources.html).

## Build and check

Requires PHP 8.2 or later. The build uses string-based cURL protocol restrictions when supported and falls back to the PHP 8.2-compatible options. From the repository root, build or verify the generated visualizer data with:

```sh
php tools/growth-chart/bin/build_growth_chart_data.php
php tools/growth-chart/bin/build_growth_chart_data.php --verify
php tools/growth-chart/tests/verify_reference_pipeline.php
```

The build fetches missing source files from their pinned URLs, verifies all source files against their recorded hashes, and writes generated files under `visualizer/`. The raw WHO tables are build inputs and should not be shipped as a separate data set. `--verify` rebuilds in memory and checks the generated files without changing them. Use `--force` to fetch the source files again; fetched files must still match the recorded hashes.
