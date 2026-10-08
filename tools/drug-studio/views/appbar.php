<?php
declare(strict_types=1);
?>
<header class="drug-appbar">
    <div class="drug-brand" id="brandHomeLink" title="Return to Studio Hub">
        <?= zrx_icon('pill', 20) ?>
        <span>ZimRx Drug Studio</span>
    </div>

    <div class="drug-actions" id="drugActionsBar">
        <button type="button" class="drug-btn" id="btnStudioHome" title="Return to Studio Home">
            <?= zrx_icon('grid', 13) ?>
            <span>Hub</span>
        </button>

        <button type="button" class="drug-btn" id="btnNavGenerics" title="Open Generics Workspace">
            <?= zrx_icon('pill', 13) ?>
            <span>Generics</span>
        </button>

        <button type="button" class="drug-btn" id="btnNavFormularies" title="Open Country Formularies Workspace">
            <?= zrx_icon('globe', 13) ?>
            <span>Formularies</span>
        </button>

        <button type="button" class="drug-btn" id="btnNavDosageForms" title="Open Dosage Forms Workspace">
            <?= zrx_icon('capsules', 13) ?>
            <span>Dosage Forms</span>
        </button>

        <button type="button" class="drug-btn" id="btnOpenManifest" title="Edit author, contributors, and license metadata">
            <?= zrx_icon('edit', 13) ?>
            <span>Manifest</span>
        </button>

        <div class="drug-dropdown-wrap" id="exportDropdownWrap">
            <button type="button" class="drug-btn drug-btn-success" id="btnExportToggle" title="Export seed options">
                <?= zrx_icon('download', 13) ?>
                <span>Export Seeds</span>
                <?= zrx_icon('chevron-down', 12) ?>
            </button>
            <div class="drug-dropdown-menu" id="exportMenu">
                <button type="button" class="drug-dropdown-item-btn" id="optExportCoreSeed">
                    <span class="drug-menu-icon"><?= zrx_icon('file-text', 14) ?></span>
                    <div>
                        <div class="drug-menu-title">Export Core Seed (zimrx_drugs.sql)</div>
                        <div class="drug-menu-desc">Save core generics, ATC &amp; clinical data</div>
                    </div>
                </button>
                <button type="button" class="drug-dropdown-item-btn" id="optExportCountrySeed">
                    <span class="drug-menu-icon"><?= zrx_icon('globe', 14) ?></span>
                    <div>
                        <div class="drug-menu-title">Export Country Pack (<span id="menuCountryCode">BD</span>)</div>
                        <div class="drug-menu-desc">Save trade brands and manufacturers</div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</header>
