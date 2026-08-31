<?php
// footer.php — shared app footer
// coffee_modal.php handles its own include-once guard
include_once 'coffee_modal.php';
$app_version = '1.0.0';
?>

<!-- ── App Footer ──────────────────────────────────────────────────────── -->
<footer class="app-footer">
    <span>&copy; <?= date('Y') ?> ZimRx EMR System</span>
    <span class="app-footer-sep">|</span>
    <span>Version <?= htmlspecialchars($app_version) ?></span>
    <span class="app-footer-sep">|</span>
    <button type="button" class="app-footer-coffee" onclick="zimrxOpenCoffeeModal()">
        <span class="coffee-icon">☕</span>
        <span class="coffee-label">Buy me a coffee?</span>
    </button>
</footer>

<style>
    .app-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        padding: 0.85rem 2rem;
        font-size: 0.78rem;
        color: #94a3b8;
        background: transparent;
        border-top: 1px solid #e2e8f0;
        margin-top: 2rem;
    }
    .app-footer-sep { color: #cbd5e1; }
    .app-footer-coffee {
        background: none;
        border: none;
        padding: 0;
        margin: 0;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.78rem;
        font-weight: 600;
        color: #d97706;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        position: relative;
        transition: color 0.2s ease;
    }
    .app-footer-coffee .coffee-icon {
        display: inline-block;
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .app-footer-coffee .coffee-label {
        position: relative;
    }
    .app-footer-coffee .coffee-label::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0%;
        height: 1.5px;
        background: linear-gradient(90deg, #f59e0b, #d97706);
        border-radius: 1px;
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .app-footer-coffee:hover {
        color: #b45309;
    }
    .app-footer-coffee:hover .coffee-label::after {
        width: 100%;
    }
    .app-footer-coffee:hover .coffee-icon {
        transform: scale(1.25) rotate(-12deg);
    }
    .app-footer-coffee:active .coffee-icon {
        transform: scale(1.05) rotate(0deg);
    }
</style>

</body>
</html>
