<?php
/**
 * Quick Diagnostic - No framework dependencies
 * Access: https://kursuscerdas.com/absen/public/check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Quick Diagnostics</h1>";

// Test 1: Check if vendor folder exists
echo "<h2>1. Vendor/Autoload Check</h2>";
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ vendor/autoload.php EXISTS<br>";
} else {
    echo "❌ vendor/autoload.php NOT FOUND!<br>";
    echo "<strong>This is the problem!</strong> Run <code>composer install</code> on server.<br>";
}

// Test 2: Check writable folder
echo "<h2>2. Writable Folder Check</h2>";
$writableDirs = ['logs', 'session', 'cache', 'debugbar'];
$basePath = dirname(__DIR__) . '/writable/';

foreach ($writableDirs as $dir) {
    $fullPath = $basePath . $dir;
    if (!file_exists($fullPath)) {
        echo "❌ <code>$dir</code> folder NOT FOUND!<br>";
    } elseif (!is_writable($fullPath)) {
        echo "❌ <code>$dir</code> folder NOT WRITABLE!<br>";
    } else {
        echo "✅ <code>$dir</code> OK (permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . ")<br>";
    }
}

// Test 3: Check .env file
echo "<h2>3. Environment File Check</h2>";
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    echo "✅ .env file EXISTS<br>";
    if (!is_readable($envFile)) {
        echo "❌ .env file NOT READABLE!<br>";
    } else {
        echo "✅ .env file READABLE<br>";
    }
} else {
    echo "❌ .env file NOT FOUND!<br>";
}

// Test 4: Try to load CodeIgniter
echo "<h2>4. CodeIgniter Load Test</h2>";
if (file_exists($vendorPath)) {
    try {
        require_once $vendorPath;
        echo "✅ Autoload loaded successfully<br>";
        
        // Try to create app instance
        $app = require_once dirname(__DIR__) . '/system/bootstrap.php';
        echo "✅ CodeIgniter bootstrap successful<br>";
    } catch (Exception $e) {
        echo "❌ Error loading CodeIgniter:<br>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

// Test 5: PHP Info
echo "<h2>5. PHP Information</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Extensions loaded: intl, mbstring, json, mysqli, curl<br>";

echo "<hr>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>⚠️ DELETE THIS FILE AFTER USE!</strong></p>";
