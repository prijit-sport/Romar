<?php
/**
 * Create .htaccess Permanently
 * สร้างไฟล์ .htaccess ถาวร
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>สร้าง .htaccess</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.path { font-family: monospace; background: #f8f9fa; padding: 5px 10px; border-radius: 4px; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>📄 สร้างไฟล์ .htaccess</h1>";

$uploads_dir = __DIR__ . '/uploads';
$htaccess_path = $uploads_dir . '/.htaccess';

// เนื้อหาไฟล์ .htaccess
$htaccess_content = "Options -Indexes\n";
$htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar)$\">\n";
$htaccess_content .= "    Order Allow,Deny\n";
$htaccess_content .= "    Allow from all\n";
$htaccess_content .= "</FilesMatch>\n";

echo "<h2>ขั้นตอนที่ 1: ตรวจสอบโฟลเดอร์</h2>";

if (!file_exists($uploads_dir)) {
    echo "<p class='error'>❌ ไม่พบโฟลเดอร์: {$uploads_dir}</p>";
    echo "<p>กรุณาสร้างโฟลเดอร์ก่อน</p>";
} else {
    echo "<p class='success'>✅ พบโฟลเดอร์: {$uploads_dir}</p>";
    
    echo "<h2>ขั้นตอนที่ 2: สร้างไฟล์ .htaccess</h2>";
    
    if (file_exists($htaccess_path)) {
        echo "<p class='error'>⚠️ ไฟล์ .htaccess มีอยู่แล้ว</p>";
        echo "<p>ขนาดไฟล์: " . filesize($htaccess_path) . " bytes</p>";
        
        // แสดงเนื้อหา
        echo "<h3>เนื้อหาปัจจุบัน:</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
        
        if (isset($_GET['force'])) {
            // ลบ Read-only ถ้ามี
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('attrib -r "' . $htaccess_path . '"');
            } else {
                chmod($htaccess_path, 0644);
            }
            
            // เขียนทับ
            if (file_put_contents($htaccess_path, $htaccess_content)) {
                echo "<p class='success'>✅ เขียนทับไฟล์สำเร็จ!</p>";
                
                // ตั้งเป็น Read-only
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    exec('attrib +r "' . $htaccess_path . '"');
                } else {
                    chmod($htaccess_path, 0444);
                }
                
                echo "<p class='success'>✅ ตั้งเป็น Read-only แล้ว</p>";
            } else {
                echo "<p class='error'>❌ ไม่สามารถเขียนทับไฟล์</p>";
            }
        } else {
            echo "<p><a href='?force=1' style='padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px;'>🔄 เขียนทับไฟล์</a></p>";
        }
        
    } else {
        // สร้างไฟล์ใหม่
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            echo "<p class='success'>✅ สร้างไฟล์ .htaccess สำเร็จ!</p>";
            echo "<p>ตำแหน่ง: <span class='path'>{$htaccess_path}</span></p>";
            echo "<p>ขนาด: " . filesize($htaccess_path) . " bytes</p>";
            
            echo "<h3>เนื้อหาไฟล์:</h3>";
            echo "<pre>" . htmlspecialchars($htaccess_content) . "</pre>";
            
            // ตั้งเป็น Read-only
            echo "<h2>ขั้นตอนที่ 3: ตั้งเป็น Read-only</h2>";
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('attrib +r "' . $htaccess_path . '"', $output, $return);
                if ($return === 0) {
                    echo "<p class='success'>✅ ตั้งเป็น Read-only สำเร็จ (Windows)</p>";
                } else {
                    echo "<p class='error'>⚠️ ไม่สามารถตั้งเป็น Read-only อัตโนมัติ</p>";
                    echo "<p>กรุณาตั้งด้วยมือ: คลิกขวา → Properties → ติ๊ก Read-only</p>";
                }
            } else {
                if (chmod($htaccess_path, 0444)) {
                    echo "<p class='success'>✅ ตั้งเป็น Read-only สำเร็จ (Linux/Mac)</p>";
                }
            }
            
            echo "<h2>✅ สำเร็จ!</h2>";
            echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;'>";
            echo "<p style='color: #155724;'><strong>ไฟล์ .htaccess ถูกสร้างและตั้งเป็น Read-only แล้ว</strong></p>";
            echo "<p style='color: #155724;'>ไฟล์จะไม่ถูกลบอีกต่อไป!</p>";
            echo "</div>";
            
        } else {
            echo "<p class='error'>❌ ไม่สามารถสร้างไฟล์</p>";
            echo "<p>กรุณาตรวจสอบ Permission ของโฟลเดอร์</p>";
        }
    }
}

echo "<h2>💡 คำแนะนำ</h2>";
echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px;'>";
echo "<h3>ถ้าไฟล์ยังหายอยู่:</h3>";
echo "<ol>";
echo "<li>ตรวจสอบ Windows Defender / Antivirus</li>";
echo "<li>เพิ่ม Exclusion: C:\\xampp\\htdocs\\ROMARDORMITORY-MANAGEMENT\\uploads\\</li>";
echo "<li>ตรวจสอบ Apache config (AllowOverride All)</li>";
echo "</ol>";

echo "<h3>วิธีสร้างด้วยมือ:</h3>";
echo "<ol>";
echo "<li>เปิด Notepad</li>";
echo "<li>Copy โค้ดด้านบน</li>";
echo "<li>Save As → All Files → ตั้งชื่อ: .htaccess</li>";
echo "<li>Save ที่: uploads/</li>";
echo "<li>คลิกขวา → Properties → ติ๊ก Read-only</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>
