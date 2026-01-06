<?php
/**
 * Setup Upload Folders
 * สคริปต์สร้างโฟลเดอร์สำหรับอัปโหลดไฟล์
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Setup Upload Folders</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🗂️ Setup Upload Folders</h1>";

$base_dir = __DIR__;
$folders = [
    'uploads',
    'uploads/documents',
    'uploads/images',
    'uploads/temp'
];

echo "<h2>กำลังสร้างโฟลเดอร์...</h2>";

foreach ($folders as $folder) {
    $full_path = $base_dir . '/' . $folder;
    
    if (!file_exists($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "<p class='success'>✅ สร้างโฟลเดอร์: {$folder}</p>";
        } else {
            echo "<p class='error'>❌ ไม่สามารถสร้างโฟลเดอร์: {$folder}</p>";
        }
    } else {
        echo "<p>ℹ️ โฟลเดอร์มีอยู่แล้ว: {$folder}</p>";
    }
}

// สร้างไฟล์ .htaccess ป้องกัน directory listing
$htaccess_content = "Options -Indexes\n";
$htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar)$\">\n";
$htaccess_content .= "    Order Allow,Deny\n";
$htaccess_content .= "    Allow from all\n";
$htaccess_content .= "</FilesMatch>\n";

file_put_contents($base_dir . '/uploads/.htaccess', $htaccess_content);
echo "<p class='success'>✅ สร้างไฟล์ .htaccess</p>";

// ตรวจสอบการตั้งค่า PHP
echo "<h2>การตั้งค่า PHP</h2>";

$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

echo "<p><strong>upload_max_filesize:</strong> {$upload_max}</p>";
echo "<p><strong>post_max_size:</strong> {$post_max}</p>";
echo "<p><strong>memory_limit:</strong> {$memory_limit}</p>";

echo "<h3 style='color: green;'>🎉 Setup เสร็จสิ้น!</h3>";
echo "<p>ตอนนี้คุณสามารถอัปโหลดไฟล์ได้แล้ว</p>";

echo "<p style='margin-top: 30px;'>";
echo "<a href='admin/documents.php' class='btn'>📁 ไปหน้าจัดการเอกสาร</a>";
echo "</p>";

echo "</div>";
echo "</body></html>";
?>