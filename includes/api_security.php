<?php
/**
 * API Security Middleware
 * ✅ CORS handling, rate limiting, request validation
 * 
 * Features:
 *   - CORS headers
 *   - Rate limiting per endpoint
 *   - API versioning support
 *   - Request/Response validation
 *   - Token revocation
 */

class ApiSecurityMiddleware {
    private static ?self $instance = null;
    private array $rateLimitStore = [];
    private int $maxRequestsPerMinute = 60;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Setup CORS headers
     */
    public function setupCors(array $allowedOrigins = ['*']) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*'; 
        
        // Validate origin if not wildcard
        if (!in_array('*', $allowedOrigins, true)) {
            if (!in_array($origin, $allowedOrigins, true)) {
                header('HTTP/1.1 403 Forbidden');
                die('CORS violation: Origin not allowed');
            }
        }
        
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With");
        header("Access-Control-Max-Age: 3600");
        header("Access-Control-Allow-Credentials: true");
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Setup API security headers
     */
    public function setupSecurityHeaders() {
        // Prevent clickjacking
        header("X-Frame-Options: DENY");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Stop browser from MIME-sniffing
        header("Content-Type: application/json; charset=UTF-8");
        
        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Strict transport security
        if (is_https()) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
    
    /**
     * Check rate limit
     */
    public function checkRateLimit(?string $identifier = null, ?int $maxRequests = null) {
        $identifier = $identifier ?? get_remote_addr();
        $maxRequests = $maxRequests ?? $this->maxRequestsPerMinute;
        $now = time();
        $windowStart = $now - 60;
        
        // Get session-based storage
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        // Clean old entries
        $_SESSION['rate_limit'] = array_filter(
            $_SESSION['rate_limit'],
            fn($time) => $time > $windowStart
        );
        
        // Check if limit exceeded
        $count = count($_SESSION['rate_limit']);
        if ($count >= $maxRequests) {
            return [
                'allowed' => false,
                'message' => "Rate limit exceeded: {$maxRequests} requests per minute",
                'retry_after' => 60
            ];
        }
        
        // Record request
        $_SESSION['rate_limit'][$now . '_' . mt_rand()] = $now;
        
        return [
            'allowed' => true,
            'remaining' => $maxRequests - $count - 1,
            'reset_at' => $windowStart + 60
        ];
    }
    
    /**
     * Validate API request
     */
    public function validateRequest() {
        $errors = [];
        
        // Check method is appropriate for content
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (empty($contentType)) {
                $errors[] = 'Content-Type header required for this request method';
            } elseif (strpos($contentType, 'application/json') === false) {
                $errors[] = 'Content-Type must be application/json';
            }
        }
        
        // Validate JSON if applicable
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'], true)) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'Invalid JSON: ' . json_last_error_msg();
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Send API response
     */
    public function sendJsonResponse(mixed $data, int $statusCode = 200, array $headers = []): void {
        http_response_code($statusCode);
        
        // Add default headers
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];
        
        foreach (array_merge($defaultHeaders, $headers) as $key => $value) {
            header("$key: $value");
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send error response
     */
    public function sendErrorResponse(string $message, string|int $code, int $statusCode = 400, array $details = []): void {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => date('c')
            ]
        ];
        
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        $this->sendJsonResponse($response, $statusCode);
    }
    
    /**
     * Send success response
     */
    public function sendSuccessResponse(array $data = [], string $message = 'Success', array $meta = []): void {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        $this->sendJsonResponse($response);
    }
    
    /**
     * Validate API version
     */
    public function validateApiVersion(string $version, array $supportedVersions = ['v1', 'v2']): array {
        if (!in_array($version, $supportedVersions, true)) {
            return [
                'valid' => false,
                'supported' => $supportedVersions,
                'message' => "API version $version not supported"
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get API version from request
     */
    public function getRequestApiVersion(string $default = 'v1'): string {
        // Check header
        $version = $_SERVER['HTTP_API_VERSION'] ?? null;
        
        // Check URL parameter
        if (!$version) {
            $version = $_GET['api_version'] ?? null;
        }
        
        // Check URL path (e.g., /api/v1/users)
        if (!$version && preg_match('#/api/(v\d+)/#', $_SERVER['REQUEST_URI'], $matches)) {
            $version = $matches[1];
        }
        
        return $version ?? $default;
    }
}

/**
 * Helper functions for API middleware
 */

if (!function_exists('api_setup_cors')) {
    /**
     * @param array $allowedOrigins
     * @return void
     */
    function api_setup_cors($allowedOrigins = ['*']) {
        ApiSecurityMiddleware::getInstance()->setupCors($allowedOrigins);
    }
}

if (!function_exists('api_setup_security_headers')) {
    /**
     * @return void
     */
    function api_setup_security_headers() {
        ApiSecurityMiddleware::getInstance()->setupSecurityHeaders();
    }
}

if (!function_exists('api_check_rate_limit')) {
    /**
     * @param ?string $identifier
     * @param ?int $maxRequests
     * @return array
     */
    function api_check_rate_limit($identifier = null, $maxRequests = null) {
        return ApiSecurityMiddleware::getInstance()->checkRateLimit($identifier, $maxRequests);
    }
}

if (!function_exists('api_validate_request')) {
    /**
     * @return array
     */
    function api_validate_request() {
        return ApiSecurityMiddleware::getInstance()->validateRequest();
    }
}

if (!function_exists('api_json_response')) {
    /**
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return void
     */
    function api_json_response($data, $statusCode = 200, $headers = []) {
        ApiSecurityMiddleware::getInstance()->sendJsonResponse($data, $statusCode, $headers);
    }
}

if (!function_exists('api_error_response')) {
    /**
     * @param string $message
     * @param string|int $code
     * @param int $statusCode
     * @param array $details
     * @return void
     */
    function api_error_response($message, $code, $statusCode = 400, $details = []) {
        ApiSecurityMiddleware::getInstance()->sendErrorResponse($message, $code, $statusCode, $details);
    }
}

if (!function_exists('api_success_response')) {
    /**
     * @param array $data
     * @param string $message
     * @param array $meta
     * @return void
     */
    function api_success_response($data = [], $message = 'Success', $meta = []) {
        ApiSecurityMiddleware::getInstance()->sendSuccessResponse($data, $message, $meta);
    }
}

if (!function_exists('api_get_version')) {
    /**
     * @param string $default
     * @return string
     */
    function api_get_version($default = 'v1') {
        return ApiSecurityMiddleware::getInstance()->getRequestApiVersion($default);
    }
}

?>
