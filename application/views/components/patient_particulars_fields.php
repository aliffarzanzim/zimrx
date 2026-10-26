<?php
declare(strict_types=1);

// Patient demographic fields (Name, Age, DOB, Gender, Reg No, Contact, Referral).

$prescription_prefill = is_array($prescription_prefill ?? null) ? $prescription_prefill : [];
$pres_val = static function (string $key, string $default = '') use ($prescription_prefill): string {
    return htmlspecialchars((string)($prescription_prefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$pres_sel = static function (string $key, string $value, string $default = '') use ($prescription_prefill): string {
    return (string)($prescription_prefill[$key] ?? $default) === $value ? ' selected' : '';
};

$current_ref_type = (string)($prescription_prefill['ref_type'] ?? 'Self');
$ref_needs_text = in_array(strtolower($current_ref_type), ['doctor', 'others'], true);
?>

<!-- Primary demographics -->
<div class="p-row p-row-1">
    <div class="p-field">
        <label><?= zrx_icon('user', 12) ?>Patient Name</label>
        <input type="text" id="patient-name" value="<?= $pres_val('patient_name') ?>" placeholder="Full Patient Name" required>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('clock', 12) ?>Age</label>
        <div class="input-group">
            <input type="text" id="patient-age" value="<?= $pres_val('age') ?>" placeholder="Age">
            <select id="patient-age-unit">
                <?php foreach (\ZimRx\Services\PatientDemographics::ageUnits() as $unit): ?>
                <option value="<?= $unit ?>"<?= $pres_sel('age_unit', $unit, 'Years') ?>><?= $unit ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="p-field">
        <label>
            <?= zrx_icon('calendar', 12) ?>DOB
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="dob" title="জন্মতারিখ (DOB) নির্দেশিকা" aria-label="DOB Help">
                <?= zrx_icon('help-circle', 13) ?>
            </span>
        </label>
        <input type="text" id="patient-dob" class="custom-date-picker" value="<?= $pres_val('dob') ?>" placeholder="DD/MM/YYYY">
    </div>

    <div class="p-field">
        <label><?= zrx_icon('users', 12) ?>Sex</label>
        <select id="patient-gender">
            <option value=""<?= $pres_sel('gender', '') ?>>--</option>
            <?php foreach (\ZimRx\Services\PatientDemographics::genders() as $gender): ?>
            <option value="<?= $gender ?>"<?= $pres_sel('gender', $gender) ?>><?= $gender ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('droplet', 12) ?>BG</label>
        <select id="patient-blood-group">
            <option value=""<?= $pres_sel('blood_group', '') ?>>--</option>
            <?php foreach (\ZimRx\Services\PatientDemographics::bloodGroups() as $bg): ?>
            <option value="<?= $bg ?>"<?= $pres_sel('blood_group', $bg) ?>><?= $bg ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('calendar', 12) ?>Date</label>
        <input type="text" id="<?= !empty($is_appointment_modal) ? 'appointment-date' : 'patient-date' ?>" class="custom-date-picker" value="<?= $pres_val('appointment_date', date('d/m/Y')) ?>" placeholder="DD/MM/YYYY" required>
    </div>

    <div class="p-field vis-field">
        <label>
            <?= zrx_icon('clipboard', 12) ?>Visit ID
            <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="visit-id" title="ভিজিট আইডি (Visit ID) নির্দেশিকা" aria-label="Visit ID Help">
                <?= zrx_icon('help-circle', 13) ?>
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
                <?= zrx_icon('help-circle', 13) ?>
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
                <?= zrx_icon('help-circle', 13) ?>
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
                <?= zrx_icon('help-circle', 13) ?>
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
                <?= zrx_icon('help-circle', 13) ?>
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
            <select id="patient-weight-unit">
                <?php foreach (\ZimRx\Services\PatientDemographics::weightUnits() as $unit): ?>
                <option value="<?= $unit ?>"<?= $pres_sel('weight_unit', $unit, 'kg') ?>><?= $unit ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('ruler', 12) ?>Height</label>
        <div class="input-group">
            <input type="text" id="patient-height" value="<?= $pres_val('height') ?>" placeholder="Ht">
            <select id="patient-height-unit">
                <?php foreach (\ZimRx\Services\PatientDemographics::heightUnits() as $unit): ?>
                <option value="<?= $unit ?>"<?= $pres_sel('height_unit', $unit, 'inch') ?>><?= $unit ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="p-field">
        <label><?= zrx_icon('users', 12) ?>Referred by</label>
        <div class="patient-referral-control<?= $ref_needs_text ? ' has-free-text' : '' ?>" id="patient-referral-control">
            <select id="patient-ref-type">
                <?php foreach (\ZimRx\Services\PatientDemographics::referralTypes() as $ref): ?>
                <option value="<?= $ref ?>"<?= $pres_sel('ref_type', $ref, 'Self') ?>><?= $ref ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="patient-ref-by" value="<?= $pres_val('referral_name') ?>" placeholder="Doctor name" autocomplete="off" list="patient-referral-list"<?= $ref_needs_text ? '' : ' hidden' ?>>
            <datalist id="patient-referral-list"></datalist>
        </div>
    </div>

    <div class="p-field vis-field">
        <label><?= zrx_icon('activity', 12) ?>Visit No</label>
        <input type="text" id="visit-no" value="<?= $pres_val('visit_no') ?>" placeholder="Auto on save" readonly>
    </div>
</div>
