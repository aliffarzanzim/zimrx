<?php
declare(strict_types=1);

// Header, footer, watermark, and seal/stamp customizer: WYSIWYG text, logo transforms, full-bleed images, and live iframe preview.

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/print_setup_lib.php';

function checked_attr(string $current, string $target): string {
    return $current === $target ? 'checked' : '';
}

$page_title = 'ZimRx - Header, Footer & Background Setup';
$extra_css = ['assets/css/pages/header_footer_background_setup.css'];
$doctorId = current_user_doctor_id();

$header = zimrx_print_load_header_settings($pdo, $doctorId);
$options = zimrx_print_load_options($pdo, $doctorId);
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
    } catch (Throwable $e) {
        // Ignore
    }
}

$pageWidth = isset($options['page_width']) && is_numeric($options['page_width']) ? (float)$options['page_width'] : 21.0;
$leftLines = zimrx_print_header_lines($header, 'left');
$rightLines = zimrx_print_header_lines($header, 'right');
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

$leftBlockHtml = zimrx_print_visual_block_html($header, 'left', $leftLines);
$rightBlockHtml = zimrx_print_visual_block_html($header, 'right', $rightLines);


include 'header.php';
?>

<main class="header-editor-page zrx-page-container">
    <div class="zps-heading-card">
        <div class="zps-heading">
            <div>
                <h1>Header, Footer &amp; Background Setup</h1>
                <p>Customize your prescription header text/image, footer, background watermark, and stamp.</p>
            </div>
            <div class="header-editor-heading-actions hfb-flex-gap8">
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
                            <textarea name="left_block_html" id="left_block_html" class="zrx-w100"><?= preview_escape($leftBlockHtml) ?></textarea>
                        </div>
                    </div>

                    <!-- Middle/Logo Column Panel -->
                    <div class="header-editor-panel panel-middle" id="header-logo-wrap">
                        <h2>Logo</h2>
                        <div class="panel-content logo-settings-panel">
                            <div class="logo-preview-box <?= $displayLogo === 'yes' ? '' : 'logo-hidden' ?> hfb-pos-rel">
                                <img id="header-logo-preview" src="<?= preview_escape($logoPath) ?>" alt="Logo" class="<?= $logoPath ? '' : 'is-hidden' ?>">
                                <span id="header-logo-placeholder" class="zrx-logo-placeholder <?= $logoPath ? 'is-hidden' : '' ?>">Logo</span>
                                <button type="button" id="logo-remove-btn" class="zrx-bgimg-remove <?= $logoPath ? '' : 'is-hidden' ?>" title="Remove logo">&#x2715;</button>
                            </div>

                            <div class="logo-controls-box">
                                <input type="hidden" name="logo_path" id="logo_path" value="<?= preview_escape($logoPath) ?>">
                                <input type="file" id="header-logo-file" accept="image/*" hidden>
                                <div class="zrx-logo-select-row">
                                    <button type="button" id="logo-open-gallery" class="btn btn-outline zrx-w100">Select Logo</button>
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
                            <textarea name="right_block_html" id="right_block_html" class="zrx-w100"><?= preview_escape($rightBlockHtml) ?></textarea>
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

        <!-- Lower Two-Column Layout -->
        <?php
        $footerWidth = (float)($options['footer_width'] ?? ($options['page_width'] ?? 21));
        if ($footerWidth <= 0) $footerWidth = 21;
        ?>
        <div class="zrx-lower-layout">

            <!-- LEFT COLUMN -->
            <div class="zrx-lower-left">

                <!-- Header Customization Card -->
                <section class="header-editor-card zrx-bgimg-card <?= $headerType === 'text' ? '' : 'is-disabled' ?>" id="header-customization-card" style="width:<?= $footerWidth ?>cm; margin-bottom: 1.5rem;">
                    <div class="zrx-bgimg-card-topbar hfb-footer-header-row">
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

                    <div class="control-row hfb-mb-20">
                        <div class="hfb-flex-1">
                            <label class="form-label hfb-label-bold">Column Widths (%)</label>
                            <span class="hfb-label-muted-desc">Note: Column widths must combine to exactly 100% if you edit them.</span>
                            <div class="hfb-field-group-row" id="header-widths-container">
                                <div class="hfb-flex-1 width-ctrl-left">
                                    <span class="hfb-field-label">Left Column Width:</span>
                                    <input type="number" name="header_left_width" id="header_left_width" min="10" max="80" step="1" class="zps-size-input hfb-full-input" value="<?= preview_escape($options['header_left_width'] ?? ($displayLogo === 'yes' ? '40' : '49')) ?>">
                                </div>
                                <div class="hfb-flex-1 width-ctrl-logo">
                                    <span class="hfb-field-label">Logo Column Width:</span>
                                    <input type="number" name="header_logo_width" id="header_logo_width" min="5" max="50" step="1" class="zps-size-input hfb-full-input" value="<?= preview_escape($options['header_logo_width'] ?? '18') ?>">
                                </div>
                                <div class="hfb-flex-1 width-ctrl-right">
                                    <span class="hfb-field-label">Right Column Width:</span>
                                    <input type="number" name="header_right_width" id="header_right_width" min="10" max="80" step="1" class="zps-size-input hfb-full-input" value="<?= preview_escape($options['header_right_width'] ?? ($displayLogo === 'yes' ? '40' : '49')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logo Transformation Panel -->
                    <div id="logo-customization-controls" class="<?= $displayLogo === 'yes' ? '' : 'is-hidden' ?>">
                        <label class="form-label hfb-section-header-bordered">Logo Placement &amp; Transformation</label>
                        
                        <div class="hfb-grid-2col-responsive">
                            <!-- Scale Slider -->
                            <div class="control-group">
                                <div class="hfb-field-label-between">
                                    <span>Logo Scale:</span>
                                    <strong id="logo-scale-val"><?= preview_escape($options['logo_scale'] ?? '100') ?>%</strong>
                                </div>
                                <input type="range" name="logo_scale" id="logo_scale" min="20" max="250" step="1" value="<?= preview_escape($options['logo_scale'] ?? '100') ?>" class="hfb-full-input-range">
                            </div>
                            
                            <!-- Rotation Slider -->
                            <div class="control-group">
                                <div class="hfb-field-label-between">
                                    <span>Logo Rotation:</span>
                                    <strong id="logo-rotation-val"><?= preview_escape($options['logo_rotation'] ?? '0') ?>°</strong>
                                </div>
                                <input type="range" name="logo_rotation" id="logo_rotation" min="-180" max="180" step="1" value="<?= preview_escape($options['logo_rotation'] ?? '0') ?>" class="hfb-full-input-range">
                            </div>

                            <!-- Opacity Slider -->
                            <div class="control-group">
                                <div class="hfb-field-label-between">
                                    <span>Logo Opacity:</span>
                                    <strong id="logo-opacity-val"><?= preview_escape($options['logo_opacity'] ?? '100') ?>%</strong>
                                </div>
                                <input type="range" name="logo_opacity" id="logo_opacity" min="0" max="100" step="1" value="<?= preview_escape($options['logo_opacity'] ?? '100') ?>" class="hfb-full-input-range">
                            </div>

                            <!-- X Offset Slider -->
                            <div class="control-group">
                                <div class="hfb-field-label-between">
                                    <span>Horizontal Mover (X):</span>
                                    <strong id="logo-offset-x-val"><?= preview_escape($options['logo_offset_x'] ?? '0') ?>px</strong>
                                </div>
                                <input type="range" name="logo_offset_x" id="logo_offset_x" min="-100" max="100" step="1" value="<?= preview_escape($options['logo_offset_x'] ?? '0') ?>" class="hfb-full-input-range">
                            </div>

                            <!-- Y Offset Slider -->
                            <div class="control-group">
                                <div class="hfb-field-label-between">
                                    <span>Vertical Mover (Y):</span>
                                    <strong id="logo-offset-y-val"><?= preview_escape($options['logo_offset_y'] ?? '0') ?>px</strong>
                                </div>
                                <input type="range" name="logo_offset_y" id="logo_offset_y" min="-100" max="100" step="1" value="<?= preview_escape($options['logo_offset_y'] ?? '0') ?>" class="hfb-full-input-range">
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
                            <div class="stamp-control-custom-row" id="stamp-color-enable-row" class="hfb-drawer-field-row">
                                <span class="hfb-drawer-label">Customize SVG Color</span>
                                <label class="zrx-switch">
                                    <input type="checkbox" id="stamp-color-enable-chk" <?= $stampColorEnable === 'yes' ? 'checked' : '' ?>>
                                    <span class="zrx-slider"></span>
                                </label>
                            </div>
                            <!-- SVG Stamp Color Picker -->
                            <div class="stamp-control-custom-row" id="stamp-color-row" class="hfb-drawer-field-row">
                                <span class="hfb-drawer-label">Stamp Color</span>
                                <div class="zrx-flex-ac-5">
                                    <input type="color" id="stamp-color-picker" value="<?= preview_escape($stampColor) ?>" class="hfb-color-palette-btn">
                                    <div class="hex-input-label hfb-color-hex-wrapper">
                                        <span class="hfb-color-hash">#</span>
                                        <input type="text" id="stamp-color-hex" value="<?= ltrim($stampColor, '#') ?>" class="hfb-color-hex-field">
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

    <!-- Background Image Gallery Modal -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-gallery-overlay">
        <div class="zrx-gallery-modal">
            <div class="zrx-gallery-header">
                <h3 id="zrx-gallery-title">Select Background Image</h3>
                <div class="zrx-flex-ac-5">
                    <div id="bg-gallery-upload-container" class="hfb-img-actions-flex">
                        <input type="file" id="bg-gallery-upload-input" accept=".svg,.png,.jpg,.jpeg" hidden>
                        <button type="button" class="btn btn-outline zrx-logo-gallery-upload-btn" id="bg-gallery-upload-btn" class="btn btn-outline hfb-btn-sm-action">&#8679; Upload New</button>
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
            <div id="bg-gallery-upload-status" class="zrx-logo-gallery-status hfb-mb-sm"></div>
            <div class="zrx-gallery-grid" id="zrx-gallery-grid">
                <div class="zrx-gallery-loading">Loading&hellip;</div>
            </div>
        </div>
    </div>
    <!-- Logo Gallery Modal -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-logo-gallery-overlay">
        <div class="zrx-gallery-modal">
            <div class="zrx-gallery-header">
                <h3>Select Logo</h3>
                <div class="zrx-flex-ac-5">
                    <input type="file" id="logo-gallery-upload-input" accept="image/*" hidden>
                    <button type="button" class="btn btn-outline zrx-logo-gallery-upload-btn" id="logo-gallery-upload-btn" class="btn btn-outline hfb-btn-sm-action">&#8679; Upload New</button>
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


    <!-- Confirm Modal -->
    <div class="zrx-gallery-overlay is-hidden" id="zrx-confirm-overlay" class="hfb-drawer-ztop">
        <div class="zrx-confirm-modal">
            <div class="zrx-confirm-body" id="zrx-confirm-message">
                Are you sure you want to permanently delete this uploaded logo?
            </div>
            <div class="zrx-confirm-actions">
                <button type="button" class="btn btn-outline" id="zrx-confirm-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="zrx-confirm-ok" class="hfb-btn-danger-solid">Delete</button>
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
            <?php $onboardDefaults = zimrx_onboarding_defaults()['doctor_profile'] ?? []; ?>
            <form id="zrx-onboard-form" class="zrx-onboard-form" autocomplete="off" data-defaults="<?= htmlspecialchars(json_encode($onboardDefaults, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <?= zimrx_csrf_field() ?>
                <div class="zrx-onboard-grid">
                    <!-- Native Side (Left Column) -->
                    <div class="zrx-onboard-column">
                        <h3>Native Details (Left Side)</h3>
                        <div class="zrx-onboard-field">
                            <label>Doctor Name (Native) *</label>
                            <input type="text" name="name_native" placeholder="Doctor Name" required>
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Qualifications</label>
                            <input type="text" name="qualifications_native" placeholder="Qualifications">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Designation</label>
                            <input type="text" name="designation_native" placeholder="Designation">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Institute / Mission</label>
                            <input type="text" name="institute_native" placeholder="Institute / Workplace">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Specialty</label>
                            <input type="text" name="speciality_native" placeholder="Specialty">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>License / Registration No.</label>
                            <input type="text" name="license_native" placeholder="Reg. / License No.">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Mobile / Phone Number</label>
                            <input type="text" name="phone_native" placeholder="Phone Number">
                        </div>
                    </div>

                    <!-- English Side (Right Column) -->
                    <div class="zrx-onboard-column">
                        <h3>English Details (Right Side)</h3>
                        <div class="zrx-onboard-field">
                            <label>Doctor Name (English) *</label>
                            <input type="text" name="name_en" placeholder="E.g. Dr. Alex Mercer" required>
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Qualifications</label>
                            <input type="text" name="qualifications_en" placeholder="E.g. MD, FACP">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Designation</label>
                            <input type="text" name="designation_en" placeholder="E.g. Senior Consultant">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Institute / Work Place</label>
                            <input type="text" name="institute_en" placeholder="E.g. Metropolitan Clinical Center">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Speciality</label>
                            <input type="text" name="speciality_en" placeholder="E.g. Internal Medicine">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>License / Registration No.</label>
                            <input type="text" name="license_en" placeholder="E.g. Reg. / License No: MD-98765">
                        </div>
                        <div class="zrx-onboard-field">
                            <label>Mobile / Phone Number</label>
                            <input type="text" name="phone_en" placeholder="E.g. Phone: +1 (555) 019-2834">
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

<script src="assets/vendor/nicedit/nicEdit-latest.js?v=<?= filemtime(__DIR__ . '/assets/vendor/nicedit/nicEdit-latest.js') ?>"></script>
<script src="assets/vendor/nicedit/nicEdit-zimrx-custom.js?v=<?= filemtime(__DIR__ . '/assets/vendor/nicedit/nicEdit-zimrx-custom.js') ?>"></script>
<script src="assets/js/pages/header_footer_setup.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/header_footer_setup.js') ?>"></script>
<?php include 'footer.php'; ?>
