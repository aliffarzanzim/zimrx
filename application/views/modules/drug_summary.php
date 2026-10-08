<?php
declare(strict_types=1);

// Drug summary & interaction module: prescription costs, antibiotic alerts, and drug interaction checker.
?>
<div class="drug-summary-wrapper" id="drug-summary-module">
    <div class="drug-summary-header">
        <div>
            <h3>Drug Summary & Interaction</h3>
            <div class="drug-summary-subline" id="drug-summary-status">No drug selected</div>
        </div>
        <button type="button" class="drug-interaction-open-btn" id="drug-interaction-open">Drug Interaction</button>
    </div>

    <div class="drug-summary-metrics">
        <div class="drug-summary-metric">
            <span>Total drug prescribed</span>
            <strong id="drug-summary-total">0</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Daily expense</span>
            <strong id="drug-summary-daily">Tk 0.00</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Total expense</span>
            <strong id="drug-summary-course">Tk 0.00</strong>
        </div>
        <div class="drug-summary-metric">
            <span>Antibiotic</span>
            <strong id="drug-summary-antibiotic">No</strong>
        </div>
    </div>

    <div class="drug-antibiotic-note" id="drug-antibiotic-note" hidden>
        Please counsel about completing full dose and AMR.
    </div>

    <div class="drug-summary-grid">
        <section class="drug-summary-section" id="drug-summary-interaction-section">
            <div class="drug-summary-section-head">
                <span>Expense per drug</span>
            </div>
            <div class="drug-summary-table-wrap">
                <table class="drug-summary-table">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Daily</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="drug-summary-expense-rows">
                        <tr><td colspan="3" class="drug-summary-empty">No selected drugs</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="drug-summary-section">
            <div class="drug-summary-section-head">
                <span>Interactions between chosen drugs</span>
                <strong id="drug-summary-interaction-count">0</strong>
            </div>
            <div class="drug-interaction-list" id="drug-summary-interactions">
                <div class="drug-summary-empty">No interaction checked yet</div>
            </div>
        </section>
    </div>
</div>

<div class="drug-interaction-modal" id="drug-interaction-modal" hidden>
    <div class="drug-interaction-backdrop" data-drug-interaction-close></div>
    <div class="drug-interaction-panel" role="dialog" aria-modal="true" aria-labelledby="drug-interaction-title">
        <div class="drug-interaction-header">
            <h3 id="drug-interaction-title">Drug Interaction</h3>
            <button type="button" class="drug-interaction-close" data-drug-interaction-close aria-label="Close Drug Interaction">&times;</button>
        </div>

        <div class="drug-interaction-body">
            <div class="drug-interaction-inputs">
                <label>
                    <span>Drug A</span>
                    <input type="text" class="drug-interaction-drug-input" id="drug-interaction-a" autocomplete="off">
                    <small id="drug-interaction-a-meta"></small>
                </label>
                <label>
                    <span>Drug B</span>
                    <input type="text" class="drug-interaction-drug-input" id="drug-interaction-b" autocomplete="off">
                    <small id="drug-interaction-b-meta"></small>
                </label>
            </div>
            <button type="button" class="drug-interaction-check-btn" id="drug-interaction-check">Check Drug</button>
            <div class="drug-interaction-result" id="drug-interaction-result">Select two drugs to check.</div>
        </div>
    </div>
</div>

<script src="assets/js/modules/drug_summary.js?v=<?= filemtime(ZIMRX_PUBLIC_DIR . '/assets/js/modules/drug_summary.js') ?>"></script>
