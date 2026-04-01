<?php
/**
 * Safe Variable Access Helpers
 * ✅ Protect against undefined index/key notices
 * 
 * Usage:
 *   get_safe_get('param', 'default', 'sanitize_type')
 *   get_safe_post('field', 0, 'integer')
 *   get_safe_server('REMOTE_ADDR')
 *   get_safe_cookie('session_id')
 *   get_safe_session('user_id')
 *   get_safe_array($array, $key, $default)
 */

if (!function_exists('get_remote_addr')) {
    /**
     * Get client IP address safely
     * @return string IP address or 'unknown'
     */
    function get_remote_addr() {
        // Check for IP from proxy servers
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipList = array_map('trim', explode(',', $_SERVER['HTTP_CLIENT_IP']));
            $ip = $ipList[0]; // Get first IP from list
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = $ipList[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = 'unknown';
        }
        
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }
        
        return $ip;
    }
}

if (!function_exists('get_safe_get')) {
    /**
     * Get $_GET parameter safely
     * @param string $key Key to retrieve
     * @param mixed $default Default value if not set
     * @param string $type Type to cast to: 'string', 'int', 'integer', 'bool', 'boolean', 'float', 'array'
     * @return mixed Value or default
     */
    function get_safe_get($key, $default = '', $type = 'string') {
        $value = isset($_GET[$key]) ? $_GET[$key] : $default;
        return _cast_to_type($value, $type);
    }
}

if (!function_exists('get_safe_post')) {
    /**
     * Get $_POST parameter safely
     * @param string $key Key to retrieve
     * @param mixed $default Default value if not set
     * @param string $type Type to cast to
     * @return mixed Value or default
     */
    function get_safe_post($key, $default = '', $type = 'string') {
        $value = isset($_POST[$key]) ? $_POST[$key] : $default;
        return _cast_to_type($value, $type);
    }
}

if (!function_exists('get_safe_request')) {
    /**
     * Get $_REQUEST parameter safely (GET or POST)
     * @param string $key Key to retrieve
     * @param mixed $default Default value if not set
     * @param string $type Type to cast to
     * @return mixed Value or default
     */
    function get_safe_request($key, $default = '', $type = 'string') {
        $value = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
        return _cast_to_type($value, $type);
    }
}

if (!function_exists('get_safe_server')) {
    /**
     * Get $_SERVER parameter safely
     * @param string $key Key to retrieve
     * @param mixed $default Default value if not set
     * @return mixed Value or default
     */
    function get_safe_server($key, $default = '') {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }
}

if (!function_exists('get_safe_cookie')) {
    /**
     * Get $_COOKIE parameter safely
     * @param string $key Key to retrieve
     * @param mixed $default Default value if not set
     * @return mixed Value or default
     */
    function get_safe_cookie($key, $default = '') {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }
}

if (!function_exists('get_safe_session')) {
    /**
     * Get $_SESSION parameter safely
     * @param string $key Key to retrieve (dot notation: 'parent.child')
     * @param mixed $default Default value if not set
     * @return mixed Value or default
     */
    function get_safe_session($key, $default = '') {
        // Support dot notation: 'user.id' -> $_SESSION['user']['id']
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $_SESSION;
            
            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return $default;
                }
                $value = $value[$k];
            }
            
            return $value;
        } else {
            return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
        }
    }
}

if (!function_exists('get_safe_array')) {
    /**
     * Get array element safely
     * @param array $array Array to access
     * @param string $key Key to retrieve (dot notation supported: 'parent.child')
     * @param mixed $default Default value if not set
     * @param string $type Type to cast to
     * @return mixed Value or default
     */
    function get_safe_array($array, $key, $default = '', $type = 'string') {
        if (!is_array($array)) {
            return _cast_to_type($default, $type);
        }
        
        // Support dot notation: 'user.name' -> $array['user']['name']
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $array;
            
            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return _cast_to_type($default, $type);
                }
                $value = $value[$k];
            }
            
            return _cast_to_type($value, $type);
        } else {
            $value = isset($array[$key]) ? $array[$key] : $default;
            return _cast_to_type($value, $type);
        }
    }
}

if (!function_exists('_cast_to_type')) {
    /**
     * Cast value to specified type
     * @internal
     */
    function _cast_to_type($value, $type) {
        $type = strtolower($type);
        
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)$value;
            
            case 'float':
            case 'double':
                return (float)$value;
            
            case 'bool':
            case 'boolean':
                return (bool)$value;
            
            case 'array':
                return is_array($value) ? $value : [];
            
            case 'string':
            default:
                return (string)$value;
        }
    }
}

if (!function_exists('set_safe_session')) {
    /**
     * Set $_SESSION value safely
     * @param string $key Key to set (dot notation: 'parent.child')
     * @param mixed $value Value to set
     * @return bool Success
     */
    function set_safe_session($key, $value) {
        // Support dot notation: 'user.id' -> $_SESSION['user']['id']
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $lastKey = array_pop($keys);
            $current = &$_SESSION;
            
            foreach ($keys as $k) {
                if (!isset($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
            
            $current[$lastKey] = $value;
            return true;
        } else {
            $_SESSION[$key] = $value;
            return true;
        }
    }
}

if (!function_exists('unset_safe_session')) {
    /**
     * Unset $_SESSION value safely
     * @param string $key Key to unset (dot notation supported)
     * @return bool Success
     */
    function unset_safe_session($key) {
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $lastKey = array_pop($keys);
            $current = &$_SESSION;
            
            foreach ($keys as $k) {
                if (!isset($current[$k])) {
                    return false;
                }
                $current = &$current[$k];
            }
            
            unset($current[$lastKey]);
            return true;
        } else {
            unset($_SESSION[$key]);
            return true;
        }
    }
}

/**
 * Safe defaults for common server variables
 */
if (!function_exists('get_http_method')) {
    function get_http_method() {
        return strtoupper(get_safe_server('REQUEST_METHOD', 'GET'));
    }
}

if (!function_exists('get_user_agent')) {
    function get_user_agent() {
        return get_safe_server('HTTP_USER_AGENT', 'Unknown');
    }
}

if (!function_exists('get_referer')) {
    function get_referer() {
        return get_safe_server('HTTP_REFERER', '');
    }
}

if (!function_exists('is_ajax')) {
    /**
     * Check if request is AJAX
     */
    function is_ajax() {
        $header = get_safe_server('HTTP_X_REQUESTED_WITH', '');
        return strtolower($header) === 'xmlhttprequest';
    }
}

if (!function_exists('is_post')) {
    /**
     * Check if request is POST
     */
    function is_post() {
        return get_http_method() === 'POST';
    }
}

if (!function_exists('is_get')) {
    /**
     * Check if request is GET
     */
    function is_get() {
        return get_http_method() === 'GET';
    }
}

if (!function_exists('is_https')) {
    /**
     * Check if request is HTTPS
     */
    function is_https() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        return false;
    }
}

?>
