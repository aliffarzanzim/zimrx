<?php
require_once 'auth.php';
require_login();
require_once 'db.php';
require_once 'print_setup_lib.php';

function checked_attr(string $current, string $target): string {
    return $current === $target ? 'checked' : '';
}

$page_title = 'ZimRx - Header, Footer & Background Setup';
$extra_css = ['assets/css/header_footer_background_setup.css'];
$doctorId = current_user_doctor_id();

$header = zimrx_bridge_load_header_settings($pdo, $doctorId);
$options = zimrx_bridge_load_print_options($pdo, $doctorId);
$hasOnboarded = (int)($options['has_onboarded'] ?? 0);

// Existing doctor check: if not onboarded in settings, but already has a custom header, auto-onboard them
if ($hasOnboarded === 0 && (trim((string)($header['left_block_html'] ?? '')) !== '' || trim((string)($header['right_block_html'] ?? '')) !== '')) {
    $hasOnboarded = 1;
    try {
        $advancedData = json_decode((string)($options['print_settings_json'] ?? '{}'), true);
        if (!is_array($advancedData)) {
            $advancedData = [];
        }
        $advancedData['has_onboarded'] = 1;
        $pdo->prepare("UPDATE zimrx_prescription_print_layout_settings SET print_settings_json = :json WHERE doctor_id = :doctor_id")
            ->execute(['json' => json_encode($advancedData), 'doctor_id' => $doctorId]);
    } catch (Exception $e) {
        // Ignore
    }
}

$pageWidth = isset($options['page_width']) && is_numeric($options['page_width']) ? (float)$options['page_width'] : 21.0;
$leftLines = zimrx_bridge_header_lines($header, 'left');
$rightLines = zimrx_bridge_header_lines($header, 'right');
$displayLogo = strtolower((string)($header['display_logo'] ?? 'yes')) === 'no' ? 'no' : 'yes';
$bgColor = strtoupper(ltrim((string)($header['bg_color'] ?? 'FFFFFF'), '#'));
$logoPath = trim((string)($header['logo_path'] ?? ''));
$footerHtml = (string)($header['footer_html'] ?? '');
$headerType = (string)($header['header_type'] ?? ($options['header_type'] ?? 'text'));
if ($headerType !== 'image') {
    $headerType = 'text';
}
$fullBodyHeaderPath = trim((string)($header['full_body_header_path'] ?? ''));
$bgImagePath    = trim((string)($header['bg_image_path'] ?? ''));
$bgImageOpacity = (float)($header['bg_image_opacity'] ?? 0.10);
$bgImageScale   = (float)($header['bg_image_scale']   ?? 1.0);
$bgImageAngle   = (float)($header['bg_image_angle']   ?? 0.0);
$bgImageOffsetX = (float)($header['bg_image_offset_x'] ?? 0.0);
$bgImageOffsetY = (float)($header['bg_image_offset_y'] ?? 0.0);

$stampPath    = trim((string)($options['stamp_path'] ?? ''));
$stampOpacity = (float)($options['stamp_opacity'] ?? 1.0);
$stampScale   = (float)($options['stamp_scale']   ?? 1.0);
$stampAngle   = (float)($options['stamp_angle']   ?? 0.0);
$stampOffsetX = (float)($options['stamp_offset_x'] ?? 0.0);
$stampOffsetY = (float)($options['stamp_offset_y'] ?? 0.0);
$stampColor   = trim((string)($options['stamp_color'] ?? '#000000'));
if ($stampColor === '') $stampColor = '#000000';
$stampColorEnable = trim((string)($options['stamp_color_enable'] ?? 'no'));
if ($stampColorEnable === '') $stampColorEnable = 'no';

$leftBlockHtml = zimrx_bridge_visual_block_html($header, 'left', $leftLines);
$rightBlockHtml = zimrx_bridge_visual_block_html($header, 'right', $rightLines);


include 'header.php';
?>

