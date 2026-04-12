<?php
/**
 * Simple migration runner via browser
 * Access: https://10.10.10.13/DB2/absen2/public/run-migrations.php
 */

// Load the framework
require_once __DIR__ . '/../system/Test/bootstrap.php';

$db = db_connect();

echo "<h1>Database Migration Runner</h1>";
echo "<h2>Checking Tables...</h2>";

// Check which tables exist
$tables = ['students', 'lecturers', 'subjects', 'meetings', 'attendance', 'otp_codes'];
foreach ($tables as $table) {
    $exists = $db->query(
        "SELECT COUNT(*) as cnt FROM information_schema.tables 
         WHERE table_schema = ? AND table_name = ?",
        [$db->getDatabase(), $table]
    )->getRowArray()['cnt'] > 0;
    
    echo "<p>" . ($exists ? "✅" : "❌") . " <strong>$table</strong>: " . ($exists ? "EXISTS" : "MISSING") . "</p>";
}

echo "<hr>";
echo "<h2>Running Migrations...</h2>";

try {
    $migration = \Config\Services::migrations();
    $migration->setNamespace(null);
    
    if ($migration->latest()) {
        echo "<p style='color:green;'>✅ All migrations completed successfully!</p>";
        
        // Show migration history
        $history = $migration->getHistory();
        if (!empty($history)) {
            echo "<h3>Migrations Applied:</h3>";
            echo "<ul>";
            foreach ($history as $item) {
                echo "<li>{$item['type']}: {$item['filename']}</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red;'>❌ Migration failed: " . $migration->getError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='/lecturer/report'>Go to Report Page</a></p>";
echo "<p><a href='/run-migrations.php'>Refresh this page</a></p>";
