<?php
declare(strict_types=1);

/**
 * Force root-folder access to the Laravel public entrypoint.
 * Works for local subfolder installs and shared hosting subdirectories.
 */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = rtrim($scriptDir, '/');
$basePath = ($basePath === '' || $basePath === '.') ? '' : $basePath;

$target = $basePath . '/public/';
header('Location: ' . $target, true, 302);
exit;

