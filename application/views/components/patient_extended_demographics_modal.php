<?php
declare(strict_types=1);

// Extended patient demographics modal: family, emergency contact, and identification.
$extra_json = $prescription_prefill['extra_demographics_json'] ?? '{}';
$extra_data = is_array($extra_json) ? $extra_json : (json_decode((string)$extra_json, true) ?: []);
$ext_val = static function (string $key, string $default = '') use ($extra_data, $prescription_prefill): string {
    return htmlspecialchars((string)($extra_data[$key] ?? $prescription_prefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$ec_data = is_array($extra_data['emergency_contact'] ?? null) ? $extra_data['emergency_contact'] : [];
$ec_val = static function (string $key, string $default = '') use ($ec_data): string {
    return htmlspecialchars((string)($ec_data[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$modal_header_actions_html = '';
ob_start();
?>
            <div class="patient-extra-sections-grid">
                <fieldset class="patient-extra-fieldset" id="patient-configurable-modal-section" hidden>
                    <legend>Additional Patient Details</legend>
                    <div class="extra-field-row" id="patient-configurable-modal-fields"></div>
                </fieldset>

                <fieldset class="patient-extra-fieldset" id="patient-custom-modal-section" hidden>
                    <legend>Custom Patient Details</legend>
                    <div class="extra-field-row" id="patient-custom-modal-fields"></div>
                </fieldset>

                <!-- Family details -->
                <fieldset class="patient-extra-fieldset" data-field-group="family">
                    <legend><?= zrx_icon('heart', 12) ?> Family Details</legend>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="spouse_name">
                            <label for="patient-spouse-name">Spouse's Name</label>
                            <input type="text" id="patient-spouse-name" value="<?= $ext_val('spouse_name') ?>" placeholder="Full name of spouse" data-zrx-modal-autofocus>
                        </div>
                    </div>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="father_name">
                            <label for="patient-father-name">Father's Name</label>
                            <input type="text" id="patient-father-name" value="<?= $ext_val('father_name') ?>" placeholder="Father's full name">
                        </div>
                        <div class="extra-field-col" data-config-key="mother_name">
                            <label for="patient-mother-name">Mother's Name</label>
                            <input type="text" id="patient-mother-name" value="<?= $ext_val('mother_name') ?>" placeholder="Mother's full name">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="patient-extra-fieldset" data-field-group="referral">
                    <legend><?= zrx_icon('users', 12) ?>Referral Source</legend>
                    <div class="extra-field-row">
                        <div class="extra-field-col">
                            <label for="patient-ref-type">Referred by</label>
                            <div class="patient-referral-control<?= $ref_needs_text ? ' has-free-text' : '' ?>" id="patient-referral-control">
                                <select id="patient-ref-type">
                                    <?php foreach (\ZimRx\Services\PatientDemographics::referralTypes() as $ref): ?>
                                    <option value="<?= $ref ?>"<?= $pres_selected('ref_type', $ref, 'Self') ?>><?= $ref ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" id="patient-ref-by" value="<?= $pres_val('referral_name') ?>" placeholder="Doctor name" autocomplete="off" list="patient-referral-list"<?= $ref_needs_text ? '' : ' hidden' ?>>
                                <datalist id="patient-referral-list"></datalist>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- Section 2: Emergency Contact -->
                <fieldset class="patient-extra-fieldset" data-field-group="emergency">
                    <legend><?= zrx_icon('phone', 12) ?> Emergency Contact</legend>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="emergency_name">
                            <label for="patient-emergency-name">Contact Person</label>
                            <input type="text" id="patient-emergency-name" value="<?= $ec_val('name') ?>" placeholder="Name of contact">
                        </div>
                        <div class="extra-field-col" data-config-key="emergency_mobile">
                            <label for="patient-emergency-mobile">Contact Phone</label>
                            <input type="text" id="patient-emergency-mobile" value="<?= $ec_val('mobile') ?>" placeholder="Phone number">
                        </div>
                    </div>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="emergency_relation">
                            <label for="patient-emergency-relation">Relationship</label>
                            <select id="patient-emergency-relation">
                                <option value="">-- Select --</option>
                                <?php foreach (\ZimRx\Services\PatientDemographics::emergencyRelations() as $rel): ?>
                                <option value="<?= $rel ?>"<?= $ec_val('relation') === $rel ? ' selected' : '' ?>><?= $rel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="extra-field-col" data-config-key="emergency_address">
                            <label for="patient-emergency-address">Contact Address</label>
                            <input type="text" id="patient-emergency-address" value="<?= $ec_val('address') ?>" placeholder="Address of contact">
                        </div>
                    </div>
                </fieldset>

                <!-- Section 3: Identity & Cross-Border -->
                <fieldset class="patient-extra-fieldset" data-field-group="identity">
                    <legend><?= zrx_icon('id-card', 12) ?> Identity & International (HL7 FHIR IPS)</legend>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="email">
                            <label for="patient-email">Email Address</label>
                            <input type="email" id="patient-email" value="<?= $pres_val('email') ?>" placeholder="patient@example.com">
                        </div>
                        <div class="extra-field-col" data-config-key="nationality">
                            <label for="patient-nationality">Nationality</label>
                            <input type="text" id="patient-nationality" value="<?= $ext_val('nationality') ?>" placeholder="e.g. Bangladeshi">
                        </div>
                    </div>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="nid_no">
                            <label for="patient-nid-no">National ID (NID)</label>
                            <input type="text" id="patient-nid-no" value="<?= $pres_val('nid_no') ?>" placeholder="National Identity number">
                        </div>
                        <div class="extra-field-col" data-config-key="passport_no">
                            <label for="patient-passport-no">Passport No</label>
                            <input type="text" id="patient-passport-no" value="<?= $pres_val('passport_no') ?>" placeholder="Passport number">
                        </div>
                    </div>
                </fieldset>

                <!-- Section 4: Employment & Corporate -->
                <fieldset class="patient-extra-fieldset" data-field-group="employment">
                    <legend><?= zrx_icon('briefcase', 12) ?> Corporate & Employment</legend>
                    <div class="extra-field-row">
                        <div class="extra-field-col" data-config-key="company_name">
                            <label for="patient-company-name">Company / Organization</label>
                            <input type="text" id="patient-company-name" value="<?= $ext_val('company_name') ?>" placeholder="Employer or company name">
                        </div>
                        <div class="extra-field-col" data-config-key="company_address">
                            <label for="patient-company-address">Company Address</label>
                            <input type="text" id="patient-company-address" value="<?= $ext_val('company_address') ?>" placeholder="Office location or branch">
                        </div>
                    </div>
                </fieldset>
            </div>
<?php
$modal_body_html = ob_get_clean();
ob_start();
?>
<span class="patient-extra-hint">Changes in this modal are automatically applied to the active patient record.</span>
<button type="button" id="btn-done-patient-extra" class="btn btn-primary btn-sm" data-zrx-modal-close>Done</button>
<?php
$modal_footer_html = ob_get_clean();

$zrx_modal_config = [
    'id' => 'patient-extra-modal',
    'title' => 'Extended Demographics & Details',
    'dialog_class' => 'patient-extra-modal-card',
    'body_class' => 'patient-extra-modal-body',
    'footer_class' => 'zrx-modal-footer-spread',
    'close_button_id' => 'btn-close-patient-extra',
    'close_label' => 'Close extended demographics',
    'header_actions_html' => $modal_header_actions_html,
    'body_html' => $modal_body_html,
    'footer_html' => $modal_footer_html
];

include __DIR__ . '/zrx_modal.php';
