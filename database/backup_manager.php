<?php
/**
 * Database Backup Manager
 * ✅ Manages database backups: cleanup, archival, retention
 * 
 * Usage:
 *   - php backup_manager.php list              // List all backups
 *   - php backup_manager.php cleanup           // Archive old, keep latest 3
 *   - php backup_manager.php archive           // Move outdated to archive/
 *   - php backup_manager.php create            // Create new backup
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';

class BackupManager {
    private string $backupDir;
    private string $archiveDir;
    private string $logsDir;
    private int $maxActiveBackups = 3;
    private int $archiveAfterDays = 7;
    
    public function __construct() {
        $this->backupDir = __DIR__ . '/backups';
        $this->archiveDir = __DIR__ . '/archive';
        $this->logsDir = __DIR__ . '/logs';
        
        $this->ensureDirectories();
    }
    
    private function ensureDirectories() {
        foreach ([$this->backupDir, $this->archiveDir, $this->logsDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * List all backups (active + archived)
     */
    public function listBackups(bool $showArchived = true) {
        $backups = [];
        
        // List active backups
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir . '/*.sql') as $file) {
                if (basename($file) === 'schema_mysql.sql') continue; // Skip schema
                $backups[] = [
                    'file' => basename($file),
                    'size' => filesize($file),
                    'date' => filemtime($file),
                    'location' => 'active'
                ];
            }
        }
        
        // List archived backups
        if ($showArchived && is_dir($this->archiveDir)) {
            foreach (glob($this->archiveDir . '/*.sql') as $file) {
                $backups[] = [
                    'file' => basename($file),
                    'size' => filesize($file),
                    'date' => filemtime($file),
                    'location' => 'archive'
                ];
            }
        }
        
        // Sort by date (newest first)
        usort($backups, fn($a, $b) => $b['date'] - $a['date']);
        return $backups;
    }
    
    /**
     * Display backups in table format
     */
    public function displayBackups() {
        $backups = $this->listBackups();
        
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "📊 DATABASE BACKUPS STATUS\n";
        echo str_repeat("=", 100) . "\n";
        
        printf("%-50s | %-15s | %-15s | %s\n", 
            "FILENAME", "SIZE", "LOCATION", "DATE");
        echo str_repeat("-", 100) . "\n";
        
        if (empty($backups)) {
            echo "❌ No backups found\n";
            return;
        }
        
        foreach ($backups as $i => $backup) {
            $sizeStr = $this->formatSize($backup['size']);
            $dateStr = date('Y-m-d H:i', $backup['date']);
            $location = strtoupper($backup['location']);
            
            $marker = $i === 0 ? '⭐ ' : '   '; // Mark latest
            printf("%s%-47s | %-15s | %-15s | %s\n",
                $marker,
                $backup['file'],
                $sizeStr,
                $location,
                $dateStr
            );
        }
        
        echo str_repeat("=", 100) . "\n";
        echo "✅ Total: " . count($backups) . " backups\n";
    }
    
    /**
     * Archive old backups (keep latest N active, move rest)
     */
    public function archiveOldBackups() {
        $backups = $this->listBackups(false); // Only active backups
        
        if (count($backups) <= $this->maxActiveBackups) {
            echo "✅ All backups are recent (keeping {$this->maxActiveBackups}). No archival needed.\n";
            return;
        }
        
        $toArchive = array_slice($backups, $this->maxActiveBackups);
        $archivedCount = 0;
        
        foreach ($toArchive as $backup) {
            $source = $this->backupDir . '/' . $backup['file'];
            $dest = $this->archiveDir . '/' . $backup['file'];
            
            if (rename($source, $dest)) {
                echo "📦 Archived: {$backup['file']}\n";
                $archivedCount++;
            }
        }
        
        if ($archivedCount > 0) {
            $this->log("Archived $archivedCount old backups");
            echo "✅ Archived $archivedCount backup(s)\n";
        }
    }
    
    /**
     * Create new database backup
     */
    public function createBackup() {
        $timestamp = date('YmdHis');
        $filename = "romar_dormitory_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . '/' . $filename;
        
        // Use mysqldump if available
        $dumpCommand = $this->getMysqldumpCommand($filepath);
        if ($dumpCommand) {
            exec($dumpCommand, $output, $returnCode);
            if ($returnCode === 0 && file_exists($filepath)) {
                $size = filesize($filepath);
                echo "✅ Backup created: $filename ({$this->formatSize($size)})\n";
                $this->log("Backup created: $filename");
                return true;
            }
        }
        
        // Fallback: PHP-based backup
        echo "⚠️  mysqldump not available. Using PHP backup method...\n";
        return $this->createPhpBackup($filepath);
    }
    
    /**
     * Get mysqldump command (Windows/Linux compatible)
     */
    private function getMysqldumpCommand(string $filepath): ?string {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $db = defined('DB_NAME') ? DB_NAME : 'romar_dormitory';
        
        // Try to find mysqldump
        $possiblePaths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'mysqldump'
        ];
        
        $mysqldump = null;
        foreach ($possiblePaths as $path) {
            if (is_executable($path) || file_exists($path)) {
                $mysqldump = $path;
                break;
            }
        }
        
        if (!$mysqldump) return null;
        
        // Build command
        $passArg = $pass ? "-p{$pass}" : '';
        return "\"$mysqldump\" -h{$host} -u{$user} {$passArg} {$db} > \"{$filepath}\" 2>&1";
    }
    
    /**
     * Fallback: PHP-based backup (slow but reliable)
     */
    private function createPhpBackup(string $filepath): bool {
        try {
            $db = getDB();
            $tables = [];
            
            // Get all tables
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            
            $sql = "-- Romar Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- MySQL Host: " . DB_HOST . "\n";
            $sql .= "-- Database: " . DB_NAME . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Dump each table
            foreach ($tables as $table) {
                $sql .= $this->dumpTable($db, $table);
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            if (file_put_contents($filepath, $sql)) {
                $size = filesize($filepath);
                echo "✅ PHP Backup created: " . basename($filepath) . " ({$this->formatSize($size)})\n";
                $this->log("PHP Backup created: " . basename($filepath));
                return true;
            }
        } catch (Exception $e) {
            echo "❌ Backup failed: " . $e->getMessage() . "\n";
            $this->log("Backup failed: " . $e->getMessage(), 'error');
            return false;
        }
        
        return false;
    }
    
    /**
     * Dump single table
     */
    private function dumpTable(mysqli $db, string $table): string {
        $sql = "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get CREATE TABLE
        $createResult = $db->query("SHOW CREATE TABLE `$table`");
        $createRow = $createResult->fetch_row();
        $sql .= $createRow[1] . ";\n\n";
        
        // Get data
        $dataResult = $db->query("SELECT * FROM `$table`");
        $numRows = $dataResult->num_rows;
        
        if ($numRows > 0) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $rows = [];
            while ($row = $dataResult->fetch_assoc()) {
                $values = [];
                foreach ($row as $val) {
                    $values[] = $val === null ? 'NULL' : $db->real_escape_string($val);
                }
                $rows[] = "('" . implode("','", $values) . "')";
            }
            $sql .= implode(",\n", $rows) . ";\n";
        }
        
        $sql .= "\n";
        return $sql;
    }
    
    /**
     * Clean up: Archive old + delete very old archived files
     */
    public function cleanup() {
        echo "🧹 Running backup cleanup...\n";
        
        // Step 1: Archive old active backups
        $this->archiveOldBackups();
        
        // Step 2: Delete archived files older than N days
        $this->deleteOldArchives();
        
        echo "✅ Cleanup completed\n";
    }
    
    /**
     * Delete archived files older than archiveAfterDays
     */
    private function deleteOldArchives() {
        if (!is_dir($this->archiveDir)) return;
        
        $cutoffDate = time() - ($this->archiveAfterDays * 86400);
        $deletedCount = 0;
        
        foreach (glob($this->archiveDir . '/*.sql') as $file) {
            if (filemtime($file) < $cutoffDate) {
                if (unlink($file)) {
                    echo "🗑️  Deleted old archive: " . basename($file) . "\n";
                    $deletedCount++;
                }
            }
        }
        
        if ($deletedCount > 0) {
            $this->log("Deleted $deletedCount old archived backups");
        }
    }
    
    /**
     * Log action
     */
    private function log(string $message, string $level = 'info'): void {
        $logFile = $this->logsDir . '/backup_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Format file size
     */
    private function formatSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}

// CLI Handler
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'phpdbg') {
    die("❌ This script must be run from command line\n");
}

$manager = new BackupManager();
$action = $argv[1] ?? 'list';

switch ($action) {
    case 'list':
        $manager->displayBackups();
        break;
    case 'archive':
        $manager->archiveOldBackups();
        break;
    case 'cleanup':
        $manager->cleanup();
        break;
    case 'create':
        $manager->createBackup();
        break;
    default:
        echo "Usage: php backup_manager.php [list|archive|cleanup|create]\n";
        echo "  list    - Show all backups (active + archived)\n";
        echo "  archive - Move old backups to archive/\n";
        echo "  cleanup - Full cleanup: archive + delete old\n";
        echo "  create  - Create new backup now\n";
}
?>
