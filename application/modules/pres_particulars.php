<?php
// modules/pres_particulars.php
$prescription_prefill = is_array($prescription_prefill ?? null) ? $prescription_prefill : [];
$pres_value = static function (string $key, string $default = '') use ($prescription_prefill): string {
    return htmlspecialchars((string)($prescription_prefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$pres_selected = static function (string $key, string $value, string $default = '') use ($prescription_prefill): string {
    return (string)($prescription_prefill[$key] ?? $default) === $value ? ' selected' : '';
};
?>
<section class="patient-particulars">
    <div class="patient-info">
        <input type="hidden" id="patient-id" value="<?= $pres_value('patient_id') ?>">
        <input type="hidden" id="appointment-id" value="<?= $pres_value('appointment_id') ?>">
        <input type="hidden" id="appointment-no" value="<?= $pres_value('appointment_no') ?>">
        <input type="hidden" id="appointment-time" value="<?= $pres_value('appointment_time') ?>">
        
        <!-- ROW 1 (7 Items): Name, Age, DOB, Sex, BG, Date, Visit ID -->
        <div class="p-row p-row-1">
            <div class="p-field">
                <label><?= zrx_icon('user', 12) ?>Patient Name</label>
                <input type="text" id="patient-name" value="<?= $pres_value('patient_name') ?>" placeholder="Full Patient Name" required>
            </div>

            <div class="p-field">
                <label><?= zrx_icon('clock', 12) ?>Age</label>
                <div class="input-group">
                    <input type="number" id="patient-age" value="<?= $pres_value('age') ?>" placeholder="Age" min="0">
                    <select id="patient-age-unit">
                        <option value="Years"<?= $pres_selected('age_unit', 'Years', 'Years') ?>>Years</option>
                        <option value="Months"<?= $pres_selected('age_unit', 'Months', 'Years') ?>>Months</option>
                        <option value="Weeks"<?= $pres_selected('age_unit', 'Weeks', 'Years') ?>>Weeks</option>
                        <option value="Days"<?= $pres_selected('age_unit', 'Days', 'Years') ?>>Days</option>
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
                <input type="text" id="patient-dob" class="custom-date-picker" value="<?= $pres_value('dob') ?>" placeholder="DD/MM/YYYY">
            </div>

            <div class="p-field">
                <label><?= zrx_icon('users', 12) ?>Sex</label>
                <select id="patient-gender">
                    <option value=""<?= $pres_selected('gender', '') ?>>--</option>
                    <option value="Male"<?= $pres_selected('gender', 'Male') ?>>Male</option>
                    <option value="Female"<?= $pres_selected('gender', 'Female') ?>>Female</option>
                    <option value="Others"<?= $pres_selected('gender', 'Others') ?>>Others</option>
                </select>
            </div>

            <div class="p-field">
                <label><?= zrx_icon('droplet', 12) ?>BG</label>
                <select id="patient-blood-group">
                    <option value=""<?= $pres_selected('blood_group', '') ?>>--</option>
                    <option value="A+"<?= $pres_selected('blood_group', 'A+') ?>>A+</option><option value="A-"<?= $pres_selected('blood_group', 'A-') ?>>A-</option>
                    <option value="B+"<?= $pres_selected('blood_group', 'B+') ?>>B+</option><option value="B-"<?= $pres_selected('blood_group', 'B-') ?>>B-</option>
                    <option value="O+"<?= $pres_selected('blood_group', 'O+') ?>>O+</option><option value="O-"<?= $pres_selected('blood_group', 'O-') ?>>O-</option>
                    <option value="AB+"<?= $pres_selected('blood_group', 'AB+') ?>>AB+</option><option value="AB-"<?= $pres_selected('blood_group', 'AB-') ?>>AB-</option>
                </select>
            </div>

            <div class="p-field">
                <label><?= zrx_icon('calendar', 12) ?>Date</label>
                <input type="text" id="patient-date" class="custom-date-picker" value="<?= $pres_value('appointment_date', date('d/m/Y')) ?>" placeholder="DD/MM/YYYY">
            </div>

            <div class="p-field vis-field">
                <label>
                    <?= zrx_icon('clipboard', 12) ?>Visit ID
                    <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="visit-id" title="ভিজিট আইডি (Visit ID) নির্দেশিকা" aria-label="Visit ID Help">
                        <?= zrx_icon('help-circle', 13) ?>
                    </span>
                </label>
                <input type="text" id="visit-code" value="<?= $pres_value('visit_id', (string)($prescription_prefill['visit_code'] ?? '')) ?>" placeholder="Auto on save" readonly>
            </div>
        </div>

        <!-- ROW 2 (7 Items): Reg No, Mobile, Occ, Address, Weight, Height, Visit No -->
        <div class="p-row p-row-2">
            <div class="p-field reg-field lookup-field">
                <label>
                    <?= zrx_icon('hash', 12) ?>
                    Reg No
                    <span role="button" tabindex="0" class="zrx-help-icon-btn" data-help-type="pres-reg" title="রেজিস্ট্রেশন নম্বর নির্দেশিকা" aria-label="Reg No Help">
                        <?= zrx_icon('help-circle', 13) ?>
                    </span>
                </label>
                <div class="autocomplete-wrapper" id="reg-wrapper">
                    <input type="text" id="patient-reg-no" value="<?= $pres_value('reg_no') ?>" placeholder="Auto on save or search Reg No" autocomplete="off">
                    <ul class="autocomplete-list appointment-lookup-list" id="reg-list"></ul>
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
                    <input type="text" id="patient-mobile" value="<?= $pres_value('mobile') ?>" placeholder="01XXX-XXXXXX" autocomplete="off">
                    <ul class="autocomplete-list appointment-lookup-list" id="mobile-list"></ul>
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
                    <input type="text" id="patient-occupation" value="<?= $pres_value('occupation') ?>" placeholder="Occupation..." autocomplete="off">
                    <ul class="autocomplete-list" id="occupation-list"></ul>
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
                    <input type="text" id="patient-address" value="<?= $pres_value('address') ?>" placeholder="Village, Union, Upazila..." autocomplete="off">
                    <ul class="autocomplete-list" id="address-list"></ul>
                </div>
            </div>

            <div class="p-field">
                <label><?= zrx_icon('scale', 12) ?>Weight</label>
                <div class="input-group">
                    <input type="text" id="patient-weight" value="<?= $pres_value('weight') ?>" placeholder="Wt">
                    <select id="patient-weight-unit">
                        <option value="kg"<?= $pres_selected('weight_unit', 'kg', 'kg') ?>>kg</option>
                        <option value="lb"<?= $pres_selected('weight_unit', 'lb', 'kg') ?>>lb</option>
                    </select>
                </div>
            </div>

            <div class="p-field">
                <label><?= zrx_icon('ruler', 12) ?>Height</label>
                <div class="input-group">
                    <input type="text" id="patient-height" value="<?= $pres_value('height') ?>" placeholder="Ht">
                    <select id="patient-height-unit">
                        <option value="inch"<?= $pres_selected('height_unit', 'inch', 'inch') ?>>inch</option>
                        <option value="feet"<?= $pres_selected('height_unit', 'feet', 'inch') ?>>feet</option>
                        <option value="cm"<?= $pres_selected('height_unit', 'cm', 'inch') ?>>cm</option>
                        <option value="meter"<?= $pres_selected('height_unit', 'meter', 'inch') ?>>meter</option>
                    </select>
                </div>
            </div>

            <?php
            $current_ref_type = (string)($prescription_prefill['ref_type'] ?? 'Self');
            $ref_needs_text = in_array(strtolower($current_ref_type), ['doctor', 'others'], true);
            ?>
            <div class="p-field">
                <label><?= zrx_icon('users', 12) ?>Referred by</label>
                <div class="patient-referral-control<?= $ref_needs_text ? ' has-free-text' : '' ?>" id="patient-referral-control">
                    <select id="patient-ref-type">
                        <option value="Self"<?= $pres_selected('ref_type', 'Self', 'Self') ?>>Self</option>
                        <option value="Other Patient"<?= $pres_selected('ref_type', 'Other Patient', 'Self') ?>>Other Patient</option>
                        <option value="Doctor"<?= $pres_selected('ref_type', 'Doctor', 'Self') ?>>Doctor</option>
                        <option value="Others"<?= $pres_selected('ref_type', 'Others', 'Self') ?>>Others</option>
                    </select>
                    <input type="text" id="patient-ref-by" value="<?= $pres_value('referral_name') ?>" placeholder="Doctor name" autocomplete="off" list="patient-referral-list"<?= $ref_needs_text ? '' : ' hidden' ?>>
                    <datalist id="patient-referral-list"></datalist>
                </div>
            </div>

            <div class="p-field vis-field">
                <label><?= zrx_icon('activity', 12) ?>Visit No</label>
                <input type="text" id="visit-no" value="<?= $pres_value('visit_no') ?>" placeholder="Auto on save" readonly>
            </div>
        </div>

        <?php
        $has_patient_profile = !empty($prescription_prefill['patient_id']) && (int)$prescription_prefill['patient_id'] > 0;
        ?>
        <!-- ROW 3: Patient Profile Quick Link -->
        <div class="p-row-profile-action">
            <button type="button" id="btn-open-patient-profile" class="btn-open-patient-profile"<?= $has_patient_profile ? '' : ' disabled' ?> title="Open Patient Profile (EMR)">
                <?= zrx_icon('user', 14) ?>
                <span>Patient Profile</span>
                <?= zrx_icon('external-link', 12) ?>
            </button>
        </div>

    </div>
    
    <div class="patient-actions">
        <button type="button" class="btn btn-primary" id="btn-save-print"><?= zrx_icon('printer', 14) ?>Save & Print</button>
        <button type="button" class="btn btn-success" id="btn-save-only"><?= zrx_icon('save', 14) ?>Save Only</button>
        <a href="prescription_preview.php" target="_blank" class="btn btn-secondary" id="btn-preview-prescription"><?= zrx_icon('eye', 14) ?>Preview</a>
        <div class="clear-action-wrap">
            <button type="button" class="btn btn-outline" id="btn-clear-fields" aria-haspopup="menu" aria-expanded="false"><?= zrx_icon('download', 14) ?>Clear</button>
            <div class="clear-options-menu" id="clear-options-menu" role="menu" hidden>
                <button type="button" role="menuitem" data-clear-action="all">Clear all including particulars</button>
                <button type="button" role="menuitem" data-clear-action="prescription">Clear the prescription</button>
            </div>
        </div>
    </div>
</section>
