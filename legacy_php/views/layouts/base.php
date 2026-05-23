<?php
declare(strict_types=1);

$basePath = $basePath ?? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$layoutTitle = $layoutTitle ?? 'GPS Payments';
$layoutSubtitle = $layoutSubtitle ?? '';
$layoutContent = $layoutContent ?? '';
$layoutNavLinks = $layoutNavLinks ?? [];
$layoutActions = $layoutActions ?? [];
$layoutShowSidebar = $layoutShowSidebar ?? true;
$layoutShowHeader = $layoutShowHeader ?? true;
$layoutShowFooter = $layoutShowFooter ?? true;
$layoutBodyClass = $layoutBodyClass ?? '';
$cssPath = ($basePath ?: '') . '/css/app.css';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($layoutTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="<?php echo htmlspecialchars($layoutBodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<div class="app-frame">
    <?php if ($layoutShowSidebar): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <div class="app-main">
        <?php if ($layoutShowHeader): ?>
            <?php include __DIR__ . '/header.php'; ?>
        <?php endif; ?>
        <main class="app-content">
            <?php echo $layoutContent; ?>
        </main>
        <?php if ($layoutShowFooter): ?>
            <?php include __DIR__ . '/footer.php'; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

