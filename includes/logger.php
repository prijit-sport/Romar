<?php
/**
 * Comprehensive Logging & Monitoring System
 * ✅ Centralized event logging, error tracking, and monitoring
 * 
 * Features:
 *   - Error logging with stack trace
 *   - Event logging (login, logout, modifications)
 *   - Performance monitoring
 *   - Failed login tracking
 *  - API call logging
 *   - Automatic log rotation
 */

class Logger {
    private static $instance = null;
    private $logDir;
    private $maxFileSize = 5242880; // 5 MB
    private $maxFiles = 10;
    
    private function __construct() {
        $this->logDir = __DIR__ . '/../logs';
        if(!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log error with stack trace
     */
    public function logError($message, $severity = 'ERROR', $context = []) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => $severity,
            'message' => $message,
            'file' => $context['file'] ?? 'unknown',
            'line' => $context['line'] ?? 0,
            'ip' => get_remote_addr(),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'debug_trace' => $context['trace'] ?? null
        ];
        
        return $this->writeLog('errors', $data);
    }
    
    /**
     * Log user event (login, logout, action)
     */
    public function logEvent($eventType, $description, $userId = null, $details = []) {
        $userId = $userId ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip' => get_remote_addr(),
            'user_agent' => substr(get_user_agent(), 0, 255),
            'description' => $description,
            'details' => json_encode($details)
        ];
        
