<?php
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$errorMessage = $error_message ?? null;
$validationErrors = $errors ?? [];
$csrfToken = $_SESSION['csrf_token'] ?? '';

ob_start();
?>
<section class="login-shell">
    <form class="panel login-card" method="post" action="<?php echo htmlspecialchars($basePath . '/auth/login', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="eyebrow">GPS Payments</span>
        <div class="title-block">
            <h2>Member Finance Console</h2>
            <p>Secure sign-in for administrators, treasurers, auditors, and secretaries.</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-box"><?php echo htmlspecialchars((string)$errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($validationErrors)): ?>
            <div class="error-box"><?php echo htmlspecialchars(implode(' | ', array_map(static fn($fieldErrors) => implode(', ', (array)$fieldErrors), $validationErrors)), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" required autocomplete="email">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button class="btn" type="submit">Enter Workspace</button>
        <p class="hint">Need access? Contact your system administrator.</p>
    </form>
</section>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = APP_NAME . ' - Login';
$layoutSubtitle = 'Authenticate to continue';
$layoutActions = [];
$layoutNavLinks = [
    ['href' => $basePath . '/auth/login', 'label' => 'Login'],
];
$layoutShowFooter = false;
$layoutBodyClass = 'auth-page';
require __DIR__ . '/../layouts/base.php';
