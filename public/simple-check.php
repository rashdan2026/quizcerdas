<?php
/**
 * Ultra Simple Diagnostic - No framework at all
 * Access: https://kursuscerdas.com/absen/public/simple-check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Check</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 5px; }
        h1 { color: #333; }
        .test { padding: 15px; margin: 10px 0; border-left: 4px solid #ccc; background: #f9f9f9; }
        .pass { border-left-color: #28a745; background: #d4edda; }
        .fail { border-left-color: #dc3545; background: #f8d7da; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Ultra Simple Diagnostic</h1>

        <?php
        // Test 1: PHP Version
        echo '<div class="test pass">';
        echo '<strong>PHP Version:</strong> ' . phpversion() . '<br>';
        echo '</div>';

        // Test 2: Check vendor folder
        echo '<div class="test ' . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'pass' : 'fail') . '">';
        echo '<strong>Vendor Folder:</strong><br>';
        $vendorPath = realpath(__DIR__ . '/../vendor');
        if ($vendorPath) {
            echo '✅ vendor folder EXISTS at: <code>' . htmlspecialchars($vendorPath) . '</code><br>';
            
            // Count files in vendor
            $fileCount = 0;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorPath));
            foreach ($iterator as $file) {
                if ($file->isFile()) $fileCount++;
            }
            echo 'Total files in vendor: <strong>' . number_format($fileCount) . '</strong><br>';
            
            // Check autoload.php
            if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                echo '✅ vendor/autoload.php EXISTS<br>';
            } else {
                echo '❌ vendor/autoload.php NOT FOUND<br>';
            }
        } else {
            echo '❌ vendor folder NOT FOUND!<br>';
            echo 'Expected path: <code>' . htmlspecialchars(__DIR__ . '/../vendor') . '</code><br>';
        }
        echo '</div>';

        // Test 3: Check writable folders
        echo '<div class="test">';
        echo '<strong>Writable Folders:</strong><br>';
        $writableBase = realpath(__DIR__ . '/../writable');
        
        if ($writableBase) {
            echo '✅ writable folder EXISTS<br><br>';
            
            $folders = ['logs', 'session', 'cache', 'debugbar', 'uploads'];
            foreach ($folders as $folder) {
                $folderPath = $writableBase . '/' . $folder;
                $exists = file_exists($folderPath);
                $writable = is_writable($folderPath);
                
                echo ($exists && $writable ? '✅' : '❌') . ' <code>' . $folder . '/</code> - ';
                echo ($exists ? 'EXISTS' : 'NOT FOUND');
                if ($exists) {
                    echo ' | ' . ($writable ? 'WRITABLE' : 'NOT WRITABLE');
                    echo ' (perm: ' . substr(sprintf('%o', fileperms($folderPath)), -4) . ')';
                }
                echo '<br>';
            }
        } else {
            echo '❌ writable folder NOT FOUND!<br>';
        }
        echo '</div>';

        // Test 4: Check .env file
        echo '<div class="test">';
        echo '<strong>Environment File:</strong><br>';
        $envFile = realpath(__DIR__ . '/../.env');
        if ($envFile) {
            echo '✅ .env file EXISTS at: <code>' . htmlspecialchars($envFile) . '</code><br>';
            
            // Read and show DB config (masked)
            $envContent = file_get_contents($envFile);
            $lines = explode("\n", $envContent);
            
            echo '<table>';
            echo '<tr><th>Setting</th><th>Value</th></tr>';
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Mask sensitive data
                    if (stripos($key, 'password') !== false || stripos($key, 'secret') !== false || stripos($key, 'key') !== false) {
                        $value = '***MASKED***';
                    }
                    
                    echo '<tr><td><code>' . htmlspecialchars($key) . '</code></td>';
                    echo '<td>' . htmlspecialchars($value) . '</td></tr>';
                }
            }
            echo '</table>';
        } else {
            echo '❌ .env file NOT FOUND!<br>';
            echo 'Expected path: <code>' . htmlspecialchars(__DIR__ . '/../.env') . '</code><br>';
        }
        echo '</div>';

        // Test 5: Try to load composer
        echo '<div class="test">';
        echo '<strong>Composer Autoload Test:</strong><br>';
        
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                echo '✅ Composer autoload loaded successfully<br>';
                
                // Check if CodeIgniter classes exist
                if (class_exists('CodeIgniter\\CodeIgniter')) {
                    echo '✅ CodeIgniter class available<br>';
                } else {
                    echo '⚠️ CodeIgniter class NOT found<br>';
                }
            } catch (Exception $e) {
                echo '❌ Error loading composer: ' . htmlspecialchars($e->getMessage()) . '<br>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
        } else {
            echo '❌ Cannot test - vendor/autoload.php not found<br>';
        }
        echo '</div>';

        // Test 6: Database connection test
        echo '<div class="test">';
        echo '<strong>Database Connection Test:</strong><br>';
        
        if (file_exists(__DIR__ . '/../.env')) {
            // Parse .env for DB credentials
            $envContent = file_get_contents(__DIR__ . '/../.env');
            $dbHost = $dbUser = $dbPass = $dbName = '';
            
            $lines = explode("\n", $envContent);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'database.default.') === 0 && strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    if ($key === 'database.default.hostname') $dbHost = $value;
                    elseif ($key === 'database.default.database') $dbName = $value;
                    elseif ($key === 'database.default.username') $dbUser = $value;
                    elseif ($key === 'database.default.password') $dbPass = $value;
                }
            }
            
            echo 'Host: <code>' . htmlspecialchars($dbHost) . '</code><br>';
            echo 'Database: <code>' . htmlspecialchars($dbName) . '</code><br>';
            echo 'Username: <code>' . htmlspecialchars($dbUser) . '</code><br>';
            echo 'Password: <code>' . (empty($dbPass) ? '(empty)' : '***SET***') . '</code><br><br>';
            
            if (!empty($dbHost) && !empty($dbName) && !empty($dbUser)) {
                try {
                    $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
                    if ($conn->connect_error) {
                        echo '❌ Connection FAILED: ' . htmlspecialchars($conn->connect_error) . '<br>';
                    } else {
                        echo '✅ Database connection SUCCESSFUL<br>';
                        echo 'Server info: ' . htmlspecialchars($conn->host_info) . '<br>';
                        
                        // Count students
                        $result = $conn->query("SELECT COUNT(*) as total FROM students");
                        if ($result) {
                            $count = $result->fetch_assoc();
                            echo 'Total students: <strong>' . $count['total'] . '</strong><br>';
                        }
                        
                        $conn->close();
                    }
                } catch (Exception $e) {
                    echo '❌ Connection error: ' . htmlspecialchars($e->getMessage()) . '<br>';
                }
            } else {
                echo '⚠️ Cannot test - missing database credentials in .env<br>';
            }
        } else {
            echo '⚠️ Cannot test - .env file not found<br>';
        }
        echo '</div>';

        // Summary
        echo '<div style="margin-top: 30px; padding: 20px; background: #007bff; color: white; border-radius: 5px;">';
        echo '<h2 style="color: white; margin-top: 0;">📊 Summary</h2>';
        echo '<p><strong>Server Path:</strong> ' . htmlspecialchars(__DIR__) . '</p>';
        echo '<p><strong>App Root:</strong> ' . htmlspecialchars(dirname(__DIR__)) . '</p>';
        echo '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '</div>';

        echo '<div style="margin-top: 20px; padding: 15px; background: #dc3545; color: white; border-radius: 5px;">';
        echo '<strong>⚠️ DELETE THIS FILE AFTER USE!</strong> (simple-check.php)<br>';
        echo 'Contains sensitive information about your server configuration.';
        echo '</div>';
        ?>
    </div>
</body>
</html>
