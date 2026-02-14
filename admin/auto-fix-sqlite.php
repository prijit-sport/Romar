<?php
/**
 * Auto-Fix SQLite to MySQL Converter
 * แก้ไขไฟล์ทั้งหมดจาก SQLite เป็น MySQL อัตโนมัติ
 */

set_time_limit(300); // 5 นาที

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Auto-Fix SQLite → MySQL</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; font-size: 0.9em; }
th { background: #f8f9fa; font-weight: 600; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #e83e8c; font-size: 0.85em; }
.progress { background: #e9ecef; border-radius: 4px; height: 30px; overflow: hidden; margin: 10px 0; }
.progress-bar { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; line-height: 30px; color: white; text-align: center; font-weight: 600; transition: width 0.3s; }
.btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; font-weight: 500; }
.btn:hover { background: #5568d3; }
.log { max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.85em; }
.log-item { margin: 5px 0; }
</style></head><body>";

echo "<h1>🔧 Auto-Fix SQLite → MySQL</h1>";

// การแทนที่แบบ Simple String Replace
$simple_replacements = [
    // SQLite Types
    'SQLITE3_ASSOC' => 'MYSQLI_ASSOC',
    'SQLITE3_NUM' => 'MYSQLI_NUM',
    'SQLITE3_INTEGER' => 'MYSQLI_ASSOC', // ใช้ตัวเดียวกันเพราะ MySQL ไม่มี type แยก
    'SQLITE3_TEXT' => 'MYSQLI_ASSOC',
    
    // Methods
    '->fetchArray(SQLITE3_ASSOC)' => '->fetch_assoc()',
    '->fetchArray(SQLITE3_NUM)' => '->fetch_array(MYSQLI_NUM)',
    '->fetchArray(MYSQLI_ASSOC)' => '->fetch_assoc()', // จากที่แทนที่ไปแล้ว
    
    // SQL Functions
    "datetime('now')" => 'NOW()',
    "date('now')" => 'CURDATE()',
    "time('now')" => 'CURTIME()',
    
    // Variable patterns
    'SQLITE3' => 'MYSQLI',
];

// การแทนที่แบบ Regex
$regex_patterns = [
    // bindValue → bind_param (integer)
    [
        'pattern' => '/\$(\w+)->bindValue\((\d+),\s*\$(\w+),\s*SQLITE3_INTEGER\);/',
        'replacement' => '$stmt->bind_param(\'i\', $$3);',
        'description' => 'bindValue integer → bind_param'
    ],
    // bindValue → bind_param (text)
    [
        'pattern' => '/\$(\w+)->bindValue\((\d+),\s*\$(\w+),\s*SQLITE3_TEXT\);/',
        'replacement' => '$stmt->bind_param(\'s\', $$3);',
        'description' => 'bindValue text → bind_param'
    ],
];

// โฟลเดอร์ที่ต้องแก้ไข (ยกเว้นไฟล์หลักที่แก้แล้ว)
$directories = [
    '../admin/',
    '../modules/',
    '../includes/', // เผื่อมีไฟล์อื่น
];

$exclude_files = [
    'check-compatibility.php',
    'migrate-sqlite-to-mysql.php',
    'add-facilities-column.php',
    'fix-facilities.php',
    'auto-fix-sqlite.php', // ไฟล์นี้เอง
];

echo "<div class='info'>";
echo "<h2>📁 กำลังสแกนและแก้ไขไฟล์...</h2>";
echo "</div>";

echo "<div class='log' id='log'>";

$total_files = 0;
$fixed_files = 0;
$skipped_files = 0;
$errors = [];

function logMessage($message, $type = 'info') {
    $colors = [
        'info' => '#00ff00',
        'success' => '#00ff00',
        'warning' => '#ffff00',
        'error' => '#ff0000',
    ];
    $color = $colors[$type] ?? '#00ff00';
    echo "<div class='log-item' style='color: $color;'>" . htmlspecialchars($message) . "</div>";
    flush();
    ob_flush();
}

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        logMessage("⚠️ ไม่พบโฟลเดอร์: $dir", 'warning');
        continue;
    }
    
    logMessage("📂 สแกนโฟลเดอร์: $dir", 'info');
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        
        $filepath = $file->getPathname();
        $filename = $file->getFilename();
        
        // ข้ามไฟล์ที่ไม่ต้องแก้
        if (in_array($filename, $exclude_files)) {
            logMessage("⏭️ ข้าม: $filename (ไฟล์ระบบ)", 'warning');
            $skipped_files++;
            continue;
        }
        
        $total_files++;
        
        // อ่านไฟล์
        $content = file_get_contents($filepath);
        $original_content = $content;
        
        // เช็คว่ามี SQLite code หรือไม่
        if (stripos($content, 'SQLite') === false && 
            stripos($content, 'SQLITE3') === false &&
            stripos($content, 'datetime(\'now\')') === false) {
            logMessage("⏭️ ข้าม: $filename (ไม่มี SQLite code)", 'warning');
            $skipped_files++;
            continue;
        }
        
        logMessage("🔧 กำลังแก้ไข: $filename", 'info');
        
        // สำรองไฟล์เดิม
        $backup_path = $filepath . '.backup';
        if (!file_exists($backup_path)) {
            file_put_contents($backup_path, $original_content);
            logMessage("  💾 สำรองเป็น: {$filename}.backup", 'info');
        }
        
        // แทนที่แบบ Simple
        $changes = 0;
        foreach ($simple_replacements as $search => $replace) {
            $new_content = str_replace($search, $replace, $content);
            if ($new_content !== $content) {
                $count = substr_count($content, $search);
                $changes += $count;
                logMessage("  ✓ แทนที่ '$search' → '$replace' ($count ครั้ง)", 'success');
                $content = $new_content;
            }
        }
        
        // แทนที่แบบ Regex
        foreach ($regex_patterns as $pattern_info) {
            $new_content = preg_replace(
                $pattern_info['pattern'], 
                $pattern_info['replacement'], 
                $content,
                -1,
                $count
            );
            if ($count > 0) {
                $changes += $count;
                logMessage("  ✓ {$pattern_info['description']} ($count ครั้ง)", 'success');
                $content = $new_content;
            }
        }
        
        // บันทึกไฟล์
        if ($content !== $original_content) {
            if (file_put_contents($filepath, $content)) {
                $fixed_files++;
                logMessage("  ✅ แก้ไขสำเร็จ: $changes การเปลี่ยนแปลง", 'success');
            } else {
                $errors[] = "ไม่สามารถบันทึกไฟล์: $filepath";
                logMessage("  ❌ ไม่สามารถบันทึก: $filename", 'error');
            }
        } else {
            logMessage("  ⏭️ ไม่มีการเปลี่ยนแปลง", 'warning');
            $skipped_files++;
        }
    }
}