<main class="header-editor-page">
    <div class="zps-heading-card">
        <div class="zps-heading">
            <div>
                <h1>Header, Footer &amp; Background Setup</h1>
                <p>Customize your prescription header text/image, footer, background watermark, and stamp.</p>
            </div>
            <div class="header-editor-heading-actions" style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="prescription_preview.php" target="_blank" class="btn btn-outline">Full Preview</a>
                <a href="page_setup.php" class="btn btn-outline">Page Setup</a>
                <a href="print_setup.php" class="btn btn-outline">Print Setup</a>
                <button type="submit" form="zimrx-header-form" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <form id="zimrx-header-form" class="header-editor-form">
        <!-- Hidden input for full_body_header_path -->
        <input type="hidden" name="full_body_header_path" id="full_body_header_path" value="<?= preview_escape($fullBodyHeaderPath) ?>">

        <!-- Header Editor Outer Box -->
        <div class="header-editor-container">
            <section class="header-editor-card header-editor-main-card">
                <div class="header-card-topbar">
                    <h2>Header Editor</h2>
                    <div class="header-type-toggle">
                        <label class="header-type-radio">
                            <input type="radio" name="header_type" value="text" <?= checked_attr($headerType, 'text') ?>> Text Header
                        </label>
                        <label class="header-type-radio">
                            <input type="radio" name="header_type" value="image" <?= checked_attr($headerType, 'image') ?>> Image Header (With Body)
                        </label>
                    </div>
                </div>

                <!-- View 1: Standard 4-Column Text Header Draft -->
                <div id="zrx-header-draft" class="header-editor-panels zrx-header-draft <?= $headerType === 'image' ? 'is-hidden' : '' ?> <?= $displayLogo === 'yes' ? 'zrx-has-logo' : 'zrx-no-logo' ?>">
                    
                    <!-- Left Column Panel -->
                    <div class="header-editor-panel panel-left">
                        <h2>Left Side Header</h2>
                        <div class="panel-content" style="background: #<?= preview_escape($bgColor) ?>;">
                            <textarea name="left_block_html" id="left_block_html" style="width: 100%;"><?= preview_escape($leftBlockHtml) ?></textarea>
                        </div>
                    </div>

                    <!-- Middle/Logo Column Panel -->
                    <div class="header-editor-panel panel-middle" id="header-logo-wrap">
                        <h2>Logo</h2>
                        <div class="panel-content logo-settings-panel">
                            <div class="logo-preview-box <?= $displayLogo === 'yes' ? '' : 'logo-hidden' ?>" style="position: relative;">
                                <img id="header-logo-preview" src="<?= preview_escape($logoPath) ?>" alt="Logo" class="<?= $logoPath ? '' : 'is-hidden' ?>">
                                <span id="header-logo-placeholder" class="zrx-logo-placeholder <?= $logoPath ? 'is-hidden' : '' ?>">Logo</span>
                                <button type="button" id="logo-remove-btn" class="zrx-bgimg-remove <?= $logoPath ? '' : 'is-hidden' ?>" title="Remove logo">&#x2715;</button>
                            </div>

                            <div class="logo-controls-box">
                                <input type="hidden" name="logo_path" id="logo_path" value="<?= preview_escape($logoPath) ?>">
                                <input type="file" id="header-logo-file" accept="image/*" hidden>
                                <div class="zrx-logo-select-row">
                                    <button type="button" id="logo-open-gallery" class="btn btn-outline" style="width: 100%;">Select Logo</button>
                                    <button type="button" id="upload-logo-trigger" class="btn btn-outline" title="Upload a new logo from your computer">&#8679; Upload</button>
                                </div>
                                <span id="logo-upload-status" class="upload-logo-status"></span>


                                <div class="logo-toggle-row">
                                    <label><input type="radio" name="display_logo" value="yes" <?= checked_attr($displayLogo, 'yes') ?>> Show</label>
                                    <label><input type="radio" name="display_logo" value="no" <?= checked_attr($displayLogo, 'no') ?>> Hide</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header Color Panel -->
                    <div class="header-editor-panel panel-color">
                        <h2>Header Color</h2>
                        <div class="panel-content color-only-panel">
                            <div class="color-picker-vertical">
                                <div class="color-picker-row">
                                    <input type="color" id="header-color-picker" value="#<?= preview_escape($bgColor) ?>">
                                    <label class="color-hex-label">
                                        <span>#</span>
                                        <input type="text" name="bgcolor" id="header-color-value" value="<?= preview_escape($bgColor) ?>" maxlength="6" pattern="[A-Fa-f0-9]{6}">
                                    </label>
                                </div>
                                <p class="color-hint">Background color for<br>the header section</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column Panel -->
                    <div class="header-editor-panel panel-right">
                        <h2>Right Side Header</h2>
                        <div class="panel-content" style="background: #<?= preview_escape($bgColor) ?>;">
                            <textarea name="right_block_html" id="right_block_html" style="width: 100%;"><?= preview_escape($rightBlockHtml) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- View 2: Image Header (With Body) Setup View -->
                <div id="zrx-image-header-view" class="image-header-view <?= $headerType === 'image' ? '' : 'is-hidden' ?>">
                    <div class="image-header-upload-shell">
                        <div class="image-file-row">
                            <input type="file" id="image-body-file" accept="image/png, image/jpeg, image/webp, image/svg+xml, .svg" class="image-body-file-input">
                        </div>
                        <div class="image-header-submit-row">
                            <button type="button" class="btn btn-outline image-body-submit-btn" id="image-body-submit">Submit</button>
                        </div>
                        <span id="image-body-upload-status" class="image-body-upload-status"></span>
                    </div>
                    
                    <div class="image-header-preview-container <?= $fullBodyHeaderPath !== '' ? '' : 'is-hidden' ?>" id="image-body-preview-container">
                        <div class="image-header-preview-box">
                            <img id="image-body-preview" src="<?= preview_escape($fullBodyHeaderPath) ?>" alt="Full Body Header Preview">
                        </div>
                    </div>

                    <div class="image-header-instruction-box">
                        <p>à¦«à§à¦²à¦¬à¦¡à¦¿ à¦ªà§à¦°à§‡à¦¸à¦•à§à¦°à¦¿à¦ªà¦¶à¦¨à§‡à¦° à¦œà¦¨à§à¦¯ A4 à¦¸à¦¾à¦‡à¦œà§‡à¦° 2480px x 3508px à¦¸à¦¾à¦‡à¦œà§‡à¦° SVG, JPG, PNG à¦›à¦¬à¦¿ à¦¬à§à¦¯à¦¬à¦¹à¦¾à¦° à¦•à¦°à¦¤à§‡ à¦¹à¦¬à§‡à¥¤</p>
                        <p>SVG à¦¤à§‡ à¦¸à¦¬à¦šà§‡à§Ÿà§‡ à¦‰à¦¨à§à¦¨à¦¤ à¦ªà§à¦°à¦¿à¦¨à§à¦Ÿ à¦¹à¦¬à§‡ (à¦…à§à¦¯à¦¾à¦¡à§‹à¦¬à¦¿ à¦‡à¦²à¦¾à¦¸à§à¦Ÿà§à¦°à§‡à¦Ÿà¦° à¦¦à¦¿à§Ÿà§‡ SVG à¦«à¦¾à¦‡à¦² à¦¬à¦¾à¦¨à¦¾à¦¤à§‡ à¦¹à§Ÿ)à¥¤ 2480 à¦¬à¦¾à¦‡ 3508 à¦ªà¦¿à¦•à§à¦¸à§‡à¦²à§‡à¦° à¦šà§‡à§Ÿà§‡ à¦¬à§‡à¦¶à¦¿ à¦®à¦¾à¦ªà§‡à¦° à¦›à¦¬à¦¿ à¦¬à§à¦¯à¦¬à¦¹à¦¾à¦° à¦•à¦°à¦¾ à¦¯à¦¾à¦¬à§‡, à¦¤à¦¬à§‡ Width à¦“ Height à¦à¦° à¦…à¦¨à§à¦ªà¦¾à¦¤ 1 : 1.41 à¦¥à¦¾à¦•à¦¤à§‡ à¦¹à¦¬à§‡à¥¤</p>
                        <p>à¦¡à¦¿à¦œà¦¾à¦‡à¦¨ à¦‡à¦²à¦¾à¦¸à§à¦Ÿà§à¦°à§‡à¦Ÿà¦° à¦¦à¦¿à§Ÿà§‡ à¦¨à¦¿à¦œà§‡ à¦•à¦°à¦¤à§‡ à¦ªà¦¾à¦°à§‡à¦¨ à¦¬à¦¾ à¦ªà§à¦°à§‡à¦¸ à¦¥à§‡à¦•à§‡ à¦•à¦°à¦¿à§Ÿà§‡ à¦¨à¦¿à¦¤à§‡ à¦ªà¦¾à¦°à§‡à¦¨à¥¤</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============================================================
             TWO-COLUMN LAYOUT: left = Background Image + Footer Editor
                                right = Live Preview
        ============================================================ -->
        <?php
        $footerWidth = (float)($options['footer_width'] ?? ($options['page_width'] ?? 21));
        if ($footerWidth <= 0) $footerWidth = 21;
        ?>
        <div class="zrx-lower-layout">

            <!-- LEFT COLUMN -->
            <div class="zrx-lower-left">

                <!-- Header Customization Card -->
                <section class="header-editor-card zrx-bgimg-card <?= $headerType === 'text' ? '' : 'is-disabled' ?>" id="header-customization-card" style="width:<?= $footerWidth ?>cm; margin-bottom: 1.5rem;">
                    <div class="zrx-bgimg-card-topbar" style="display:flex; justify-content:space-between; align-items:center;">
                        <h2>Header Customization</h2>
                        <button type="button" id="btn-reset-header-customization" class="zrx-reset-icon-btn" title="Reset Header Customization to Defaults" <?= $headerType === 'text' ? '' : 'disabled' ?>>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                <path d="M3 3v5h5"/>
                            </svg>
                        </button>
                        <?php if ($headerType !== 'text'): ?>
                        <span class="zrx-bgimg-disabled-note">Only available with Text Header</span>
                        <?php endif; ?>
                    </div>

                    <div class="control-row" style="margin-bottom: 1.25rem;">
                        <div style="flex: 1;">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 0.25rem; display: block;">Column Widths (%)</label>
                            <span style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem; display: block;">Note: Column widths must combine to exactly 100% if you edit them.</span>
                            <div style="display: flex; gap: 1rem;" id="header-widths-container">
                                <div style="flex: 1;" class="width-ctrl-left">
                                    <span style="font-size: 0.8rem; color:#64748b;">Left Column Width:</span>
                                    <input type="number" name="header_left_width" id="header_left_width" min="10" max="80" step="1" class="zps-size-input" style="width: 100%; margin-top: 4px;" value="<?= preview_escape($options['header_left_width'] ?? ($displayLogo === 'yes' ? '40' : '49')) ?>">
                                </div>
                                <div style="flex: 1;" class="width-ctrl-logo">
                                    <span style="font-size: 0.8rem; color:#64748b;">Logo Column Width:</span>
                                    <input type="number" name="header_logo_width" id="header_logo_width" min="5" max="50" step="1" class="zps-size-input" style="width: 100%; margin-top: 4px;" value="<?= preview_escape($options['header_logo_width'] ?? '18') ?>">
                                </div>
                                <div style="flex: 1;" class="width-ctrl-right">
                                    <span style="font-size: 0.8rem; color:#64748b;">Right Column Width:</span>
                                    <input type="number" name="header_right_width" id="header_right_width" min="10" max="80" step="1" class="zps-size-input" style="width: 100%; margin-top: 4px;" value="<?= preview_escape($options['header_right_width'] ?? ($displayLogo === 'yes' ? '40' : '49')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logo Transformation Panel -->
                    <div id="logo-customization-controls" class="<?= $displayLogo === 'yes' ? '' : 'is-hidden' ?>">
                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem; display: block; border-top: 1px solid #e2e8f0; padding-top: 1rem;">Logo Placement &amp; Transformation</label>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem 1.5rem;">
                            <!-- Scale Slider -->
                            <div class="control-group">
                                <div style="display:flex; justify-content:space-between; font-size: 0.8rem; color:#64748b;">
                                    <span>Logo Scale:</span>
                                    <strong id="logo-scale-val"><?= preview_escape($options['logo_scale'] ?? '100') ?>%</strong>
                                </div>
                                <input type="range" name="logo_scale" id="logo_scale" min="20" max="250" step="1" value="<?= preview_escape($options['logo_scale'] ?? '100') ?>" style="width: 100%; margin-top: 4px; accent-color: var(--primary);">
                            </div>
                            
                            <!-- Rotation Slider -->
                            <div class="control-group">
                                <div style="display:flex; justify-content:space-between; font-size: 0.8rem; color:#64748b;">
                                    <span>Logo Rotation:</span>
                                    <strong id="logo-rotation-val"><?= preview_escape($options['logo_rotation'] ?? '0') ?>°</strong>
                                </div>
                                <input type="range" name="logo_rotation" id="logo_rotation" min="-180" max="180" step="1" value="<?= preview_escape($options['logo_rotation'] ?? '0') ?>" style="width: 100%; margin-top: 4px; accent-color: var(--primary);">
                            </div>

                            <!-- Opacity Slider -->
                            <div class="control-group">
                                <div style="display:flex; justify-content:space-between; font-size: 0.8rem; color:#64748b;">
                                    <span>Logo Opacity:</span>
                                    <strong id="logo-opacity-val"><?= preview_escape($options['logo_opacity'] ?? '100') ?>%</strong>
                                </div>
                                <input type="range" name="logo_opacity" id="logo_opacity" min="0" max="100" step="1" value="<?= preview_escape($options['logo_opacity'] ?? '100') ?>" style="width: 100%; margin-top: 4px; accent-color: var(--primary);">
                            </div>

                            <!-- X Offset Slider -->
                            <div class="control-group">
                                <div style="display:flex; justify-content:space-between; font-size: 0.8rem; color:#64748b;">
                                    <span>Horizontal Mover (X):</span>
                                    <strong id="logo-offset-x-val"><?= preview_escape($options['logo_offset_x'] ?? '0') ?>px</strong>
                                </div>
                                <input type="range" name="logo_offset_x" id="logo_offset_x" min="-100" max="100" step="1" value="<?= preview_escape($options['logo_offset_x'] ?? '0') ?>" style="width: 100%; margin-top: 4px; accent-color: var(--primary);">
                            </div>

                            <!-- Y Offset Slider -->
                            <div class="control-group">
                                <div style="display:flex; justify-content:space-between; font-size: 0.8rem; color:#64748b;">
                                    <span>Vertical Mover (Y):</span>
                                    <strong id="logo-offset-y-val"><?= preview_escape($options['logo_offset_y'] ?? '0') ?>px</strong>
                                </div>
                                <input type="range" name="logo_offset_y" id="logo_offset_y" min="-100" max="100" step="1" value="<?= preview_escape($options['logo_offset_y'] ?? '0') ?>" style="width: 100%; margin-top: 4px; accent-color: var(--primary);">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Background Image Card (text header only) -->
                <section class="header-editor-card zrx-bgimg-card <?= $headerType === 'text' ? '' : 'is-disabled' ?>" id="bgimg-card" style="width:<?= $footerWidth ?>cm;">
                    <div class="zrx-bgimg-card-topbar">
                        <h2>Background Image</h2>
                        <?php if ($headerType !== 'text'): ?>
                        <span class="zrx-bgimg-disabled-note">Only available with Text Header</span>
                        <?php endif; ?>
                    </div>

                    <!-- Hidden inputs for bg_image fields -->
                    <input type="hidden" name="bg_image_path"    id="bg_image_path"    value="<?= preview_escape($bgImagePath) ?>">
                    <input type="hidden" name="bg_image_opacity" id="bg_image_opacity" value="<?= preview_escape((string)$bgImageOpacity) ?>">
                    <input type="hidden" name="bg_image_scale"   id="bg_image_scale"   value="<?= preview_escape((string)$bgImageScale) ?>">
                    <input type="hidden" name="bg_image_angle"   id="bg_image_angle"   value="<?= preview_escape((string)$bgImageAngle) ?>">
                    <input type="hidden" name="bg_image_offset_x" id="bg_image_offset_x" value="<?= preview_escape((string)$bgImageOffsetX) ?>">
                    <input type="hidden" name="bg_image_offset_y" id="bg_image_offset_y" value="<?= preview_escape((string)$bgImageOffsetY) ?>">

                    <div class="zrx-bgimg-body <?= $headerType !== 'text' ? 'is-locked' : '' ?>">
                        <!-- Select button + current thumb -->
                        <div class="zrx-bgimg-select-row">
                            <button type="button" class="btn btn-outline" id="bgimg-open-gallery">Select Background</button>
                            <div class="zrx-bgimg-current-thumb <?= $bgImagePath !== '' ? '' : 'is-hidden' ?>" id="bgimg-current-thumb">
                                <img id="bgimg-thumb-img" src="<?= preview_escape($bgImagePath) ?>" alt="Selected background">
                                <button type="button" class="zrx-bgimg-remove" id="bgimg-remove" title="Remove">&#x2715;</button>
                            </div>
                        </div>

                        <!-- Controls (shown only if image selected) -->
                        <div class="zrx-bgimg-controls <?= $bgImagePath !== '' ? '' : 'is-hidden' ?>" id="bgimg-controls">
                            <div class="zrx-bgimg-slider-row">
                                <label>Opacity <span id="bgimg-opacity-val"><?= round($bgImageOpacity * 100) ?>%</span></label>
                                <input type="range" min="0" max="100" step="1" id="bgimg-opacity-range" value="<?= round($bgImageOpacity * 100) ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Scale <span id="bgimg-scale-val"><?= round($bgImageScale * 100) ?>%</span></label>
                                <input type="range" min="10" max="300" step="1" id="bgimg-scale-range" value="<?= round($bgImageScale * 100) ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Rotation <span id="bgimg-angle-val"><?= $bgImageAngle ?>&deg;</span></label>
                                <input type="range" min="-180" max="180" step="1" id="bgimg-angle-range" value="<?= $bgImageAngle ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Horizontal <span id="bgimg-offsetx-val"><?= $bgImageOffsetX ?>px</span></label>
                                <input type="range" min="-500" max="500" step="1" id="bgimg-offsetx-range" value="<?= $bgImageOffsetX ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Vertical <span id="bgimg-offsety-val"><?= $bgImageOffsetY ?>px</span></label>
                                <input type="range" min="-500" max="500" step="1" id="bgimg-offsety-range" value="<?= $bgImageOffsetY ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Footer Editor: exact width from print settings -->
                <section class="header-editor-card rich-editor-card footer-editor-card" style="width:<?= $footerWidth ?>cm;">
                    <h2>Footer Editor</h2>
                    <div class="footer-editor-wrap">
                        <textarea name="footer_html" id="footer_html" spellcheck="false"><?= preview_escape($footerHtml) ?></textarea>
                    </div>
                </section>

                <!-- Seal & Stamp Card -->
                <section class="header-editor-card zrx-bgimg-card" id="stamp-card" style="width:<?= $footerWidth ?>cm;">
                    <div class="zrx-bgimg-card-topbar">
                        <h2>Seal & Stamp</h2>
                    </div>

                    <!-- Hidden inputs for stamp fields -->
                    <input type="hidden" name="stamp_path"     id="stamp_path"     value="<?= preview_escape($stampPath) ?>">
                    <input type="hidden" name="stamp_opacity"  id="stamp_opacity"  value="<?= preview_escape((string)$stampOpacity) ?>">
                    <input type="hidden" name="stamp_scale"    id="stamp_scale"    value="<?= preview_escape((string)$stampScale) ?>">
                    <input type="hidden" name="stamp_angle"    id="stamp_angle"    value="<?= preview_escape((string)$stampAngle) ?>">
                    <input type="hidden" name="stamp_offset_x" id="stamp_offset_x" value="<?= preview_escape((string)$stampOffsetX) ?>">
                    <input type="hidden" name="stamp_offset_y" id="stamp_offset_y" value="<?= preview_escape((string)$stampOffsetY) ?>">
                    <input type="hidden" name="stamp_color"    id="stamp_color"    value="<?= preview_escape($stampColor) ?>">
                    <input type="hidden" name="stamp_color_enable" id="stamp_color_enable" value="<?= preview_escape($stampColorEnable) ?>">

                    <div class="zrx-bgimg-body">
                        <!-- Select button + current thumb -->
                        <div class="zrx-bgimg-select-row">
                            <button type="button" class="btn btn-outline" id="stamp-open-gallery">Select Seal/Stamp</button>
                            <div class="zrx-bgimg-current-thumb <?= $stampPath !== '' ? '' : 'is-hidden' ?>" id="stamp-current-thumb">
                                <img id="stamp-thumb-img" src="<?= preview_escape($stampPath) ?>" alt="Selected Seal/Stamp">
                                <button type="button" class="zrx-bgimg-remove" id="stamp-remove" title="Remove">✕</button>
                            </div>
                        </div>

                        <!-- Controls (shown only if stamp image selected) -->
                        <div class="zrx-bgimg-controls <?= $stampPath !== '' ? '' : 'is-hidden' ?>" id="stamp-controls">
                            <div class="zrx-bgimg-slider-row">
                                <label>Opacity <span id="stamp-opacity-val"><?= round($stampOpacity * 100) ?>%</span></label>
                                <input type="range" min="0" max="100" step="1" id="stamp-opacity-range" value="<?= round($stampOpacity * 100) ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Scale <span id="stamp-scale-val"><?= round($stampScale * 100) ?>%</span></label>
                                <input type="range" min="10" max="300" step="1" id="stamp-scale-range" value="<?= round($stampScale * 100) ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Rotation <span id="stamp-angle-val"><?= $stampAngle ?>°</span></label>
                                <input type="range" min="-180" max="180" step="1" id="stamp-angle-range" value="<?= $stampAngle ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Horizontal <span id="stamp-offsetx-val"><?= $stampOffsetX ?>px</span></label>
                                <input type="range" min="-1200" max="1200" step="1" id="stamp-offsetx-range" value="<?= $stampOffsetX ?>">
                            </div>
                            <div class="zrx-bgimg-slider-row">
                                <label>Vertical <span id="stamp-offsety-val"><?= $stampOffsetY ?>px</span></label>
                                <input type="range" min="-1200" max="1200" step="1" id="stamp-offsety-range" value="<?= $stampOffsetY ?>">
                            </div>
                            <!-- Customize SVG Stamp Color Checkbox -->
                            <div class="stamp-control-custom-row" id="stamp-color-enable-row" style="display: none; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.82rem; font-weight: 600; color: #475569;">Customize SVG Color</span>
                                <label class="zrx-switch">
                                    <input type="checkbox" id="stamp-color-enable-chk" <?= $stampColorEnable === 'yes' ? 'checked' : '' ?>>
                                    <span class="zrx-slider"></span>
                                </label>
                            </div>
                            <!-- SVG Stamp Color Picker -->
                            <div class="stamp-control-custom-row" id="stamp-color-row" style="display: none; align-items: center; justify-content: space-between;">
                                <span style="font-size: 0.82rem; font-weight: 600; color: #475569;">Stamp Color</span>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="color" id="stamp-color-picker" value="<?= preview_escape($stampColor) ?>" style="border: none; width: 36px; height: 36px; padding: 0; background: none; cursor: pointer; border-radius: 6px; flex-shrink: 0;">
                                    <div class="hex-input-label" style="display: inline-flex; align-items: center; gap: 0.25rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 0.5rem; height: 34px;">
                                        <span style="color: #64748b; font-size: 0.85rem;">#</span>
                                        <input type="text" id="stamp-color-hex" value="<?= ltrim($stampColor, '#') ?>" style="width: 70px; border: none; outline: none; font-size: 0.85rem; text-transform: uppercase; color: #334155; font-family: monospace;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN: Live Mini Preview (scaled iframe) -->
            <div class="zrx-lower-right">
                <section class="header-editor-card zrx-mini-preview-card">
                    <div class="zrx-mini-preview-toolbar">
                        <h2>Live Preview</h2>
                        <p>Real-time visual representation of your layout.</p>
                    </div>
                    <div class="zrx-sheet-stage">
                        <div class="zrx-layout-page-scale">
                            <div class="zrx-paper-wrap" id="zrx-paper-wrap">
                                <iframe
                                    id="hf-preview-frame"
                                    class="zrx-hf-preview-frame"
                                    src="prescription_preview.php?embedded=1"
                                    title="Prescription preview"
                                ></iframe>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div><!-- end .zrx-lower-layout -->

    </form>

    <!-- ============================================================
         BACKGROUND IMAGE GALLERY POPUP
    ============================================================ -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-gallery-overlay">
        <div class="zrx-gallery-modal">
            <div class="zrx-gallery-header">
                <h3 id="zrx-gallery-title">Select Background Image</h3>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div id="bg-gallery-upload-container" style="display: none; align-items: center; gap: 0.5rem;">
                        <input type="file" id="bg-gallery-upload-input" accept=".svg,.png,.jpg,.jpeg" hidden>
                        <button type="button" class="btn btn-outline zrx-logo-gallery-upload-btn" id="bg-gallery-upload-btn" style="padding: 0.35rem 0.75rem; font-size: 0.82rem; height: 32px; display: flex; align-items: center; gap: 0.25rem;">&#8679; Upload New</button>
                    </div>
                    <button type="button" class="zrx-gallery-close" id="zrx-gallery-close">&#x2715;</button>
                </div>
            </div>
            <div class="zrx-gallery-toolbar">
                <input type="text" id="gallery-search" placeholder="Search images&hellip;" class="zrx-gallery-search">
                <select id="gallery-filter" class="zrx-gallery-filter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div id="bg-gallery-upload-status" class="zrx-logo-gallery-status" style="display: none; margin-bottom: 0.5rem;"></div>
            <div class="zrx-gallery-grid" id="zrx-gallery-grid">
                <div class="zrx-gallery-loading">Loading&hellip;</div>
            </div>
        </div>
    </div>
    <!-- ============================================================
         LOGO GALLERY POPUP
    ============================================================ -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-logo-gallery-overlay">
        <div class="zrx-gallery-modal">
            <div class="zrx-gallery-header">
                <h3>Select Logo</h3>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="file" id="logo-gallery-upload-input" accept="image/*" hidden>
                    <button type="button" class="btn btn-outline zrx-logo-gallery-upload-btn" id="logo-gallery-upload-btn" style="padding: 0.35rem 0.75rem; font-size: 0.82rem; height: 32px; display: flex; align-items: center; gap: 0.25rem;">&#8679; Upload New</button>
                    <button type="button" class="zrx-gallery-close" id="zrx-logo-gallery-close">&#x2715;</button>
                </div>
            </div>
            <div class="zrx-gallery-toolbar">
                <input type="text" id="logo-gallery-search" placeholder="Search logos&hellip;" class="zrx-gallery-search">
                <select id="logo-gallery-filter" class="zrx-gallery-filter">
                    <option value="">All</option>
                </select>
            </div>
            <div id="logo-gallery-upload-status" class="zrx-logo-gallery-status"></div>
            <div class="zrx-gallery-grid" id="zrx-logo-gallery-grid">
                <div class="zrx-gallery-loading">Loading&hellip;</div>
            </div>
        </div>
    </div>


    <!-- ============================================================
         CUSTOM CONFIRM MODAL
    ============================================================ -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-confirm-overlay" style="z-index: 100001;">
        <div class="zrx-confirm-modal">
            <div class="zrx-confirm-body" id="zrx-confirm-message">
                Are you sure you want to permanently delete this uploaded logo?
            </div>
            <div class="zrx-confirm-actions">
                <button type="button" class="btn btn-outline" id="zrx-confirm-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="zrx-confirm-ok" style="background: #ef4444; border-color: #ef4444;">Delete</button>
            </div>
        </div>
    </div>

    <!-- Toast Success/Error Dialog -->

    <div id="print-setup-toast" class="print-setup-toast" hidden>
        <div class="print-setup-toast-panel" role="dialog" aria-modal="true" aria-labelledby="print-setup-toast-message">
            <span class="print-setup-toast-icon" aria-hidden="true">&#10003;</span>
            <strong id="print-setup-toast-message">Saved successfully</strong>
            <button type="button" id="print-setup-toast-close" class="btn btn-primary">Okay</button>
        </div>
    </div>

    <!-- Onboarding Modal Backdrop -->
    <?php if ($hasOnboarded === 0): ?>
    <div class="zrx-onboard-backdrop" id="zrx-onboard-backdrop">
        <div class="zrx-onboard-modal">
            <div class="zrx-onboard-header">
                <h2>Doctor Profile Setup</h2>
                <p>Welcome! Enter your professional details below to auto-generate your prescription header. You can edit or format this anytime later.</p>
            </div>
            <form id="zrx-onboard-form" class="zrx-onboard-form" autocomplete="off">
                <div class="zrx-onboard-grid">
                    <!-- Bangla Side (Left Column) -->
                    <div class="zrx-onboard-column">
                        <h3>বাংলায় বিবরণ (Left Side)</h3>
                        <div class="zrx-onboard-field">
                            <label>ডাক্তারের নাম (বাংলা) *</label>
                            <input type="text" name="name_bn" placeholder="যেমন: ডা. শাফায়েত মাহমুদ" required>
                        </div>
                        <div class="zrx-onboard-field">
                            <label>শিক্ষাগত যোগ্যতা / ডিগ্রী</label>
                            <input type="text" name="qualifications_bn" placeholder="যেমন: এমবিবিএস, এমডি (কার্ডিওলজি), এফসিপিএস (মেডিসিন)">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>পদবি</label>
                            <input type="text" name="designation_bn" placeholder="যেমন: চিফ কনসালটেন্ট ও বিভাগীয় প্রধান (কার্ডিওলজি)">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>প্রতিষ্ঠান / কর্মস্থল</label>
                            <input type="text" name="institute_bn" placeholder="যেমন: এপেক্স কার্ডিয়াক ইনস্টিটিউট">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>বিশেষজ্ঞতা</label>
                            <input type="text" name="speciality_bn" placeholder="যেমন: হৃদরোগ, উচ্চ রক্তচাপ ও মেডিসিন বিশেষজ্ঞ">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>বিএমডিসি রেজি নম্বর</label>
                             <input type="text" name="bmdc_bn" placeholder="যেমন: বিএমডিসি রেজি নং: A-112233">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>মোবাইল / ফোন নম্বর</label>
                            <input type="text" name="phone_bn" placeholder="যেমন: মোবাইলঃ ০১৭১০-XXXXXX">
                        </div>
                    </div>

                    <!-- English Side (Right Column) -->
                    <div class="zrx-onboard-column">
                        <h3>English Details (Right Side)</h3>
                        <div class="zrx-onboard-field">
                            <label>Doctor Name (English) *</label>
                            <input type="text" name="name_en" placeholder="E.g. Dr. Shafayet Mahmud" required>
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Qualifications</label>
                            <input type="text" name="qualifications_en" placeholder="E.g. MBBS, MD (Cardiology), FCPS (Medicine) BCS (Health)">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Designation</label>
                            <input type="text" name="designation_en" placeholder="E.g. Chief Consultant & Head of the Department (Cardiology)">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Institute / Work Place</label>
                            <input type="text" name="institute_en" placeholder="E.g. Apex Cardiac Institute">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Speciality</label>
                            <input type="text" name="speciality_en" placeholder="E.g. Cardiology, Hypertension & Medicine Specialist">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>BMDC Reg. Number</label>
                            <input type="text" name="bmdc_en" placeholder="E.g. BMDC Reg. No: A-112233">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Mobile / Phone Number</label>
                            <input type="text" name="phone_en" placeholder="E.g. Mobile: 01710-XXXXXX">
                        </div>
                    </div>
                </div>
                
                <div class="zrx-onboard-actions">
                    <button type="button" class="btn btn-outline" id="zrx-onboard-skip-btn">Skip (Use Defaults)</button>
                    <button type="submit" class="btn btn-primary" id="zrx-onboard-submit-btn">Save &amp; Apply Header</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="vendor/nicedit/nicEdit-latest.js?v=<?= filemtime(__DIR__ . '/vendor/nicedit/nicEdit-latest.js') ?>"></script>
