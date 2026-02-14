<?php
/**
 * Check SQLite to MySQL Compatibility
 * ตรวจสอบไฟล์ที่ยังใช้ SQLite syntax
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Check SQLite Compatibility</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #e83e8c; font-size: 0.9em; }
.file-list { max-height: 400px; overflow-y: auto; background: white; padding: 15px; border-radius: 8px; }
.btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; font-weight: 500; }
.btn:hover { background: #5568d3; }
</style></head><body>";

echo "<h1>🔍 ตรวจสอบความเข้ากันได้ SQLite → MySQL</h1>";

// คำที่ต้องตรวจสอบ
$sqlite_patterns = [
    'SQLite3',
    'fetchArray',
    'SQLITE3_ASSOC',
    'SQLITE3_NUM',
    'SQLITE3_INTEGER',
    'SQLITE3_TEXT',
    'sqlite_',
    'new SQLite3',
    '->query(',
    "datetime('now')",
    "date('now')",
    "time('now')",
];

$mysql_replacements = [
    'mysqli' => 'SQLite3',
    'fetch_assoc()' => 'fetchArray(SQLITE3_ASSOC)',
    'MYSQLI_ASSOC' => 'SQLITE3_ASSOC',
    'MYSQLI_NUM' => 'SQLITE3_NUM',
    'bind_param' => 'bindValue',
    'NOW()' => "datetime('now')",
    'CURDATE()' => "date('now')",
    'CURTIME()' => "time('now')",
];

// โฟลเดอร์ที่ต้องตรวจสอบ
$directories = [
    '../includes/',
    '../admin/',
    '../modules/',
    '../config/',
];

$issues = [];
$total_files = 0;
$files_with_issues = 0;

echo "<div class='info'>";
echo "<h2>📁 กำลังสแกนไฟล์...</h2>";
echo "</div>";

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $total_files++;
            $filepath = $file->getPathname();
            $content = file_get_contents($filepath);
            
            $file_issues = [];
            
            // ตรวจสอบ SQLite patterns
            foreach ($sqlite_patterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    // นับจำนวนครั้งที่เจอ
                    $count = substr_count(strtolower($content), strtolower($pattern));
                    $file_issues[] = [
                        'pattern' => $pattern,
                        'count' => $count
                    ];
                }
            }
            
            if (!empty($file_issues)) {
                $files_with_issues++;
                $issues[$filepath] = $file_issues;
            }
        }
    }
}

// แสดงผลสรุป
echo "<div class='warning'>";
echo "<h2>📊 สรุปผลการตรวจสอบ</h2>";
echo "<table>";
echo "<tr><th>รายการ</th><th>จำนวน</th></tr>";
echo "<tr><td>ไฟล์ทั้งหมด</td><td><strong>$total_files</strong></td></tr>";
echo "<tr><td>ไฟล์ที่ต้องแก้ไข</td><td><strong style='color: #dc3545;'>$files_with_issues</strong></td></tr>";
echo "<tr><td>ไฟล์ที่ OK</td><td><strong style='color: #28a745;'>" . ($total_files - $files_with_issues) . "</strong></td></tr>";
echo "</table>";
echo "</div>";

// แสดงรายละเอียดไฟล์ที่มีปัญหา
if (!empty($issues)) {
    echo "<div class='error'>";
    echo "<h2>❌ ไฟล์ที่ต้องแก้ไข ({$files_with_issues} ไฟล์)</h2>";
    
    echo "<table>";
    echo "<tr><th>ไฟล์</th><th>ปัญหาที่พบ</th><th>จำนวน</th></tr>";
    
    foreach ($issues as $filepath => $file_issues) {
        $relative_path = str_replace('../', '', $filepath);
        $row_count = count($file_issues);
        
        echo "<tr>";
        echo "<td rowspan='$row_count'><code>" . htmlspecialchars($relative_path) . "</code></td>";
        
        $first = true;
        foreach ($file_issues as $issue) {
            if (!$first) echo "<tr>";
            echo "<td><code>" . htmlspecialchars($issue['pattern']) . "</code></td>";
            echo "<td><strong>" . $issue['count'] . "</strong> ครั้ง</td>";
            echo "</tr>";
            $first = false;
        }
    }
    
    echo "</table>";
    echo "</div>";
    
    // แสดงไฟล์ที่สำคัญที่ต้องแก้ไขก่อน
    echo "<div class='warning'>";
    echo "<h2>⚠️ ไฟล์สำคัญที่ต้องแก้ไขก่อน</h2>";
    echo "<ol>";
    
    $critical_files = [
        'includes/functions.php' => 'ฟังก์ชันหลักของระบบ',
        'config/database.php' => 'การเชื่อมต่อฐานข้อมูล',
        'admin/dashboard.php' => 'หน้า Dashboard',
    ];
    
    foreach ($critical_files as $file => $description) {
        $full_path = '../' . $file;
        if (isset($issues[$full_path])) {
            echo "<li>";
            echo "<strong><code>$file</code></strong> - $description";
            echo "<ul>";
            foreach ($issues[$full_path] as $issue) {
                echo "<li><code>" . htmlspecialchars($issue['pattern']) . "</code>: " . $issue['count'] . " ครั้ง</li>";
            }
            echo "</ul>";
            echo "</li>";
        } else {
            echo "<li><strong><code>$file</code></strong> - $description <span style='color: #28a745;'>✅ OK</span></li>";
        }
    }
    
    echo "</ol>";
    echo "</div>";
}

// คำแนะนำการแก้ไข
echo "<div class='info'>";
echo "<h2>📝 วิธีแก้ไข</h2>";
echo "<h3>ขั้นตอนที่ 1: แทนที่ไฟล์สำคัญ</h3>";
echo "<ol>";
echo "<li>ดาวน์โหลด <code>database-mysql.php</code> → เปลี่ยนชื่อเป็น <code>database.php</code></li>";
echo "<li>แทนที่: <code>config/database.php</code></li>";
echo "<li>ดาวน์โหลด <code>functions-mysql.php</code> → เปลี่ยนชื่อเป็น <code>functions.php</code></li>";
echo "<li>แทนที่: <code>includes/functions.php</code></li>";
echo "</ol>";

echo "<h3>ขั้นตอนที่ 2: แก้ไขไฟล์อื่นๆ</h3>";
echo "<p>เปลี่ยน SQLite syntax เป็น MySQL syntax:</p>";

echo "<table>";
echo "<tr><th>SQLite (เดิม)</th><th>MySQL (ใหม่)</th></tr>";
echo "<tr><td><code>fetchArray(SQLITE3_ASSOC)</code></td><td><code>fetch_assoc()</code></td></tr>";
echo "<tr><td><code>fetchArray(SQLITE3_NUM)</code></td><td><code>fetch_row()</code></td></tr>";
echo "<tr><td><code>bindValue(1, \$val, SQLITE3_INTEGER)</code></td><td><code>bind_param('i', \$val)</code></td></tr>";
echo "<tr><td><code>bindValue(1, \$val, SQLITE3_TEXT)</code></td><td><code>bind_param('s', \$val)</code></td></tr>";
echo "<tr><td><code>datetime('now')</code></td><td><code>NOW()</code></td></tr>";
echo "<tr><td><code>date('now')</code></td><td><code>CURDATE()</code></td></tr>";
echo "<tr><td><code>time('now')</code></td><td><code>CURTIME()</code></td></tr>";
echo "<tr><td><code>new SQLite3(\$db)</code></td><td><code>new mysqli(\$host, \$user, \$pass, \$db)</code></td></tr>";
echo "</table>";

echo "</div>";

// ปุ่มดำเนินการ
echo "<div class='success' style='text-align: center; padding: 30px;'>";
echo "<h2>✅ ขั้นตอนถัดไป</h2>";
echo "<p>แก้ไขไฟล์ตามคำแนะนำด้านบน แล้วทดสอบระบบใหม่</p>";
echo "<div>";
echo "<a href='dashboard.php' class='btn'>🏠 ไปหน้า Dashboard</a>";
echo "<a href='migrate-sqlite-to-mysql.php' class='btn'>🔄 Migration Script</a>";
echo "</div>";
echo "</div>";

echo "</body></html>";
?>