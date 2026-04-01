<?php
/**
 * Database Backup Helper Functions
 * ✅ Helper functions for backup management and scheduling
 */

if (!function_exists('create_automatic_backup')) {
    /**
     * Create automatic backup (scheduled via cron or task scheduler)
     * Call this function periodically to maintain backups
     */
    function create_automatic_backup() {
        require_once __DIR__ . '/../database/backup_manager.php';
        
        $manager = new BackupManager();
        return $manager->createBackup();
    }
}

if (!function_exists('cleanup_old_backups')) {
    /**
     * Cleanup old backups (archive and delete based on retention policy)
     * Call weekly or monthly
     */
    function cleanup_old_backups() {
        require_once __DIR__ . '/../database/backup_manager.php';
        
        $manager = new BackupManager();
        $manager->cleanup();
        return true;
    }
}

if (!function_exists('get_latest_backup_info')) {
    /**
     * Get info about latest backup
     */
    function get_latest_backup_info() {
        $backupDir = __DIR__ . '/../database/backups';
        
        if (!is_dir($backupDir)) {
            return null;
        }
        
        $files = glob($backupDir . '/*.sql');
        if (empty($files)) {
            return null;
        }
        
        // Get most recent
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $latest = $files[0];
        
        return [
            'filename' => basename($latest),
            'size' => filesize($latest),
            'date' => filemtime($latest),
            'path' => $latest,
            'size_human' => format_bytes(filesize($latest))
        ];
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Format bytes to human readable size
     */
    function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('restore_from_backup')) {
    /**
     * Restore database from backup file
     * ⚠️ BE CAREFUL - This will overwrite current database!
     * 
     * @param string $backupFile Path to SQL file
     * @return bool Success status
     */
    function restore_from_backup($backupFile) {
        if (!file_exists($backupFile)) {
            error_log("Backup file not found: $backupFile");
            return false;
        }
        
        try {
            $db = getDB();
            
            // Read backup file
            $sql = file_get_contents($backupFile);
            
            if (!$sql) {
                error_log("Failed to read backup file");
                return false;
            }
            
            // Split by GO or ; to execute multiple statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s) && !str_starts_with($s, '--')
            );
            
            foreach ($statements as $statement) {
                if ($statement && !$db->query($statement)) {
                    error_log("Restore error: " . $db->error);
                    return false;
                }
            }
            
            security_audit_log(
                getCurrentUserId(),
                'database_restore',
                'Database',
                'Restored from: ' . basename($backupFile)
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Restore failed: " . $e->getMessage());
            return false;
        }
    }
}

?>
