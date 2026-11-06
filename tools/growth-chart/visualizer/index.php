<?php

declare(strict_types=1);

header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Growth Reference Visualizer</title>
    <link rel="stylesheet" href="assets/css/visualizer.css">
    <script type="module" src="assets/js/app.js"></script>
</head>
<body>
    <main>
        <h1>Growth Reference Visualizer</h1>
        <p>CDC/NCHS reference data are public domain and available from CDC at no charge. This tool is not endorsed by CDC, HHS, or the U.S. Government.</p>
        <form id="growth-form">
            <label>Sex
                <select id="gender" name="gender">
                    <option value="Male">Boy</option>
                    <option value="Female">Girl</option>
                </select>
            </label>
            <label>Measure
                <select id="metric" name="metric">
                    <option value="weight">Weight-for-age</option>
                    <option value="height">Length/height-for-age</option>
                    <option value="hc">Head circumference-for-age</option>
                    <option value="bmi">BMI-for-age</option>
                </select>
            </label>
            <label>Reference
                <select id="standard" name="standard">
                    <option value="auto">Automatic</option>
                    <option value="who">WHO</option>
                    <option value="cdc">CDC</option>
                    <option value="cdc_extended" disabled>CDC 2022 Extended BMI</option>
                </select>
            </label>
            <label>Age in months
                <input id="age-months" name="ageMonths" type="number" min="0" step="any" aria-describedby="age-guidance" required>
            </label>
            <label id="height-position-control" hidden>Height measurement position
                <select id="height-position" name="heightPosition" aria-describedby="height-guidance">
                    <option value="standard">Standard for age</option>
                    <option value="recumbent">Measured lying down</option>
                    <option value="standing">Measured standing</option>
                </select>
            </label>
            <label>Measured value
                <input id="measured-value" name="value" type="number" min="0" step="any">
            </label>
            <button type="submit">Draw</button>
        </form>
        <aside id="age-guidance" class="info-box">
            Enter the child's age at measurement in months. For WHO references under five, convert age in days to months by dividing by 30.4375. Automatic uses WHO through 60 months and CDC after 60 months. Manual WHO uses WHO 2006 through 60 months and WHO 2007 from 61 months where available; no WHO series covers ages between 60 and 61 months. For BMI above the 97th percentile at ages 2 to 20 years, select CDC 2022 Extended BMI.
        </aside>
        <aside id="height-guidance" class="info-box" hidden>
            Standard position is lying down before 24 months and standing from 24 months. Select the recorded position if it differs. WHO 2006 applies a 0.7 cm conversion through 60 months; CDC 2000 uses 0.8 cm. WHO 2007 requires standing height. <a href="data-sources.html#measurement-position">Measurement method sources</a>.
        </aside>
        <p id="chart-status" role="status">Loading reference data.</p>
        <svg id="growth-chart" role="img" aria-label="Growth reference chart"></svg>
        <footer>
            <p><a href="data-sources.html">Data sources and licensing</a></p>
        </footer>
    </main>
</body>
</html>
