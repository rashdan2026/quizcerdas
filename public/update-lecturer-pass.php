<?php
/**
 * Update Lecturer Password - Standalone Version
 * No framework dependencies required.
 * Access: https://kursuscerdas.com/absen/public/update-lecturer-pass.php
 * 
 * Email: hendra@eng.uir.ac.id
 * Password: indahsari
 * 
 * ⚠️ DELETE THIS FILE AFTER USE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Manual .env parser
function parseEnv($path) {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
    return $vars;
}

// Load config
$env = parseEnv(dirname(__DIR__) . '/.env');
$host = $env['database.default.hostname'] ?? 'localhost';
$db   = $env['database.default.database'] ?? '';
$user = $env['database.default.username'] ?? '';
$pass = $env['database.default.password'] ?? '';
$port = $env['database.default.port'] ?? '3306';

// Target
$email   = 'hendra@eng.uir.ac.id';
$newPass = 'indahsari';
$hash    = password_hash($newPass, PASSWORD_BCRYPT);

try {
    // Connect
    $conn = new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    // Update
    $stmt = $conn->prepare("UPDATE lecturers SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hash, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<div style='font-family:sans-serif; padding:20px; background:#d4edda; border:1px solid #c3e6cb; border-radius:5px; max-width:600px; margin:50px auto;'>";
        echo "<h2 style='color:#155724; margin-top:0;'>✅ Password Updated Successfully!</h2>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
        echo "<p><strong>New Password:</strong> " . htmlspecialchars($newPass) . "</p>";
        echo "<hr>";
        echo "<p style='color:#856404; font-weight:bold;'>⚠️ SECURITY WARNING: Delete this file (update-lecturer-pass.php) immediately!</p>";
        echo "</div>";
    } else {
        echo "<div style='font-family:sans-serif; padding:20px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; max-width:600px; margin:50px auto;'>";
        echo "<h2 style='color:#721c24; margin-top:0;'>❌ Update Failed</h2>";
        echo "<p>No lecturer found with email: <code>" . htmlspecialchars($email) . "</code></p>";
        echo "<p>Please check if the email exists in the 'lecturers' table.</p>";
        echo "</div>";
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo "<div style='font-family:sans-serif; padding:20px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; max-width:600px; margin:50px auto;'>";
    echo "<h2 style='color:#721c24; margin-top:0;'>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
