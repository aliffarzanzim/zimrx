<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_admin();
$page_title = 'ZimRx - Payments';
$extra_css = ['assets/css/pages/admin.css'];
include 'header.php';
?>
<main class="admin-page">
    <section class="admin-hero">
        <div>
            <p class="eyebrow">Coming Soon</p>
            <h1>Payments</h1>
            <p>Central payment reporting will be added here later.</p>
        </div>
    </section>
</main>
<?php include 'footer.php'; ?>
