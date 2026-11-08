<?php declare(strict_types=1); ?>
<main class="geo-first-run-layer" id="geoSetupPage" hidden>
    <div class="geo-first-run-page">
        <section class="geo-first-run-card" role="dialog" aria-modal="true" aria-labelledby="geoSetupTitle">
            <div class="geo-first-run-content">
                <h1 id="geoSetupTitle">Welcome to ZimRx Geo Studio</h1>
                <p class="geo-first-run-intro">Enter your name for contributor attribution.</p>

                <form id="geoSetupForm" novalidate>
                    <div class="geo-first-run-name">
                        <label for="geoSetupContributor">Your name</label>
                        <input id="geoSetupContributor" name="contributor_name" type="text" maxlength="80"
                            autocomplete="name" required>
                        <p>Geo Studio adds this name to pack metadata when you create or edit a pack.</p>
                    </div>

                    <p class="geo-first-run-status" id="geoSetupStatus" role="status" aria-live="polite"></p>
                    <div class="geo-first-run-actions">
                        <button type="submit" class="geo-btn geo-btn-primary" id="btnSaveSetup">Continue</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
