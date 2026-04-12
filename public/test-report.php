<?php
// Simple test file to diagnose the issue
// Access via: https://10.10.10.13/DB2/absen2/public/test-report

echo "<h1>Test Report Page</h1>";
echo "<p>If you see this, PHP is working.</p>";
echo "<hr>";

try {
    echo "<h2>Step 1: Basic PHP</h2>";
    echo "<p>✓ PHP is running</p>";
    
    echo "<h2>Step 2: Database Connection</h2>";
    $db = db_connect();
    echo "<p>✓ Database connected</p>";
    
    echo "<h2>Step 3: Session Check</h2>";
    $dosenId = session('user_id');
    echo "<p>User ID: " . ($dosenId ? $dosenId : 'NOT SET') . "</p>";
    
    if (!$dosenId) {
        echo "<p style='color:red;'>⚠ You need to login first as lecturer</p>";
        echo "<p><a href='/auth'>Login here</a></p>";
    }
    
    echo "<h2>Step 4: Simple Query</h2>";
    $result = $db->table('meetings')->countAllResults();
    echo "<p>Meetings count: $result</p>";
    
    echo "<h2>Step 5: Query with JOIN</h2>";
    $dosenId = session('user_id');
    $meetings = $db->table('meetings m')
        ->select('m.id, m.pertemuan_ke, m.judul, s.kode_mk, s.nama_mk')
        ->join('subjects s', 's.id = m.subject_id')
        ->where('s.dosen_id', $dosenId)
        ->get()
        ->getResultArray();
    
    echo "<p>Meetings for this lecturer: " . count($meetings) . "</p>";
    
    if (!empty($meetings)) {
        echo "<pre>";
        print_r($meetings);
        echo "</pre>";
    }
    
    echo "<hr>";
    echo "<p><a href='/lecturer/report'>Go to actual report</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
