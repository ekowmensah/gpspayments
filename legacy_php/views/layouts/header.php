<?php
declare(strict_types=1);

$displayName = trim((string)($_SESSION['user_name'] ?? 'Guest'));
$displayRole = (string)($_SESSION['user_role'] ?? 'Visitor');
?>
<header class="layout-header">
    <div class="layout-header-text">
        <h1><?php echo htmlspecialchars($layoutTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($layoutSubtitle !== ''): ?>
            <p><?php echo htmlspecialchars($layoutSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <div class="layout-header-meta">
        <span class="chip"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="chip muted"><?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php foreach ($layoutActions as $action): ?>
            <a class="chip link" href="<?php echo htmlspecialchars((string)($action['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string)($action['label'] ?? 'Action'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>
</header>