<script src="vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(__DIR__ . '/vendor/nicedit/nicEdit-zimrx-custom.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zimrx-header-form');
    
    // Onboarding form submission and skip button handling
    const onboardBackdrop = document.getElementById('zrx-onboard-backdrop');
    const onboardForm = document.getElementById('zrx-onboard-form');
    const onboardSkipBtn = document.getElementById('zrx-onboard-skip-btn');

    if (onboardForm) {
        onboardForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('zrx-onboard-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }

            const formData = new FormData(onboardForm);

            try {
                const response = await fetch('header_onboarding_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData).toString(),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                if ((await response.text()).trim() === '1') {
                    if (onboardBackdrop) onboardBackdrop.remove();
                    window.location.reload();
                } else {
                    alert('Failed to save profile. Please try again.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Save & Apply Header';
                    }
                }
            } catch (err) {
                alert('Connection error occurred.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save & Apply Header';
                }
            }
        });
    }

    if (onboardSkipBtn && onboardForm) {
        onboardSkipBtn.addEventListener('click', () => {
            // Populate Bangla defaults
            onboardForm.querySelector('[name="name_bn"]').value = 'ডা. শাফায়েত মাহমুদ';
            onboardForm.querySelector('[name="qualifications_bn"]').value = 'এমবিবিএস, এমডি (কার্ডিওলজি), এফসিপিএস (মেডিসিন), বিসিএস(স্বাস্থ্য)';
            onboardForm.querySelector('[name="designation_bn"]').value = 'চিফ কনসালটেন্ট ও বিভাগীয় প্রধান (কার্ডিওলজি)';
            onboardForm.querySelector('[name="institute_bn"]').value = 'এপেক্স কার্ডিয়াক ইনস্টিটিউট';
            onboardForm.querySelector('[name="speciality_bn"]').value = 'হৃদরোগ, উচ্চ রক্তচাপ ও মেডিসিন বিশেষজ্ঞ';
            onboardForm.querySelector('[name="bmdc_bn"]').value = 'বিএমডিসি রেজি নং: A-112233';
            onboardForm.querySelector('[name="phone_bn"]').value = 'মোবাইলঃ ০১৭১০-XXXXXX';

            // Populate English defaults
            onboardForm.querySelector('[name="name_en"]').value = 'Dr. Shafayet Mahmud';
            onboardForm.querySelector('[name="qualifications_en"]').value = 'MBBS, MD (Cardiology), FCPS (Medicine), BCS (Health)';
            onboardForm.querySelector('[name="designation_en"]').value = 'Chief Consultant & HOD (Cardiology)';
            onboardForm.querySelector('[name="institute_en"]').value = 'Apex Cardiac Institute';
            onboardForm.querySelector('[name="speciality_en"]').value = 'Cardiology, Hypertension & Medicine Specialist';
            onboardForm.querySelector('[name="bmdc_en"]').value = 'BMDC Reg. No: A-112233';
            onboardForm.querySelector('[name="phone_en"]').value = 'Mobile: 01710-XXXXXX';

            // Submit form
            const submitBtn = document.getElementById('zrx-onboard-submit-btn');
            if (submitBtn) submitBtn.click();
        });
    }

    const showToast = (message) => {
        const toast = document.getElementById('print-setup-toast');
        const toastMessage = document.getElementById('print-setup-toast-message');
        const toastClose = document.getElementById('print-setup-toast-close');
        if (toast && toastMessage) {
            toastMessage.textContent = message;
            toast.hidden = false;
            if (toastClose) {
                toastClose.focus();
                toastClose.onclick = () => { toast.hidden = true; };
            }
            toast.onclick = (e) => {
                if (e.target === toast) {
                    toast.hidden = true;
                }
            };
        } else {
            alert(message);
        }
    };

    const zrxConfirm = (message, onConfirm) => {
        const confirmOverlay = document.getElementById('zrx-confirm-overlay');
        const confirmMsg     = document.getElementById('zrx-confirm-message');
        const confirmOk      = document.getElementById('zrx-confirm-ok');
        const confirmCancel  = document.getElementById('zrx-confirm-cancel');

        if (!confirmOverlay || !confirmMsg || !confirmOk || !confirmCancel) {
            if (confirm(message)) onConfirm();
            return;
        }

        confirmMsg.textContent = message;
        confirmOverlay.classList.remove('is-hidden');

        const cleanUp = () => {
            confirmOverlay.classList.add('is-hidden');
            // Remove previous handlers to avoid multiple calls
            const newOk = confirmOk.cloneNode(true);
            const newCancel = confirmCancel.cloneNode(true);
            confirmOk.parentNode.replaceChild(newOk, confirmOk);
            confirmCancel.parentNode.replaceChild(newCancel, confirmCancel);
        };

        // Wire fresh listeners
        document.getElementById('zrx-confirm-ok').addEventListener('click', () => {
            cleanUp();
            onConfirm();
        });

        document.getElementById('zrx-confirm-cancel').addEventListener('click', () => {
            cleanUp();
        });

        confirmOverlay.onclick = (e) => {
            if (e.target === confirmOverlay) {
                cleanUp();
            }
        };
    };
    const headerDraft = document.getElementById('zrx-header-draft');

    const colorPicker = document.getElementById('header-color-picker');
    const colorValue = document.getElementById('header-color-value');
    const uploadTrigger = document.getElementById('upload-logo-trigger');
    const uploadInput = document.getElementById('header-logo-file');
    const uploadStatus = document.getElementById('logo-upload-status');
    const logoWrap = document.getElementById('header-logo-wrap');
    const logoPreview = document.getElementById('header-logo-preview');
    const logoPlaceholder = document.getElementById('header-logo-placeholder');
    const logoPathInput = document.getElementById('logo_path');
    const footerInput = document.getElementById('footer_html');

    const normalizeColor = value => String(value || '').replace(/[^a-fA-F0-9]/g, '').slice(0, 6).toUpperCase().padEnd(6, 'F');

    // ---- Iframe preview (Responsive Dynamic Scale) ----
    const hfFrame = document.getElementById('hf-preview-frame');
    const hfWrap  = document.getElementById('zrx-paper-wrap');
    const sheetStage = document.querySelector('.zrx-sheet-stage');
    const PAGE_W_CM = 21;
    const PAGE_H_CM = 29.7;

    const scaleIframe = () => {
        if (!hfFrame || !hfWrap) return;
        const availableWidth = sheetStage ? Math.max(200, sheetStage.clientWidth - 40) : 500;
        const fullWidthPx = PAGE_W_CM * (96 / 2.54); // ~793.7px
        
        let dynamicScale = availableWidth / fullWidthPx;
        if (dynamicScale > 0.95) dynamicScale = 0.95;
        if (dynamicScale < 0.35) dynamicScale = 0.35;

        hfFrame.style.width  = `${PAGE_W_CM}cm`;
        hfFrame.style.height = `${PAGE_H_CM}cm`;
        hfFrame.style.transform = `scale(${dynamicScale})`;
        hfWrap.style.width  = `calc(${PAGE_W_CM}cm * ${dynamicScale})`;
        hfWrap.style.height = `calc(${PAGE_H_CM}cm * ${dynamicScale})`;
    };

    window.addEventListener('resize', scaleIframe);
    if (typeof ResizeObserver !== 'undefined' && sheetStage) {
        new ResizeObserver(scaleIframe).observe(sheetStage);
    }
    scaleIframe();

    // ================================================================
    // REAL-TIME INSTANT LIVE PREVIEW SYNC
    // ================================================================
    const getPreviewDoc = () => {
        try {
            return hfFrame?.contentDocument || null;
        } catch (e) {
            return null;
        }
    };

    const updatePreviewHeaderColor = (hex) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const pageHeader = doc.querySelector('#pageHeader');
        if (pageHeader) pageHeader.style.backgroundColor = `#${hex}`;
    };

    const updatePreviewLeftHeader = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const leftBox = doc.querySelector('.zrx-header-left');
        if (leftBox) leftBox.innerHTML = html;
    };

    const updatePreviewRightHeader = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const rightBox = doc.querySelector('.zrx-header-right');
        if (rightBox) rightBox.innerHTML = html;
    };

    const updatePreviewFooter = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const footerBox = doc.querySelector('#preview-footer');
        if (footerBox) {
            footerBox.innerHTML = html;
            footerBox.style.display = 'block';
        }
    };

    const updatePreviewLogo = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const displayLogo = form.querySelector('input[name="display_logo"]:checked')?.value || 'yes';
        const logoPath = logoPathInput?.value || '';
        const layout = doc.querySelector('.zrx-header-layout');
        let logoDiv = doc.querySelector('.zrx-header-logo');

        if (displayLogo === 'yes' && logoPath) {
            if (layout) {
                layout.classList.remove('zrx-no-logo');
                layout.classList.add('zrx-has-logo');
            }
            if (!logoDiv && layout) {
                logoDiv = doc.createElement('div');
                logoDiv.className = 'zrx-header-logo';
                logoDiv.innerHTML = `<img src="${logoPath}" alt="Header logo">`;
                const rightBox = doc.querySelector('.zrx-header-right');
                if (rightBox) layout.insertBefore(logoDiv, rightBox);
                else layout.appendChild(logoDiv);
            } else if (logoDiv) {
                logoDiv.style.display = 'block';
                const img = logoDiv.querySelector('img');
                if (img) img.src = logoPath;
            }
        } else {
            if (layout) {
                layout.classList.remove('zrx-has-logo');
                layout.classList.add('zrx-no-logo');
            }
            if (logoDiv) {
                logoDiv.style.display = 'none';
            }
        }
    };

    const syncHeaderCustomizationPreview = () => {
        const doc = getPreviewDoc();
        if (!doc) return;

        const leftWidthInput = document.getElementById('header_left_width');
        const logoWidthInput = document.getElementById('header_logo_width');
        const rightWidthInput = document.getElementById('header_right_width');

        const leftWidth = leftWidthInput ? leftWidthInput.value : '40';
        const logoWidth = logoWidthInput ? logoWidthInput.value : '18';
        const rightWidth = rightWidthInput ? rightWidthInput.value : '40';

        const scale = document.getElementById('logo_scale').value;
        const rotation = document.getElementById('logo_rotation').value;
        const opacity = document.getElementById('logo_opacity').value;
        const offsetX = document.getElementById('logo_offset_x').value;
        const offsetY = document.getElementById('logo_offset_y').value;

        // Update strong label values
        document.getElementById('logo-scale-val').textContent = `${scale}%`;
        document.getElementById('logo-rotation-val').textContent = `${rotation}°`;
        document.getElementById('logo-opacity-val').textContent = `${opacity}%`;
        document.getElementById('logo-offset-x-val').textContent = `${offsetX}px`;
        document.getElementById('logo-offset-y-val').textContent = `${offsetY}px`;

        const leftCol = doc.querySelector('.zrx-header-left');
        const logoCol = doc.querySelector('.zrx-header-logo');
        const rightCol = doc.querySelector('.zrx-header-right');
        const logoImg = doc.querySelector('.zrx-header-logo img');

        if (leftCol) leftCol.style.width = `${leftWidth}%`;
        if (rightCol) rightCol.style.width = `${rightWidth}%`;
        if (logoCol) logoCol.style.width = `${logoWidth}%`;
        if (logoImg) {
            logoImg.style.transform = `translate(${offsetX}px, ${offsetY}px) rotate(${rotation}deg) scale(${scale / 100})`;
            logoImg.style.opacity = opacity / 100;
        }
    };

    const updatePreviewBackground = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const isImageHeader = form.querySelector('input[name="header_type"]:checked')?.value === 'image';
        const page = doc.querySelector('.zrx-print-page');
        const header = doc.querySelector('#pageHeader');
        const footer = doc.querySelector('#preview-footer');
        let watermark = doc.querySelector('#preview-watermark-layer');

        const headerLayout = doc.querySelector('.zrx-header-layout');
        const curFooterHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('footer_html'))
            ? nicEditors.findEditor('footer_html').getContent()
            : (footerInput?.value || '');

        if (footer) {
            footer.style.display = 'block';
        }

        if (isImageHeader) {
            const fullBodyPath = fullBodyHeaderPathInput?.value || '';
            if (header) {
                header.style.display = 'block';
                header.style.background = 'transparent';
            }
            if (headerLayout) {
                headerLayout.style.display = 'none';
            }
            if (watermark) watermark.style.display = 'none';
            if (page) {
                page.style.backgroundImage = fullBodyPath ? `url('${fullBodyPath}')` : 'none';
                page.style.backgroundSize = '100% 100%';
                page.style.backgroundRepeat = 'no-repeat';
                page.style.backgroundPosition = 'top center';
            }
        } else {
            const hex = normalizeColor(colorValue?.value || 'FFFFFF');
            if (header) {
                header.style.display = 'block';
                header.style.backgroundColor = `#${hex}`;
            }
            if (headerLayout) {
                headerLayout.style.display = 'flex';
            }
            if (page) page.style.backgroundImage = 'none';

            const bgPath = bgImgPathInput?.value || '';
            const opacity = parseFloat(bgImgOpacityIn?.value || '0.10');
            const scale = parseFloat(bgImgScaleIn?.value || '1.0');
            const angle = parseFloat(bgImgAngleIn?.value || '0.0');
            const offX = parseFloat(bgImgOffsetXIn?.value || '0.0');
            const offY = parseFloat(bgImgOffsetYIn?.value || '0.0');

            if (!watermark && page) {
                watermark = doc.createElement('div');
                watermark.id = 'preview-watermark-layer';
                watermark.className = 'zrx-watermark-layer';
                page.insertBefore(watermark, page.firstChild);
            }

            if (watermark) {
                if (bgPath) {
                    watermark.style.display = 'block';
                    watermark.style.backgroundImage = `url('${bgPath}')`;
                    watermark.style.opacity = String(opacity);
                    watermark.style.transform = `translate(${offX}px, ${offY}px) rotate(${angle}deg) scale(${scale})`;
                } else {
                    watermark.style.display = 'none';
                }
            }
        }
    };

    const syncAllToPreview = () => {
        const hex = normalizeColor(colorValue?.value || 'FFFFFF');
        updatePreviewHeaderColor(hex);
        updatePreviewLogo();
        updatePreviewBackground();
        updatePreviewStamp();
        const leftHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('left_block_html'))
            ? nicEditors.findEditor('left_block_html').getContent()
            : (document.getElementById('left_block_html')?.value || '');
        updatePreviewLeftHeader(leftHtml);

        const rightHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('right_block_html'))
            ? nicEditors.findEditor('right_block_html').getContent()
            : (document.getElementById('right_block_html')?.value || '');
        updatePreviewRightHeader(rightHtml);

        const footerHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('footer_html'))
            ? nicEditors.findEditor('footer_html').getContent()
            : (footerInput?.value || '');
        updatePreviewFooter(footerHtml);
    };

    if (hfFrame) {
        hfFrame.addEventListener('load', () => {
            scaleIframe();
            syncAllToPreview();
        });
    }

    const applyColor = value => {
        const hex = normalizeColor(value);
        colorValue.value = hex;
        colorPicker.value = `#${hex}`;
        const leftEdit = document.querySelector('.panel-left .panel-content');
        const rightEdit = document.querySelector('.panel-right .panel-content');
        if (leftEdit) leftEdit.style.background = `#${hex}`;
        if (rightEdit) rightEdit.style.background = `#${hex}`;
        updatePreviewHeaderColor(hex);
    };

    const updateLogoVisibility = () => {
        const selected = form.querySelector('input[name="display_logo"]:checked')?.value || 'yes';
        
        const logoCtrl = document.getElementById('logo-customization-controls');
        const logoWidthCtrl = document.querySelector('.width-ctrl-logo');
        if (logoCtrl) logoCtrl.classList.toggle('is-hidden', selected !== 'yes');
        if (logoWidthCtrl) logoWidthCtrl.classList.toggle('is-hidden', selected !== 'yes');

        const leftWidthInput = document.getElementById('header_left_width');
        const rightWidthInput = document.getElementById('header_right_width');
        if (leftWidthInput && rightWidthInput) {
            if (selected === 'yes') {
                if (leftWidthInput.value === '49') leftWidthInput.value = '40';
                if (rightWidthInput.value === '49') rightWidthInput.value = '40';
            } else {
                if (leftWidthInput.value === '40') leftWidthInput.value = '49';
                if (rightWidthInput.value === '40') rightWidthInput.value = '49';
            }
        }

        const previewBox = document.querySelector('.logo-preview-box');
        if (previewBox) {
            previewBox.classList.toggle('logo-hidden', selected !== 'yes');
        }
        headerDraft.classList.toggle('zrx-has-logo', selected === 'yes');
        headerDraft.classList.toggle('zrx-no-logo', selected !== 'yes');
        
        const titleRow = document.querySelector('.header-column-titles');
        if (titleRow) {
            titleRow.classList.toggle('zrx-has-logo', selected === 'yes');
            titleRow.classList.toggle('zrx-no-logo', selected !== 'yes');
        }
        updatePreviewLogo();
        syncHeaderCustomizationPreview();
    };

    // Toggle between Text Header (4 boxes) and Image Header (With Body)
    const textHeaderView = document.getElementById('zrx-header-draft');
    const imageHeaderView = document.getElementById('zrx-image-header-view');
    form.querySelectorAll('input[name="header_type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const isImage = form.querySelector('input[name="header_type"]:checked')?.value === 'image';
            if (textHeaderView) textHeaderView.classList.toggle('is-hidden', isImage);
            if (imageHeaderView) imageHeaderView.classList.toggle('is-hidden', !isImage);
            syncBgCardState();
            updatePreviewBackground();
        });
    });

    // Image Header (With Body) Upload Handler
    const imageBodyInput = document.getElementById('image-body-file');
    const imageBodySubmit = document.getElementById('image-body-submit');
    const imageBodyStatus = document.getElementById('image-body-upload-status');
    const imageBodyPreview = document.getElementById('image-body-preview');
    const imageBodyPreviewContainer = document.getElementById('image-body-preview-container');
    const fullBodyHeaderPathInput = document.getElementById('full_body_header_path');

    if (imageBodySubmit && imageBodyInput) {
        imageBodySubmit.addEventListener('click', async () => {
            if (!imageBodyInput.files.length) {
                imageBodyStatus.textContent = 'à¦ªà§à¦°à¦¥à¦®à§‡ à¦«à¦¾à¦‡à¦² à¦¸à¦¿à¦²à§‡à¦•à§à¦Ÿ à¦•à¦°à§à¦¨ à¦à¦°à¦ªà¦° à¦¸à¦¾à¦¬à¦®à¦¿à¦Ÿ à¦šà¦¾à¦ªà§à¦¨à¥¤';
                imageBodyStatus.style.color = '#ef4444';
                return;
            }
            imageBodyStatus.textContent = 'Uploading...';
            imageBodyStatus.style.color = 'var(--primary)';

            const fd = new FormData();
            fd.append('image_body', imageBodyInput.files[0]);

            try {
                const res = await fetch('api/upload_full_body_header.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok && data.full_body_header_path) {
                    fullBodyHeaderPathInput.value = data.full_body_header_path;
                    imageBodyPreview.src = data.full_body_header_path;
                    imageBodyPreviewContainer.classList.remove('is-hidden');
                    imageBodyStatus.textContent = data.message || 'à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦«à§à¦²à¦¬à¦¡à¦¿ à¦‡à¦®à§‡à¦œ à¦¹à§‡à¦¡à¦¾à¦° à¦†à¦ªà¦²à§‹à¦¡ à¦¸à¦®à§à¦ªà¦¨à§à¦¨ à¦¹à§Ÿà§‡à¦›à§‡à¥¤';
                    imageBodyStatus.style.color = '#16a34a';
                    updatePreviewBackground();
                } else {
                    imageBodyStatus.textContent = data.error ? `à¦¤à§à¦°à§à¦Ÿà¦¿: ${data.error}` : 'Upload failed';
                    imageBodyStatus.style.color = '#ef4444';
                }
            } catch (e) {
                imageBodyStatus.textContent = 'Network error during upload';
                imageBodyStatus.style.color = '#ef4444';
            }
        });
    }

    colorPicker.addEventListener('input', () => applyColor(colorPicker.value));
    colorValue.addEventListener('input', () => applyColor(colorValue.value));
    form.querySelectorAll('input[name="display_logo"]').forEach(input => input.addEventListener('change', updateLogoVisibility));

    const logoGalleryOpenBtn     = document.getElementById('logo-open-gallery');
    const logoGalleryOverlay     = document.getElementById('zrx-logo-gallery-overlay');
    const logoGalleryGrid        = document.getElementById('zrx-logo-gallery-grid');
    const logoGalleryClose       = document.getElementById('zrx-logo-gallery-close');
    const logoGallerySearch      = document.getElementById('logo-gallery-search');
    const logoGalleryFilter      = document.getElementById('logo-gallery-filter');
    const logoRemoveBtn          = document.getElementById('logo-remove-btn');

    // Inside-gallery upload elements
    const logoGalleryUploadBtn   = document.getElementById('logo-gallery-upload-btn');
    const logoGalleryUploadInput = document.getElementById('logo-gallery-upload-input');
    const logoGalleryUploadStatus= document.getElementById('logo-gallery-upload-status');

    let logoGalleryImages = null;

    const applyLogoSelection = (url) => {
        logoPathInput.value = url;
        logoPreview.src = url;
        logoPreview.classList.remove('is-hidden');
        logoPlaceholder.classList.add('is-hidden');
        if (logoRemoveBtn) logoRemoveBtn.classList.remove('is-hidden');
        uploadStatus.textContent = '';

        // Auto check Show radio button
        const showRadio = form.querySelector('input[name="display_logo"][value="yes"]');
        if (showRadio) showRadio.checked = true;

        updateLogoVisibility();
        updatePreviewLogo();
    };

    const renderLogoGallery = (images) => {
        const q   = (logoGallerySearch?.value || '').toLowerCase();
        const cat = (logoGalleryFilter?.value || '').toLowerCase();
        const filtered = images.filter(img =>
            (cat === '' || img.category.toLowerCase() === cat) &&
            (q   === '' || img.name.toLowerCase().includes(q) || img.category.toLowerCase().includes(q))
        );
        
        logoGalleryGrid.innerHTML = filtered.length === 0
            ? '<div class="zrx-gallery-empty">No logos found</div>'
            : filtered.map(img => {
                const isUploaded = img.category.toLowerCase() === 'uploaded';
                const filename = img.url.split('/').pop();
                const deleteBtnHtml = isUploaded 
                    ? `<button type="button" class="zrx-gallery-item-delete" data-filename="${filename}" title="Delete Logo">&#x2715;</button>` 
                    : '';
                return `
                <div class="zrx-gallery-item-wrapper">
                    <button type="button" class="zrx-gallery-item" data-url="${img.url}" title="${img.name} (${img.category})">
                        <img src="${img.url}" alt="${img.name}" loading="lazy">
                        <span class="zrx-gallery-item-name">${img.name}</span>
                        <span class="zrx-gallery-item-cat">${img.category}</span>
                    </button>
                    ${deleteBtnHtml}
                </div>`;
            }).join('');

        // Wire select events
        logoGalleryGrid.querySelectorAll('.zrx-gallery-item').forEach(btn => {
            btn.addEventListener('click', () => {
                applyLogoSelection(btn.dataset.url);
                logoGalleryOverlay.classList.add('is-hidden');
            });
        });

        // Wire delete events
        logoGalleryGrid.querySelectorAll('.zrx-gallery-item-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                
                zrxConfirm('Are you sure you want to permanently delete this uploaded logo?', async () => {
                    const filename = btn.dataset.filename;
                    const fd = new FormData();
                    fd.append('filename', filename);

                    try {
                        const res = await fetch('api/delete_header_logo.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.ok) {
                            // Remove from local cache list
                            logoGalleryImages = logoGalleryImages.filter(img => img.url.split('/').pop() !== filename);
                            renderLogoGallery(logoGalleryImages);

                            // Reset display if this logo is currently selected
                            const currentLogoUrl = logoPathInput.value;
                            if (currentLogoUrl && currentLogoUrl.split('/').pop() === filename) {
                                logoPathInput.value = '';
                                logoPreview.src = '';
                                logoPreview.classList.add('is-hidden');
                                logoPlaceholder.classList.remove('is-hidden');
                                if (logoRemoveBtn) logoRemoveBtn.classList.add('is-hidden');

                                // Auto check Hide radio button
                                const hideRadio = form.querySelector('input[name="display_logo"][value="no"]');
                                if (hideRadio) hideRadio.checked = true;

                                updateLogoVisibility();
                            }
                        } else {
                            alert(data.error || 'Failed to delete logo.');
                        }
                    } catch (err) {
                        alert('Network error while deleting logo.');
                    }
                });
            });
        });
    };

    const loadLogoGallery = async () => {
        if (logoGalleryImages) { renderLogoGallery(logoGalleryImages); return; }
        logoGalleryGrid.innerHTML = '<div class="zrx-gallery-loading">Loading&hellip;</div>';
        try {
            const res  = await fetch('api/list_header_logos.php');
            const data = await res.json();
            logoGalleryImages = data.images || [];
            const cats = data.categories || [];
            logoGalleryFilter.innerHTML = '<option value="">All</option>' +
                cats.map(c => `<option value="${c.toLowerCase()}">${c}</option>`).join('');
            renderLogoGallery(logoGalleryImages);
        } catch (e) {
            logoGalleryGrid.innerHTML = '<div class="zrx-gallery-empty">Failed to load logos</div>';
        }
    };

    if (logoGalleryOpenBtn) {
        logoGalleryOpenBtn.addEventListener('click', () => {
            logoGalleryOverlay.classList.remove('is-hidden');
            loadLogoGallery();
        });
    }
    if (logoGalleryClose) logoGalleryClose.addEventListener('click', () => logoGalleryOverlay.classList.add('is-hidden'));
    logoGalleryOverlay?.addEventListener('click', e => { if (e.target === logoGalleryOverlay) logoGalleryOverlay.classList.add('is-hidden'); });
    logoGallerySearch?.addEventListener('input', () => logoGalleryImages && renderLogoGallery(logoGalleryImages));
    logoGalleryFilter?.addEventListener('change', () => logoGalleryImages && renderLogoGallery(logoGalleryImages));

    // Inside gallery upload wiring
    if (logoGalleryUploadBtn && logoGalleryUploadInput) {
        logoGalleryUploadBtn.addEventListener('click', () => logoGalleryUploadInput.click());
        logoGalleryUploadInput.addEventListener('change', async () => {
            if (!logoGalleryUploadInput.files.length) return;
            logoGalleryUploadStatus.textContent = 'Uploading...';
            logoGalleryUploadStatus.style.color = 'var(--primary)';

            const fd = new FormData();
            fd.append('logo', logoGalleryUploadInput.files[0]);

            try {
                const res = await fetch('api/upload_header_logo.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.logo_path) {
                    logoGalleryUploadStatus.textContent = 'Uploaded successfully!';
                    logoGalleryUploadStatus.style.color = '#16a34a';
                    logoGalleryImages = null; // Invalidate cache
                    await loadLogoGallery(); // Reload and render
                    applyLogoSelection(data.logo_path); // Apply to preview
                    setTimeout(() => { logoGalleryUploadStatus.textContent = ''; }, 2000);
                } else {
                    logoGalleryUploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
                    logoGalleryUploadStatus.style.color = '#ef4444';
                }
            } catch (e) {
                logoGalleryUploadStatus.textContent = 'Network error';
                logoGalleryUploadStatus.style.color = '#ef4444';
            }
        });
    }

    // Remove logo
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', () => {
            logoPathInput.value = '';
            logoPreview.src = '';
            logoPreview.classList.add('is-hidden');
            logoPlaceholder.classList.remove('is-hidden');
            logoRemoveBtn.classList.add('is-hidden');
            uploadStatus.textContent = '';

            // Auto check Hide radio button
            const hideRadio = form.querySelector('input[name="display_logo"][value="no"]');
            if (hideRadio) hideRadio.checked = true;

            updateLogoVisibility();
        });
    }


    // Upload new logo via file input
    uploadTrigger.addEventListener('click', () => uploadInput.click());
    uploadInput.addEventListener('change', async () => {
        if (!uploadInput.files.length) return;
        uploadStatus.textContent = 'Uploading...';

        const fd = new FormData();
        fd.append('logo', uploadInput.files[0]);

        try {
            const res = await fetch('api/upload_header_logo.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.logo_path) {
                // Invalidate logo gallery cache so new upload appears next time
                logoGalleryImages = null;
                applyLogoSelection(data.logo_path);
                uploadStatus.textContent = 'Uploaded';
            } else {
                uploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
            }
        } catch (e) {
            uploadStatus.textContent = 'Network error';
        }
    });

    // ================================================================
    // BACKGROUND IMAGE GALLERY & CONTROLS
    // ================================================================
    const bgImgCard       = document.getElementById('bgimg-card');
    const bgImgBody       = bgImgCard?.querySelector('.zrx-bgimg-body');
    const bgImgPathInput  = document.getElementById('bg_image_path');
    const bgImgOpacityIn  = document.getElementById('bg_image_opacity');
    const bgImgScaleIn    = document.getElementById('bg_image_scale');
    const bgImgAngleIn    = document.getElementById('bg_image_angle');
    const bgImgOffsetXIn  = document.getElementById('bg_image_offset_x');
    const bgImgOffsetYIn  = document.getElementById('bg_image_offset_y');

    const bgImgThumb      = document.getElementById('bgimg-current-thumb');
    const bgImgThumbImg   = document.getElementById('bgimg-thumb-img');
    const bgImgControls   = document.getElementById('bgimg-controls');
    const bgImgRemoveBtn  = document.getElementById('bgimg-remove');
    const bgImgOpenBtn    = document.getElementById('bgimg-open-gallery');

    const opacityRange    = document.getElementById('bgimg-opacity-range');
    const opacityVal      = document.getElementById('bgimg-opacity-val');
    const scaleRange      = document.getElementById('bgimg-scale-range');
    const scaleVal        = document.getElementById('bgimg-scale-val');
    const angleRange      = document.getElementById('bgimg-angle-range');
    const angleVal        = document.getElementById('bgimg-angle-val');
    const offsetXRange    = document.getElementById('bgimg-offsetx-range');
    const offsetXVal      = document.getElementById('bgimg-offsetx-val');
    const offsetYRange    = document.getElementById('bgimg-offsety-range');
    const offsetYVal      = document.getElementById('bgimg-offsety-val');

    // ================================================================
    // SEAL & STAMP GALLERY & CONTROLS
    // ================================================================
    const stampCard       = document.getElementById('stamp-card');
    const stampPathInput  = document.getElementById('stamp_path');
    const stampOpacityIn  = document.getElementById('stamp_opacity');
    const stampScaleIn    = document.getElementById('stamp_scale');
    const stampAngleIn    = document.getElementById('stamp_angle');
    const stampOffsetXIn  = document.getElementById('stamp_offset_x');
    const stampOffsetYIn  = document.getElementById('stamp_offset_y');

    const stampThumb      = document.getElementById('stamp-current-thumb');
    const stampThumbImg   = document.getElementById('stamp-thumb-img');
    const stampControls   = document.getElementById('stamp-controls');
    const stampRemoveBtn  = document.getElementById('stamp-remove');
    const stampOpenBtn    = document.getElementById('stamp-open-gallery');

    const stampOpacityRange = document.getElementById('stamp-opacity-range');
    const stampOpacityVal   = document.getElementById('stamp-opacity-val');
    const stampScaleRange   = document.getElementById('stamp-scale-range');
    const stampScaleVal     = document.getElementById('stamp-scale-val');
    const stampAngleRange   = document.getElementById('stamp-angle-range');
    const stampAngleVal     = document.getElementById('stamp-angle-val');
    const stampOffsetXRange = document.getElementById('stamp-offsetx-range');
    const stampOffsetXVal   = document.getElementById('stamp-offsetx-val');
    const stampOffsetYRange = document.getElementById('stamp-offsety-range');
    const stampOffsetYVal   = document.getElementById('stamp-offsety-val');

    const stampColorInput = document.getElementById('stamp_color');
    const stampColorPicker = document.getElementById('stamp-color-picker');
    const stampColorHex = document.getElementById('stamp-color-hex');
    const stampColorEnableInput = document.getElementById('stamp_color_enable');
    const stampColorEnableChk = document.getElementById('stamp-color-enable-chk');

    // Disable bg card when image header is chosen
    const syncBgCardState = () => {
        const isText = form.querySelector('input[name="header_type"]:checked')?.value !== 'image';
        if (bgImgCard) bgImgCard.classList.toggle('is-disabled', !isText);
        if (bgImgBody) bgImgBody.classList.toggle('is-locked', !isText);
    };
    syncBgCardState();

    // Slider events
    const bindSlider = (range, valEl, hiddenIn, suffix, decimals, divisor, callback) => {
        if (!range) return;
        range.addEventListener('input', () => {
            const raw = parseFloat(range.value);
            const stored = raw / divisor;
            if (hiddenIn) hiddenIn.value = stored.toFixed(decimals);
            if (valEl) valEl.textContent = raw + suffix;
            callback?.();
        });
    };

    const toggleStampColorRow = () => {
        const path = stampPathInput?.value || '';
        const isSvg = path.toLowerCase().endsWith('.svg');
        const enableRow = document.getElementById('stamp-color-enable-row');
        const colorRow = document.getElementById('stamp-color-row');
        
        if (enableRow) {
            enableRow.style.display = isSvg ? 'flex' : 'none';
        }
        if (colorRow) {
            const isEnabled = stampColorEnableChk?.checked;
            colorRow.style.display = (isSvg && isEnabled) ? 'flex' : 'none';
        }
    };

    const updatePreviewStamp = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        let stamp = doc.querySelector('#preview-stamp-layer');
        if (!stamp) {
            const page = doc.querySelector('.zrx-print-page');
            if (page) {
                stamp = doc.createElement('div');
                stamp.id = 'preview-stamp-layer';
                stamp.className = 'zrx-stamp-layer';
                page.appendChild(stamp);
            }
        }

        const path = stampPathInput?.value || '';
        const opacity = parseFloat(stampOpacityIn?.value || '1.0');
        const scale = parseFloat(stampScaleIn?.value || '1.0');
        const angle = parseFloat(stampAngleIn?.value || '0.0');
        const offX = parseFloat(stampOffsetXIn?.value || '0.0');
        const offY = parseFloat(stampOffsetYIn?.value || '0.0');
        const stampColor = stampColorInput?.value || '#000000';
        const isColorEnabled = stampColorEnableChk?.checked;

        // Toggle visibility of the SVG color picker rows
        toggleStampColorRow();

        if (stamp) {
            if (path) {
                stamp.style.display = 'block';
                stamp.style.transform = `translate(${offX}px, ${offY}px) rotate(${angle}deg) scale(${scale})`;

                let inner = stamp.querySelector('#preview-stamp-inner');
                if (!inner) {
                    inner = doc.createElement('div');
                    inner.id = 'preview-stamp-inner';
                    inner.style.width = '100%';
                    inner.style.height = '100%';
                    inner.style.backgroundRepeat = 'no-repeat';
                    inner.style.backgroundPosition = 'center center';
                    inner.style.backgroundSize = 'contain';
                    inner.style.position = 'absolute';
                    inner.style.top = '0';
                    inner.style.left = '0';
                    inner.style.zIndex = '1';
                    stamp.appendChild(inner);
                }

                inner.style.opacity = String(opacity);

                const isSvg = path.toLowerCase().endsWith('.svg');
                if (isSvg && isColorEnabled) {
                    inner.style.backgroundImage = 'none';
                    inner.style.maskImage = `url('${path}')`;
                    inner.style.webkitMaskImage = `url('${path}')`;
                    inner.style.maskSize = 'contain';
                    inner.style.webkitMaskSize = 'contain';
                    inner.style.maskRepeat = 'no-repeat';
                    inner.style.webkitMaskRepeat = 'no-repeat';
                    inner.style.maskPosition = 'center center';
                    inner.style.webkitMaskPosition = 'center center';
                    inner.style.backgroundColor = stampColor;
                } else {
                    inner.style.backgroundImage = `url('${path}')`;
                    inner.style.maskImage = 'none';
                    inner.style.webkitMaskImage = 'none';
                    inner.style.backgroundColor = 'transparent';
                }

                // Bind mouse drag listeners for interactive stamp moving
                if (!stamp._dragBound) {
                    stamp._dragBound = true;
                    stamp.style.cursor = 'move';
                    stamp.style.pointerEvents = 'auto';

                    let isDragging = false;
                    let startX = 0;
                    let startY = 0;
                    let startOffsetX = 0;
                    let startOffsetY = 0;

                    stamp.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        isDragging = true;
                        startX = e.clientX;
                        startY = e.clientY;
                        startOffsetX = parseFloat(stampOffsetXIn?.value || '0.0');
                        startOffsetY = parseFloat(stampOffsetYIn?.value || '0.0');

                        const onMouseMove = (moveEvent) => {
                            if (!isDragging) return;
                            const dx = moveEvent.clientX - startX;
                            const dy = moveEvent.clientY - startY;

                            const newX = Math.round(startOffsetX + dx);
                            const newY = Math.round(startOffsetY + dy);

                            const clampedX = Math.max(-1200, Math.min(1200, newX));
                            const clampedY = Math.max(-1200, Math.min(1200, newY));

                            if (stampOffsetXIn) stampOffsetXIn.value = clampedX;
                            if (stampOffsetYIn) stampOffsetYIn.value = clampedY;

                            if (stampOffsetXRange) stampOffsetXRange.value = clampedX;
                            if (stampOffsetYRange) stampOffsetYRange.value = clampedY;

                            if (stampOffsetXVal) stampOffsetXVal.textContent = clampedX + 'px';
                            if (stampOffsetYVal) stampOffsetYVal.textContent = clampedY + 'px';

                            // Dynamically update element style for smooth drag feel
                            const currentOpacity = parseFloat(stampOpacityIn?.value || '1.0');
                            const currentScale = parseFloat(stampScaleIn?.value || '1.0');
                            const currentAngle = parseFloat(stampAngleIn?.value || '0.0');
                            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${currentAngle}deg) scale(${currentScale})`;
                        };

                        const onMouseUp = () => {
                            isDragging = false;
                            doc.removeEventListener('mousemove', onMouseMove);
                            doc.removeEventListener('mouseup', onMouseUp);
                            window.removeEventListener('mouseup', onMouseUp);
                        };

                        doc.addEventListener('mousemove', onMouseMove);
                        doc.addEventListener('mouseup', onMouseUp);
                        window.addEventListener('mouseup', onMouseUp);
                    });
                }
            } else {
                stamp.style.display = 'none';
            }
        }
    };

    bindSlider(opacityRange, opacityVal, bgImgOpacityIn, '%', 2, 100, updatePreviewBackground);
    bindSlider(scaleRange,   scaleVal,   bgImgScaleIn,   '%', 2, 100, updatePreviewBackground);
    bindSlider(angleRange,   angleVal,   bgImgAngleIn,   '°', 1, 1,   updatePreviewBackground);
    bindSlider(offsetXRange, offsetXVal, bgImgOffsetXIn, 'px', 1, 1,  updatePreviewBackground);
    bindSlider(offsetYRange, offsetYVal, bgImgOffsetYIn, 'px', 1, 1,  updatePreviewBackground);

    bindSlider(stampOpacityRange, stampOpacityVal, stampOpacityIn, '%', 2, 100, updatePreviewStamp);
    bindSlider(stampScaleRange,   stampScaleVal,   stampScaleIn,   '%', 2, 100, updatePreviewStamp);
    bindSlider(stampAngleRange,   stampAngleVal,   stampAngleIn,   '°', 1, 1,   updatePreviewStamp);
    bindSlider(stampOffsetXRange, stampOffsetXVal, stampOffsetXIn, 'px', 1, 1,  updatePreviewStamp);
    bindSlider(stampOffsetYRange, stampOffsetYVal, stampOffsetYIn, 'px', 1, 1,  updatePreviewStamp);

    // Remove bg image
    if (bgImgRemoveBtn) {
        bgImgRemoveBtn.addEventListener('click', () => {
            if (bgImgPathInput)  bgImgPathInput.value = '';
            if (bgImgThumb)      bgImgThumb.classList.add('is-hidden');
            if (bgImgControls)   bgImgControls.classList.add('is-hidden');
            updatePreviewBackground();
        });
    }

    // Remove stamp
    if (stampRemoveBtn) {
        stampRemoveBtn.addEventListener('click', () => {
            if (stampPathInput)  stampPathInput.value = '';
            if (stampThumb)      stampThumb.classList.add('is-hidden');
            if (stampControls)   stampControls.classList.add('is-hidden');
            updatePreviewStamp();
        });
    }

    // Select background: set image after gallery pick
    const setSelectedBgImage = (url) => {
        if (bgImgPathInput && !bgImgPathInput.value) {
            if (bgImgOpacityIn) bgImgOpacityIn.value = '0.1';
            const opacityRange = document.getElementById('bgimg-opacity-range');
            const opacityVal = document.getElementById('bgimg-opacity-val');
            if (opacityRange) opacityRange.value = '10';
            if (opacityVal) opacityVal.textContent = '10%';
        }
        if (bgImgPathInput)  bgImgPathInput.value = url;
        if (bgImgThumbImg)   bgImgThumbImg.src = url;
        if (bgImgThumb)      bgImgThumb.classList.remove('is-hidden');
        if (bgImgControls)   bgImgControls.classList.remove('is-hidden');
        updatePreviewBackground();
    };

    // Select stamp: set image after gallery pick
    const setSelectedStampImage = (url) => {
        if (stampPathInput)  stampPathInput.value = url;
        if (stampThumbImg)   stampThumbImg.src = url;
        if (stampThumb)      stampThumb.classList.remove('is-hidden');
        if (stampControls)   stampControls.classList.remove('is-hidden');
        updatePreviewStamp();
    };

    // ================================================================
    // GALLERY MODAL
    // ================================================================
    const overlay      = document.getElementById('zrx-gallery-overlay');
    const galleryGrid  = document.getElementById('zrx-gallery-grid');
    const galleryClose = document.getElementById('zrx-gallery-close');
    const gallerySearch = document.getElementById('gallery-search');
    const galleryFilter = document.getElementById('gallery-filter');

    let gallerySelectionCallback = null;
    let galleryCache = {};
    let currentIsBgGallery = false;
    let activeEndpointUrl = '';

    const renderGallery = (images, isBgGallery = false) => {
        const q   = (gallerySearch?.value || '').toLowerCase();
        const cat = (galleryFilter?.value || '').toLowerCase();
        const filtered = images.filter(img =>
            (cat === '' || img.category.toLowerCase() === cat) &&
            (q   === '' || img.name.toLowerCase().includes(q) || img.category.toLowerCase().includes(q))
        );
        galleryGrid.innerHTML = filtered.length === 0
            ? '<div class="zrx-gallery-empty">No images found</div>'
            : filtered.map(img => {
                const isUploaded = img.category.toLowerCase() === 'uploaded';
                const filename = img.url.split('/').pop();
                const deleteBtnHtml = isUploaded
                    ? `<button type="button" class="zrx-gallery-item-delete" data-filename="${filename}" title="Delete Uploaded Image">&#x2715;</button>`
                    : '';
                return `
                <div class="zrx-gallery-item-wrapper">
                    <button type="button" class="zrx-gallery-item" data-url="${img.url}" title="${img.name} (${img.category})">
                        <img src="${img.url}" alt="${img.name}" loading="lazy">
                        <span class="zrx-gallery-item-name">${img.name}</span>
                        <span class="zrx-gallery-item-cat">${img.category}</span>
                    </button>
                    ${deleteBtnHtml}
                </div>`;
            }).join('');

        // Wire select events
        galleryGrid.querySelectorAll('.zrx-gallery-item').forEach(btn => {
            btn.addEventListener('click', () => {
                if (gallerySelectionCallback) {
                    gallerySelectionCallback(btn.dataset.url);
                }
                overlay.classList.add('is-hidden');
            });
        });

        // Wire delete events
        galleryGrid.querySelectorAll('.zrx-gallery-item-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const filename = btn.dataset.filename;
                const confirmMsg = isBgGallery
                    ? 'Are you sure you want to permanently delete this uploaded background image?'
                    : 'Are you sure you want to permanently delete this uploaded seal/stamp image?';
                const deleteApi = isBgGallery ? 'api/delete_background_image.php' : 'api/delete_seal_and_stamp.php';
                const reloadApi = isBgGallery ? 'api/list_background_images.php' : 'api/list_seal_and_stamps.php';

                zrxConfirm(confirmMsg, async () => {
                    const fd = new FormData();
                    fd.append('filename', filename);
                    try {
                        const res = await fetch(deleteApi, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.ok) {
                            galleryCache = {};
                            await loadGallery(reloadApi, isBgGallery);
                        } else {
                            alert(data.error || 'Failed to delete file');
                        }
                    } catch (err) {
                        alert('Network error during delete');
                    }
                });
            });
        });
    };

    const loadGallery = async (endpointUrl, isBgGallery = false) => {
        activeEndpointUrl = endpointUrl;
        currentIsBgGallery = isBgGallery;
        if (galleryCache[endpointUrl]) {
            renderGallery(galleryCache[endpointUrl], isBgGallery);
            return;
        }
        galleryGrid.innerHTML = '<div class="zrx-gallery-loading">Loading…</div>';
        try {
            const res  = await fetch(endpointUrl);
            const data = await res.json();
            const images = data.images || [];
            galleryCache[endpointUrl] = images;

            const cats = data.categories || [];
            galleryFilter.innerHTML = '<option value="">All Categories</option>' +
                cats.map(c => `<option value="${c.toLowerCase()}">${c}</option>`).join('');

            renderGallery(images, isBgGallery);
        } catch (e) {
            galleryGrid.innerHTML = '<div class="zrx-gallery-empty">Failed to load images</div>';
        }
    };

    if (bgImgOpenBtn) {
        bgImgOpenBtn.addEventListener('click', () => {
            if (bgImgCard?.classList.contains('is-disabled')) return;
            
            // Set dynamic title
            const titleEl = document.getElementById('zrx-gallery-title');
            if (titleEl) titleEl.textContent = 'Select Background Image';
            
            // Show upload container
            const uploadCont = document.getElementById('bg-gallery-upload-container');
            if (uploadCont) uploadCont.style.display = 'flex';
            
            overlay.classList.remove('is-hidden');
            gallerySelectionCallback = setSelectedBgImage;
            loadGallery('api/list_background_images.php', true);
        });
    }

    if (stampOpenBtn) {
        stampOpenBtn.addEventListener('click', () => {
            // Set dynamic title
            const titleEl = document.getElementById('zrx-gallery-title');
            if (titleEl) titleEl.textContent = 'Select Seal & Stamp';
            
            // Show upload container
            const uploadCont = document.getElementById('bg-gallery-upload-container');
            if (uploadCont) uploadCont.style.display = 'flex';
            
            overlay.classList.remove('is-hidden');
            gallerySelectionCallback = setSelectedStampImage;
            loadGallery('api/list_seal_and_stamps.php', false);
        });
    }

    // Background & Stamp gallery upload wiring
    const bgGalleryUploadBtn = document.getElementById('bg-gallery-upload-btn');
    const bgGalleryUploadInput = document.getElementById('bg-gallery-upload-input');
    const bgGalleryUploadStatus = document.getElementById('bg-gallery-upload-status');

    if (bgGalleryUploadBtn && bgGalleryUploadInput) {
        bgGalleryUploadBtn.addEventListener('click', () => bgGalleryUploadInput.click());
        bgGalleryUploadInput.addEventListener('change', async () => {
            if (!bgGalleryUploadInput.files.length) return;
            bgGalleryUploadStatus.style.display = 'block';
            bgGalleryUploadStatus.textContent = 'Uploading...';
            bgGalleryUploadStatus.style.color = 'var(--primary)';

            const uploadApi = currentIsBgGallery ? 'api/upload_background_image.php' : 'api/upload_seal_and_stamp.php';
            const fieldName = currentIsBgGallery ? 'bg_image' : 'stamp_image';
            const reloadApi = currentIsBgGallery ? 'api/list_background_images.php' : 'api/list_seal_and_stamps.php';

            const fd = new FormData();
            fd.append(fieldName, bgGalleryUploadInput.files[0]);

            try {
                const res = await fetch(uploadApi, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    bgGalleryUploadStatus.textContent = 'Uploaded successfully!';
                    bgGalleryUploadStatus.style.color = '#16a34a';
                    galleryCache = {};
                    await loadGallery(reloadApi, currentIsBgGallery);
                    setTimeout(() => {
                        bgGalleryUploadStatus.textContent = '';
                        bgGalleryUploadStatus.style.display = 'none';
                    }, 2000);
                } else {
                    bgGalleryUploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
                    bgGalleryUploadStatus.style.color = '#ef4444';
                }
            } catch (err) {
                bgGalleryUploadStatus.textContent = 'Network error';
                bgGalleryUploadStatus.style.color = '#ef4444';
            }
            bgGalleryUploadInput.value = '';
        });
    }

    if (galleryClose) galleryClose.addEventListener('click', () => overlay.classList.add('is-hidden'));
    overlay?.addEventListener('click', e => { if (e.target === overlay) overlay.classList.add('is-hidden'); });
    gallerySearch?.addEventListener('input', () => {
        if (activeEndpointUrl) {
            renderGallery(galleryCache[activeEndpointUrl] || [], currentIsBgGallery);
        }
    });
    galleryFilter?.addEventListener('change', () => {
        if (activeEndpointUrl) {
            renderGallery(galleryCache[activeEndpointUrl] || [], currentIsBgGallery);
        }
    });

    // Initialize NicEditors
    if (typeof bkLib !== 'undefined') {
        const baseConfig = {
            fullPanel: true,
            iconsPath: 'vendor/nicedit/images/nicEditIcons-latest.gif'
        };

        const leftPanel  = document.querySelector('.panel-left  .panel-content');
        const rightPanel = document.querySelector('.panel-right .panel-content');
        const footerWrap = document.querySelector('.footer-editor-wrap');

        const leftWidth  = leftPanel  ? leftPanel.clientWidth  : 400;
        const rightWidth = rightPanel ? rightPanel.clientWidth  : 400;
        const footerWidth = footerWrap ? footerWrap.clientWidth : 715;

        const myFooterEditor = new nicEditor({ ...baseConfig, width: footerWidth }).panelInstance('footer_html');
        const myLeftEditor   = new nicEditor({ ...baseConfig, width: leftWidth  }).panelInstance('left_block_html');
        const myRightEditor  = new nicEditor({ ...baseConfig, width: rightWidth }).panelInstance('right_block_html');

        const attachLiveSync = (editorInstance, updateFn) => {
            if (!editorInstance) return;
            const sync = () => {
                editorInstance.saveContent();
                updateFn(editorInstance.getContent());
            };
            const el = editorInstance.getElm ? editorInstance.getElm() : editorInstance.elm;
            if (el) {
                el.addEventListener('input', sync);
                el.addEventListener('keyup', sync);
                el.addEventListener('blur', sync);
                el.addEventListener('paste', sync);
                el.addEventListener('cut', sync);
            }
        };

        setTimeout(() => {
            const edLeft = nicEditors.findEditor('left_block_html');
            const edRight = nicEditors.findEditor('right_block_html');
            const edFooter = nicEditors.findEditor('footer_html');

            attachLiveSync(edLeft, updatePreviewLeftHeader);
            attachLiveSync(edRight, updatePreviewRightHeader);
            attachLiveSync(edFooter, updatePreviewFooter);
        }, 300);
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Save Settings';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        if (typeof nicEditors !== 'undefined') {
            ['footer_html', 'left_block_html', 'right_block_html'].forEach(id => {
                const editorInstance = nicEditors.findEditor(id);
                if (editorInstance) {
                    editorInstance.saveContent();
                }
            });
        }

        try {
            const response = await fetch('header_edit_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form)).toString()
            });

            const ok = (await response.text()).trim() === '1';
            if (ok) {
                showToast('Saved successfully');
                // Refresh preview iframe
                const frame = document.getElementById('hf-preview-frame');
                if (frame) frame.contentWindow.location.reload();
            } else {
                showToast('Save error');
            }
        } catch (e) {
            showToast('Network error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });

    // Bind Header Customization inputs
    ['header_left_width', 'header_logo_width', 'header_right_width', 'logo_scale', 'logo_rotation', 'logo_opacity', 'logo_offset_x', 'logo_offset_y'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', syncHeaderCustomizationPreview);
            input.addEventListener('change', syncHeaderCustomizationPreview);
        }
    });

    const resetHeaderBtn = document.getElementById('btn-reset-header-customization');
    if (resetHeaderBtn) {
        resetHeaderBtn.addEventListener('click', () => {
            const hasLogo = form.querySelector('input[name="display_logo"]:checked')?.value === 'yes';

            const leftWidthInput = document.getElementById('header_left_width');
            const logoWidthInput = document.getElementById('header_logo_width');
            const rightWidthInput = document.getElementById('header_right_width');

            if (leftWidthInput) leftWidthInput.value = hasLogo ? '40' : '49';
            if (logoWidthInput) logoWidthInput.value = '18';
            if (rightWidthInput) rightWidthInput.value = hasLogo ? '40' : '49';

            const scaleInput = document.getElementById('logo_scale');
            const rotationInput = document.getElementById('logo_rotation');
            const opacityInput = document.getElementById('logo_opacity');
            const offsetXInput = document.getElementById('logo_offset_x');
            const offsetYInput = document.getElementById('logo_offset_y');

            if (scaleInput) scaleInput.value = '100';
            if (rotationInput) rotationInput.value = '0';
            if (opacityInput) opacityInput.value = '100';
            if (offsetXInput) offsetXInput.value = '0';
            if (offsetYInput) offsetYInput.value = '0';

            syncHeaderCustomizationPreview();
            showToast('Default settings loaded. Save to apply.');
        });
    }

    const applyStampColor = (color) => {
        const hex = normalizeColor(color);
        if (stampColorInput) stampColorInput.value = `#${hex}`;
        if (stampColorHex) stampColorHex.value = hex;
        if (stampColorPicker) stampColorPicker.value = `#${hex}`;
        updatePreviewStamp();
    };

    if (stampColorPicker && stampColorHex) {
        stampColorPicker.addEventListener('input', () => applyStampColor(stampColorPicker.value));
        stampColorHex.addEventListener('input', () => applyStampColor(stampColorHex.value));
    }

    if (stampColorEnableChk) {
        stampColorEnableChk.addEventListener('change', () => {
            if (stampColorEnableInput) {
                stampColorEnableInput.value = stampColorEnableChk.checked ? 'yes' : 'no';
            }
            updatePreviewStamp();
        });
    }

    applyColor(colorValue.value);
    applyStampColor(stampColorInput?.value || '#000000');
    updateLogoVisibility();
    syncHeaderCustomizationPreview();
    updatePreviewStamp();
});
</script>
<?php include 'footer.php'; ?>