echo "</div>";

// สรุปผล
echo "<div class='success'>";
echo "<h2>📊 สรุปผลการแก้ไข</h2>";
echo "<table>";
echo "<tr><th>รายการ</th><th>จำนวน</th></tr>";
echo "<tr><td>ไฟล์ทั้งหมด</td><td><strong>$total_files</strong></td></tr>";
echo "<tr><td>แก้ไขสำเร็จ</td><td><strong style='color: #28a745;'>$fixed_files</strong></td></tr>";
echo "<tr><td>ข้าม/ไม่ต้องแก้</td><td><strong>$skipped_files</strong></td></tr>";
echo "<tr><td>Error</td><td><strong style='color: #dc3545;'>" . count($errors) . "</strong></td></tr>";
echo "</table>";

if (!empty($errors)) {
    echo "<h3 style='color: #dc3545;'>❌ Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

// แสดง Progress
$progress = $total_files > 0 ? round(($fixed_files / $total_files) * 100) : 0;
echo "<div class='progress' style='margin-top: 20px;'>";
echo "<div class='progress-bar' style='width: {$progress}%;'>{$progress}% เสร็จสิ้น</div>";
echo "</div>";

echo "</div>";

// คำแนะนำถัดไป
echo "<div class='info'>";
echo "<h2>📝 ไฟล์ Backup</h2>";
echo "<p>ไฟล์เดิมถูกสำรองไว้แล้ว (*.backup)</p>";
echo "<p>ถ้าต้องการกู้คืน:</p>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";
echo "1. ค้นหาไฟล์ *.backup\n";
echo "2. เปลี่ยนชื่อ file.php.backup → file.php\n";
echo "3. ทับไฟล์เดิม";
echo "</pre>";
echo "</div>";

// ปุ่มดำเนินการ
echo "<div class='success' style='text-align: center; padding: 30px;'>";
echo "<h2>✅ เสร็จสิ้น!</h2>";
echo "<p style='font-size: 1.1em;'>แก้ไขไฟล์เรียบร้อยแล้ว กรุณาทดสอบระบบ</p>";
echo "<div>";
echo "<a href='dashboard.php' class='btn'>🏠 ทดสอบ Dashboard</a>";
echo "<a href='room-booking.php' class='btn'>📅 ทดสอบจองห้อง</a>";
echo "<a href='my-bookings.php' class='btn'>📋 ทดสอบรายการจอง</a>";
echo "<a href='check-compatibility.php' class='btn'>🔍 ตรวจสอบอีกครั้ง</a>";
echo "</div>";
echo "</div>";

// Warning
echo "<div class='warning'>";
echo "<h3>⚠️ สิ่งที่ต้องทำต่อ:</h3>";
echo "<ol>";
echo "<li>ทดสอบทุกฟังก์ชันในระบบ</li>";
echo "<li>ตรวจสอบว่าทุกอย่างทำงานถูกต้อง</li>";
echo "<li>ถ้ามี Error ให้ถ่ายภาพส่งมา</li>";
echo "<li>ถ้าทุกอย่าง OK ลบไฟล์ *.backup ได้</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>