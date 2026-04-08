<?php
/**
 * Auto redirect to public folder
 * Redirect: https://kursuscerdas.com/absen/ → https://kursuscerdas.com/absen/public/
 */

// Get the current script directory name
$currentDir = basename(__DIR__);

// Build redirect URL to public folder
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Extract the base path (e.g., /absen/)
$basePath = dirname($scriptName);
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

// Redirect to public folder
$redirectUrl = $protocol . '://' . $host . $basePath . '/public/';

// Also pass along any query string
if (!empty($_SERVER['QUERY_STRING'])) {
    $redirectUrl .= '?' . $_SERVER['QUERY_STRING'];
}

// Send redirect header
header('Location: ' . $redirectUrl, true, 301);
exit;
