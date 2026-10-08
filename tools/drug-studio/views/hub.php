<?php
declare(strict_types=1);
?>
<div id="viewHub" class="drug-hub-view">
    <div class="drug-hub-inner">
        <div class="drug-hub-hero">
            <div>
                <h1 class="drug-hub-title">Clinical Drug Catalog &amp; Formularies Studio</h1>
                <p class="drug-hub-subtitle">
                    Offline pharmaceutical catalog curation workspace for active generics, therapeutic classes, indications, country commercial formularies, and normalized dosage forms.
                </p>
            </div>
            <div class="drug-hub-stats-row">
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalGenerics">0</span>
                    <span class="hub-stat-lbl">Active Generics</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalBrands">0</span>
                    <span class="hub-stat-lbl">Trade Brands</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalDosageForms">0</span>
                    <span class="hub-stat-lbl">Dosage Forms</span>
                </div>
                <div class="hub-stat-item">
                    <span class="hub-stat-val" id="hubTotalPacks">0</span>
                    <span class="hub-stat-lbl">Country Packs</span>
                </div>
            </div>
        </div>

        <div class="drug-hub-grid">
            <!-- 1. Generics Card -->
            <div class="drug-card drug-hub-card" id="cardGenericsHub">
                <div class="drug-hub-card-header">
                    <div class="drug-hub-icon-wrap">
                        <?= zrx_icon('pill', 24) ?>
                    </div>
                    <div>
                        <h2 class="drug-hub-card-title">Generics &amp; Therapeutics</h2>
                        <div class="drug-hub-card-badge">Universal Core</div>
                    </div>
                </div>
                <p class="drug-hub-card-desc">
                    Curate clinical active substances, WHO ATC classifications, clinical indications, mechanism of action, pregnancy safety profiles, hepatic/renal dosing, and critical precautions.
                </p>
                <div class="drug-hub-card-footer">
                    <button type="button" class="drug-btn drug-btn-primary" id="btnHubOpenGenerics">
                        <?= zrx_icon('eye', 13) ?>
                        <span>Open Generics Workspace</span>
                    </button>
                </div>
            </div>

            <!-- 2. Formularies Card -->
            <div class="drug-card drug-hub-card" id="cardFormulariesHub">
                <div class="drug-hub-card-header">
                    <div class="drug-hub-icon-wrap">
                        <?= zrx_icon('globe', 24) ?>
                    </div>
                    <div>
                        <h2 class="drug-hub-card-title">Country Formularies</h2>
                        <div class="drug-hub-card-badge">National Packs</div>
                    </div>
                </div>
                <p class="drug-hub-card-desc">
                    Manage country commercial trade names, delivery variants, package sizes, unit pricing, and licensed pharmaceutical manufacturers linked to the core generic knowledge base.
                </p>
                <div class="drug-hub-card-footer">
                    <button type="button" class="drug-btn drug-btn-primary" id="btnHubOpenFormularies">
                        <?= zrx_icon('eye', 13) ?>
                        <span>Open Formularies Workspace</span>
                    </button>
                </div>
            </div>

            <!-- 3. Dosage Forms Card -->
            <div class="drug-card drug-hub-card" id="cardDosageFormsHub">
                <div class="drug-hub-card-header">
                    <div class="drug-hub-icon-wrap">
                        <?= zrx_icon('capsules', 24) ?>
                    </div>
                    <div>
                        <h2 class="drug-hub-card-title">Dosage Forms &amp; Delivery</h2>
                        <div class="drug-hub-card-badge">Prescription Formats</div>
                    </div>
                </div>
                <p class="drug-hub-card-desc">
                    Define standardized pharmaceutical delivery forms, prescription short/full prefixes, variant aliases, strength appending rules, and display ordering.
                </p>
                <div class="drug-hub-card-footer">
                    <button type="button" class="drug-btn drug-btn-primary" id="btnHubOpenDosageForms">
                        <?= zrx_icon('eye', 13) ?>
                        <span>Open Dosage Forms Workspace</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
