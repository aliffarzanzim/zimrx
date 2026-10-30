<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();
require_once ZIMRX_BASE_DIR . '/lib/Services/PracticeProfileService.php';

if (current_user_role() !== 'doctor') {
    http_response_code(403);
    exit('Practice profile management is available to doctor accounts.');
}

$profileService = new \ZimRx\Services\PracticeProfileService($pdo);
$profiles = $profileService->managedByCurrentUser();
$activeProfile = $profileService->activeForCurrentUser();
$page_title = 'ZimRx - Practice Profiles';
$extra_css = ['assets/css/pages/settings.css', 'assets/css/pages/practice_profiles.css'];
include __DIR__ . '/header.php';
?>
<main class="zrx-settings-index zrx-page-container" id="practice-profiles-page">
    <header class="zrx-settings-page-hero">
        <div class="zrx-settings-page-hero-copy">
            <h1>Practice Profiles</h1>
            <p>Keep appointments and billing separate for each practice. Sharing patient records is configured per profile.</p>
        </div>
    </header>

    <section class="zrx-settings-index-section practice-profile-current" aria-labelledby="practice-profile-current-title">
        <h2 id="practice-profile-current-title">Current practice</h2>
        <p class="practice-profile-current-name"><?= htmlspecialchars((string)($activeProfile['profile_name'] ?? 'No active profile'), ENT_QUOTES, 'UTF-8') ?></p>
    </section>

    <section class="zrx-settings-index-section" aria-labelledby="practice-profile-list-title">
        <h2 id="practice-profile-list-title">Profiles</h2>
        <div class="practice-profile-list">
            <?php foreach ($profiles as $profile): ?>
                <form class="practice-profile-form" data-profile-form>
                    <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                    <input type="hidden" name="doctor_id" value="<?= (int)$profile['doctor_id'] ?>">
                    <input type="hidden" name="is_active" value="<?= (int)$profile['is_active'] ?>">
                    <div class="practice-profile-form-heading">
                        <h3>Profile details</h3>
                        <?php if ((int)$profile['is_active'] === 1): ?>
                            <span class="practice-profile-state">Active</span>
                        <?php else: ?>
                            <span class="practice-profile-state is-inactive">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <label>Profile name
                        <input class="zrx-form-control" name="profile_name" maxlength="120" required value="<?= htmlspecialchars((string)$profile['profile_name'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>Address
                        <input class="zrx-form-control" name="address" maxlength="500" value="<?= htmlspecialchars((string)$profile['address'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>Phone
                        <input class="zrx-form-control" name="phone" maxlength="80" value="<?= htmlspecialchars((string)$profile['phone'], ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>Prescription footer note
                        <textarea class="zrx-form-control" name="prescription_footer_note" maxlength="5000" rows="3"><?= htmlspecialchars((string)$profile['prescription_footer_note'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <fieldset>
                        <legend>Cross-profile sharing</legend>
                        <div class="practice-profile-sharing-options">
                            <label><input type="checkbox" name="share_patient_particulars" value="1" <?= (int)$profile['share_patient_particulars'] === 1 ? 'checked' : '' ?>> <span>Patient particulars</span></label>
                            <label><input type="checkbox" name="share_emr" value="1" <?= (int)$profile['share_emr'] === 1 ? 'checked' : '' ?>> <span>EMR history and measurements</span></label>
                            <label><input type="checkbox" name="share_prescriptions" value="1" <?= (int)$profile['share_prescriptions'] === 1 ? 'checked' : '' ?>> <span>Prescription records</span></label>
                        </div>
                        <p>Patient particulars must be shared for other profiles to find or open the patient.</p>
                    </fieldset>
                    <div class="practice-profile-actions">
                        <button class="btn btn-primary" type="submit" <?= (int)$profile['is_active'] === 0 ? 'disabled' : '' ?>>Save profile</button>
                        <?php if ((int)$profile['id'] !== (int)($activeProfile['id'] ?? 0)): ?>
                            <?php if ((int)$profile['is_active'] === 0): ?>
                                <button class="btn btn-outline-primary" type="button" data-reactivate-profile>Reactivate</button>
                            <?php else: ?>
                            <button class="btn btn-outline-primary" type="button" data-archive-profile>Deactivate</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary" type="button" data-remove-profile>Remove profile, keep records</button>
                        <button class="btn btn-outline-danger" type="button" data-delete-profile-data>Delete profile and patient data</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="zrx-settings-index-section practice-profile-removal" aria-labelledby="practice-profile-removal-title">
        <h2 id="practice-profile-removal-title">Profile removal</h2>
        <p>Removing a profile hides it from profile selection and keeps its clinical and billing records. Deleting a profile and its patient data permanently removes the profile's records and uploaded files. Deletion is blocked when another profile or doctor still references those patients.</p>
    </section>

    <section class="zrx-settings-index-section" aria-labelledby="practice-profile-create-title">
        <h2 id="practice-profile-create-title">Add practice profile</h2>
        <form id="practice-profile-create-form" class="practice-profile-create-form">
            <label>Profile name <input class="zrx-form-control" name="profile_name" maxlength="120" required></label>
            <label>Address <input class="zrx-form-control" name="address" maxlength="500"></label>
            <label>Phone <input class="zrx-form-control" name="phone" maxlength="80"></label>
            <button class="btn btn-primary" type="submit">Add profile</button>
        </form>
    </section>
    <p class="practice-profile-status" id="practice-profile-status" role="status" aria-live="polite"></p>
</main>
<script src="assets/js/pages/practice_profiles.js?v=<?= file_exists(__DIR__ . '/assets/js/pages/practice_profiles.js') ? filemtime(__DIR__ . '/assets/js/pages/practice_profiles.js') : '1' ?>" defer></script>
<?php include __DIR__ . '/footer.php'; ?>
