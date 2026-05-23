<?php
$safeTitle = htmlspecialchars((string)($title ?? 'Dashboard'), ENT_QUOTES, 'UTF-8');
$safeUser = htmlspecialchars((string)(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars((string)($userRole ?? 'User'), ENT_QUOTES, 'UTF-8');
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1><?php echo $safeTitle; ?></h1>
            <p><?php echo $safeUser; ?> | <?php echo $safeRole; ?></p>
        </div>
    </header>

    <section class="panel" style="margin-bottom:14px;">
        <span class="eyebrow">Overview</span>
        <div class="kpi-grid">
            <?php foreach (($stats ?? []) as $name => $value): ?>
                <article class="kpi">
                    <div class="k"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$name), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="v"><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h2>Operational Snapshot</h2>
        <p class="hint" style="margin-top:0;">Live payload currently exposed while module pages mature.</p>
        <pre class="mono"><?php echo htmlspecialchars(json_encode([
            'recent_payments' => $recent_payments ?? [],
            'pending_reconciliation' => $pending_reconciliation ?? [],
            'active_members' => $active_members ?? [],
            'all_payments' => $all_payments ?? [],
            'my_payments' => $my_payments ?? [],
        ], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></pre>
    </section>
</div>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = (string)($title ?? 'Dashboard');
$layoutSubtitle = 'Association operations overview';
$layoutActions = [
    ['href' => $basePath . '/auth/logout', 'label' => 'Logout'],
];
$layoutNavLinks = [
    ['href' => $basePath . '/dashboard', 'label' => 'Dashboard'],
    ['href' => $basePath . '/members/page', 'label' => 'Members'],
    ['href' => $basePath . '/collections/page', 'label' => 'Collections'],
    ['href' => $basePath . '/payments/page', 'label' => 'Payments'],
    ['href' => $basePath . '/reconciliation/page', 'label' => 'Reconciliation'],
    ['href' => $basePath . '/reports/page', 'label' => 'Reports'],
    ['href' => $basePath . '/audit/page', 'label' => 'Audit'],
];
require __DIR__ . '/../layouts/base.php';
