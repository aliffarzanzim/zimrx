<?php
declare(strict_types=1);

// Patient demographic fields (Name, Age, DOB, Gender, Reg No, Contact, Referral).

$prescription_prefill = is_array($prescription_prefill ?? null) ? $prescription_prefill : [];
$pres_val = static function (string $key, string $default = '') use ($prescription_prefill): string {
    return htmlspecialchars((string)($prescription_prefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$pres_selected = static function (string $key, string $value, string $default = '') use ($prescription_prefill): string {
    return (string)($prescription_prefill[$key] ?? $default) === $value ? ' selected' : '';
};
$patient_dropdown = static function (string $id, array $options, string $selected): string {
    $option_markup = '';
    $selected_label = '--';
    foreach ($options as $value => $label) {
        $value = (string)$value;
        $label = (string)$label;
        $is_selected = $value === $selected;
        if ($is_selected) {
            $selected_label = $label;
        }
        $option_markup .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . ($is_selected ? ' selected' : '') . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $safe_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $safe_label = htmlspecialchars($selected_label, ENT_QUOTES, 'UTF-8');
    return '<select id="' . $safe_id . '" class="zrx-select zrx-select-native" aria-hidden="true" tabindex="-1">' . $option_markup . '</select>'
        . '<div id="zrx-wrap-' . $safe_id . '" class="zrx-custom-select autocomplete-wrapper">'
        . '<div class="zrx-select-trigger" tabindex="0" role="combobox" aria-expanded="false">'
        . '<span class="zrx-select-label">' . $safe_label . '</span>'
        . '<span class="dropdown-arrow">' . zrx_icon('chevron-down', 14) . '</span>'
        . '</div><ul class="zrx-dropdown autocomplete-list"></ul></div>';
};
$gender_options = array_combine(\ZimRx\Services\PatientDemographics::genders(), \ZimRx\Services\PatientDemographics::genders()) ?: [];
$blood_group_options = ['' => '--'] + (array_combine(\ZimRx\Services\PatientDemographics::bloodGroups(), \ZimRx\Services\PatientDemographics::bloodGroups()) ?: []);
$weight_unit_options = array_combine(\ZimRx\Services\PatientDemographics::weightUnits(), \ZimRx\Services\PatientDemographics::weightUnits()) ?: [];
$height_unit_options = array_combine(\ZimRx\Services\PatientDemographics::heightUnits(), \ZimRx\Services\PatientDemographics::heightUnits()) ?: [];
$marital_status_options = ['' => '--'] + (array_combine(\ZimRx\Services\PatientDemographics::maritalStatuses(), \ZimRx\Services\PatientDemographics::maritalStatuses()) ?: []);

$current_ref_type = (string)($prescription_prefill['ref_type'] ?? 'Self');
$ref_needs_text = in_array(strtolower($current_ref_type), ['doctor', 'others'], true);
?>

<!-- Primary demographics -->
<div class="p-row p-row-1">
    <div class="p-field">
        <label><?= zrx_icon('user', 12) ?>Patient Name</label>
        <input type="text" id="patient-name" value="<?= $pres_val('patient_name') ?>" placeholder="Full Patient Name" required>
    </div>

    <div class="p-field p-field-age" id="p-field-age" data-field-name="age">
        <label><?= zrx_icon('clock', 12) ?>Age</label>
        <div class="age-triple-group" id="patient-age-triple-group">
            <input type="number" id="patient-age-years" min="0" max="150" placeholder="Y" title="Years">
            <span class="age-sep">y</span>
            <input type="number" id="patient-age-months" min="0" max="11" placeholder="M" title="Months">
            <span class="age-sep">m</span>
            <input type="number" id="patient-age-days" min="0" max="30" placeholder="D" title="Days">
            <span class="age-sep">d</span>
        </div>
        <input type="hidden" id="patient-age" value="<?= $pres_val('age') ?>">
        <input type="hidden" id="patient-age-unit" value="<?= $pres_val('age_unit', 'Years') ?>">
    </div>

    <div class="p-field p-field-dob" id="p-field-dob" data-field-name="dob">
        <label>
            <?= zrx_icon('calendar', 12) ?>
            <?php
            $has_dob_val = !empty($prescription_prefill['dob']);
            $initial_dob_type = (string)($prescription_prefill['dob_type'] ?? 'exact');
            $dob_badge_label = $initial_dob_type === 'estimated' ? '(Estimated)' : '(Exact)';
            ?>
            <span>DOB<span id="patient-dob-type-badge" class="dob-type-text <?= $initial_dob_type === 'estimated' ? 'estimated' : 'exact' ?>" role="button" tabindex="0" title="Click to toggle: Exact / Estimated"<?= $has_dob_val ? '' : ' hidden style="display:none;"' ?>><?= $has_dob_val ? ' ' . $dob_badge_label : '' ?></span></span>
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="dob" title="জন্মতারিখ (DOB) নির্দেশিকা" aria-label="DOB Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <input type="text" id="patient-dob" class="custom-date-picker" value="<?= $pres_val('dob') ?>" placeholder="DD/MM/YYYY">
        <input type="hidden" id="patient-dob-type" value="<?= $pres_val('dob_type', 'exact') ?>">
    </div>

    <div class="p-field">
        <label><?= zrx_icon('users', 12) ?>Sex</label>
        <?= $patient_dropdown('patient-gender', ['' => '--'] + $gender_options, (string)($prescription_prefill['gender'] ?? '')) ?>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('droplet', 12) ?>BG</label>
        <?= $patient_dropdown('patient-blood-group', $blood_group_options, (string)($prescription_prefill['blood_group'] ?? '')) ?>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('calendar', 12) ?>Date</label>
        <input type="text" id="<?= !empty($is_appointment_modal) ? 'appointment-date' : 'patient-date' ?>" class="custom-date-picker" value="<?= $pres_val('appointment_date', date('d/m/Y')) ?>" placeholder="DD/MM/YYYY" <?= empty($is_appointment_modal) ? 'readonly aria-readonly="true"' : '' ?> required>
    </div>

    <div class="p-field vis-field">
        <label>
            <?= zrx_icon('clipboard', 12) ?>Visit ID
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="visit-id" title="ভিজিট আইডি (Visit ID) নির্দেশিকা" aria-label="Visit ID Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <input type="text" id="visit-code" value="<?= $pres_val('visit_id', (string)($prescription_prefill['visit_code'] ?? '')) ?>" placeholder="Auto on save" readonly>
    </div>
</div>

<!-- Contact, metrics, and identifiers -->
<div class="p-row p-row-2">
    <div class="p-field reg-field lookup-field">
        <label>
            <?= zrx_icon('hash', 12) ?>
            Reg No
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="<?= !empty($is_appointment_modal) ? 'appt-reg' : 'pres-reg' ?>" title="রেজিস্ট্রেশন নম্বর নির্দেশিকা" aria-label="Reg No Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <div class="autocomplete-wrapper" id="reg-wrapper">
            <input type="text" id="patient-reg-no" value="<?= $pres_val('reg_no') ?>" placeholder="Auto on save or search Reg No" autocomplete="off">
            <ul class="zrx-dropdown" id="reg-list"></ul>
        </div>
    </div>

    <div class="p-field lookup-field reg-field">
        <label>
            <?= zrx_icon('phone', 12) ?>
            Mobile
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="mobile" title="ফোন নম্বর নির্দেশিকা" aria-label="Mobile Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <div class="autocomplete-wrapper" id="mobile-wrapper">
            <input type="text" id="patient-mobile" value="<?= $pres_val('mobile') ?>" placeholder="01XXX-XXXXXX" autocomplete="off">
            <ul class="zrx-dropdown" id="mobile-list"></ul>
        </div>
    </div>

    <div class="p-field">
        <label>
            <?= zrx_icon('briefcase', 12) ?>Occupation
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="occupation" title="পেশা (Occupation) নির্দেশিকা ও ম্যানেজমেন্ট" aria-label="Occupation Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <div class="autocomplete-wrapper" id="occ-wrapper">
            <input type="text" id="patient-occupation" value="<?= $pres_val('occupation') ?>" placeholder="Occupation..." autocomplete="off">
            <ul class="zrx-dropdown" id="occupation-list"></ul>
        </div>
    </div>

    <div class="p-field">
        <label>
            <?= zrx_icon('map-pin', 12) ?>Address
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="address" title="ঠিকানা (Address) নির্দেশিকা ও ম্যানেজমেন্ট" aria-label="Address Help">
                <?= zrx_icon('help-circle', 16) ?>
            </span>
        </label>
        <div class="autocomplete-wrapper" id="address-wrapper">
            <input type="text" id="patient-address" value="<?= $pres_val('address') ?>" placeholder="Village, Union, Upazila..." autocomplete="off">
            <ul class="zrx-dropdown" id="address-list"></ul>
        </div>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('scale', 12) ?>Weight</label>
        <div class="input-group">
            <input type="text" id="patient-weight" value="<?= $pres_val('weight') ?>" placeholder="Wt">
            <?= $patient_dropdown('patient-weight-unit', $weight_unit_options, (string)($prescription_prefill['weight_unit'] ?? 'kg')) ?>
        </div>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('ruler', 12) ?>Height</label>
        <div class="input-group">
            <input type="text" id="patient-height" value="<?= $pres_val('height') ?>" placeholder="Ht">
            <?= $patient_dropdown('patient-height-unit', $height_unit_options, (string)($prescription_prefill['height_unit'] ?? 'inch')) ?>
        </div>
    </div>

    <div class="p-field" data-config-key="marital_status">
        <label for="patient-marital-status"><?= zrx_icon('heart', 12) ?>Marital Status</label>
        <?= $patient_dropdown('patient-marital-status', $marital_status_options, (string)($prescription_prefill['marital_status'] ?? '')) ?>
    </div>

    <div class="p-field vis-field" data-field-name="visit_no">
        <label><?= zrx_icon('activity', 12) ?>Visit No</label>
        <input type="text" id="visit-no" value="<?= $pres_val('visit_no') ?>" placeholder="Auto on save" readonly>
    </div>

    <div class="p-field p-field-extra-btn">
        <label>&nbsp;</label>
        <button type="button" id="btn-patient-extra-modal" class="btn-patient-extra" data-zrx-modal-open="patient-extra-modal" title="More Demographics & Details">
            <?= zrx_icon('user-plus', 12) ?>
            <span>More Info...</span>
            <span class="extra-demographics-dot" id="extra-demographics-dot" hidden></span>
        </button>
    </div>
</div>

<div class="p-row p-row-configurable" id="patient-configurable-main-fields" hidden></div>
<div class="p-row p-row-configurable" id="patient-custom-main-fields" hidden></div>

<?php include ZIMRX_BASE_DIR . '/views/components/patient_extended_demographics_modal.php'; ?>
