<?php
declare(strict_types=1);

if (empty($layoutNavLinks)) {
    $layoutNavLinks = [
        ['href' => $basePath . '/auth/login', 'label' => 'Login'],
    ];
}
?>
<aside class="layout-sidebar">
    <div class="sidebar-brand">
        <span class="eyebrow">GPS Payments</span>
        <h2>Operations Hub</h2>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($layoutNavLinks as $link): ?>
            <?php
            $href = (string)($link['href'] ?? '#');
            $label = (string)($link['label'] ?? 'Link');
            $isActive = $requestPath === parse_url($href, PHP_URL_PATH);
            ?>
            <a class="sidebar-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