        return $this->writeLog('events', $data);
    }
    
    /**
     * Log API call
     */
    public function logApiCall($endpoint, $method, $statusCode, $responseTime, $userId = null) {
        $userId = $userId ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'response_time_ms' => round($responseTime * 1000, 2),
            'user_id' => $userId,
            'ip' => get_remote_addr(),
            'request_size' => $_SERVER['CONTENT_LENGTH'] ?? 0,
            'user_agent' => substr(get_user_agent(), 0, 255)
        ];
        
        return $this->writeLog('api_calls', $data);
    }
    
    /**
     * Log failed login attempt
     */
    public function logFailedLogin($username) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => $username,
            'ip' => get_remote_addr(),
            'user_agent' => substr(get_user_agent(), 0, 255),
            'reason' => 'Invalid credentials'
        ];
        
        return $this->writeLog('failed_logins', $data);
    }
    
    /**
     * Log successful login
     */
    public function logLogin($userId, $username) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'username' => $username,
            'ip' => get_remote_addr(),
            'user_agent' => substr(get_user_agent(), 0, 255)
        ];
        
        return $this->writeLog('logins', $data);
    }
    
    /**
     * Log logout
     */
    public function logLogout($userId) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'ip' => get_remote_addr()
        ];
        
        return $this->writeLog('logouts', $data);
    }
    
    /**
     * Log user action (create, update, delete)
     */
    public function logAction($action, $table, $recordId, $userId = null, $details = []) {
        $userId = $userId ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => strtoupper($action),
            'table' => $table,
            'record_id' => $recordId,
            'user_id' => $userId,
            'ip' => get_remote_addr(),
            'changes' => json_encode($details)
        ];
        
        return $this->writeLog('actions', $data);
    }
    
    /**
     * Log security event (suspicious activity, access denied, etc)
     */
    public function logSecurityEvent($eventType, $description, $severity = 'WARN', $details = []) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'severity' => $severity,
            'description' => $description,
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
            'ip' => get_remote_addr(),
            'user_agent' => substr(get_user_agent(), 0, 255),
            'details' => json_encode($details)
        ];
        
        return $this->writeLog('security', $data);
    }
    
    /**
     * Log performance metric
     */
    public function logPerformance($page, $renderTime, $dbQueries, $memory) {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'page' => $page,
            'render_time_ms' => round($renderTime * 1000, 2),
            'db_queries' => $dbQueries,
            'memory_mb' => round($memory / 1024 / 1024, 2),
            'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
        ];
        
        return $this->writeLog('performance', $data);
    }
    
    /**
     * Write log to file
     * @private
     */
    private function writeLog($logType, $data) {
        $date = date('Y-m-d');
        $filename = "{$logType}_{$date}.json";
        $filepath = $this->logDir . '/' . $filename;
        
        // Rotate if file too large
        if (file_exists($filepath) && filesize($filepath) > $this->maxFileSize) {
            $this->rotateLog($filepath);
        }
        
        // Write as JSON line
        $line = json_encode($data) . "\n";
        
        return file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Rotate log file when too large
     * @private
     */
    private function rotateLog($filepath) {
        $dirname = dirname($filepath);
        $filename = basename($filepath);
        
        // Find next available number
        for ($i = 1; $i <= $this->maxFiles; $i++) {
            $archived = "{$dirname}/{$filename}.{$i}";
            if (!file_exists($archived)) {
                rename($filepath, $archived);
                break;
            }
        }
        
        // Delete oldest if too many
        $pattern = preg_quote($filename) . '\.(\d+)';
        $files = [];
        
        foreach (scandir($dirname) as $file) {
            if (preg_match('/' . $pattern . '/', $file, $matches)) {
                $files[(int)$matches[1]] = $file;
            }
        }
        
        ksort($files);
        
        while (count($files) > $this->maxFiles) {
            $oldest = array_shift($files);
            @unlink($dirname . '/' . $oldest);
        }
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs($logType, $limit = 100) {
        $date = date('Y-m-d');
        $filepath = $this->logDir . "/{$logType}_{$date}.json";
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $lines = array_slice(
            file($filepath, FILE_SKIP_EMPTY_LINES),
            -$limit
        );
        
        $logs = [];
        foreach ($lines as $line) {
            $logs[] = json_decode($line, true);
        }
        
        return array_reverse($logs);
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats($logType, $days = 7) {
        $stats = [
            'total' => 0,
            'by_day' => [],
            'summary' => []
        ];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $filepath = $this->logDir . "/{$logType}_{$date}.json";
            
            if (file_exists($filepath)) {
                $count = count(file($filepath, FILE_SKIP_EMPTY_LINES));
                $stats['by_day'][$date] = $count;
                $stats['total'] += $count;
            }
        }
        
        return $stats;
    }
}

/**
 * Helper functions for easy logging
 */

if (!function_exists('log_error')) {
    function log_error($message, $severity = 'ERROR', $context = []) {
        return Logger::getInstance()->logError($message, $severity, $context);
    }
}

if (!function_exists('log_event')) {
    function log_event($eventType, $description, $userId = null, $details = []) {
        return Logger::getInstance()->logEvent($eventType, $description, $userId, $details);
    }
}

if (!function_exists('log_action')) {
    function log_action($action, $table, $recordId, $userId = null, $details = []) {
        return Logger::getInstance()->logAction($action, $table, $recordId, $userId, $details);
    }
}

if (!function_exists('log_api_call')) {
    function log_api_call($endpoint, $method, $statusCode, $responseTime, $userId = null) {
        return Logger::getInstance()->logApiCall($endpoint, $method, $statusCode, $responseTime, $userId);
    }
}

if (!function_exists('log_failed_login')) {
    function log_failed_login($username) {
        return Logger::getInstance()->logFailedLogin($username);
    }
}

if (!function_exists('log_login')) {
    function log_login($userId, $username) {
        return Logger::getInstance()->logLogin($userId, $username);
    }
}

if (!function_exists('log_logout')) {
    function log_logout($userId) {
        return Logger::getInstance()->logLogout($userId);
    }
}

if (!function_exists('log_security_event')) {
    function log_security_event($eventType, $description, $severity = 'WARN', $details = []) {
        return Logger::getInstance()->logSecurityEvent($eventType, $description, $severity, $details);
    }
}

if (!function_exists('log_performance')) {
    function log_performance($page, $renderTime, $dbQueries, $memory) {
        return Logger::getInstance()->logPerformance($page, $renderTime, $dbQueries, $memory);
    }
}

?>
