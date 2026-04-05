<?php
/**
 * Input Validation Helper Functions
 * ✅ Comprehensive input validation for all data types
 * 
 * Usage:
 *   validate_email($email)
 *   validate_phone($phone, 'TH')
 *   validate_date($date, 'Y-m-d')
 *   validate_role($role)
 *   validate_status($status, ['active', 'inactive'])
 *   validate_integer($value, $min, $max)
 *   validate_string($value, $minLen, $maxLen)
 *   validate_url($url)
 *   validate_username($username)
 *   validate_password($password)
 */

if (!function_exists('validate_email')) {
    /**
     * Validate email address
     * @param string $email Email to validate
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_email($email) {
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email cannot be empty'];
        }
        
        $email = trim($email);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }
        
        if (strlen($email) > 254) {
            return ['valid' => false, 'error' => 'Email is too long (max 254 characters)'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_phone')) {
    /**
     * Validate phone number
     * @param string $phone Phone number
     * @param string $country Country code: 'TH', 'US', etc.
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_phone($phone, $country = 'TH') {
        if (empty($phone)) {
            return ['valid' => false, 'error' => 'Phone number cannot be empty'];
        }
        
        $phone = preg_replace('/[^0-9+\-() ]/', '', $phone); // Remove invalid chars
        $phone = trim($phone);
        
        // Country-specific validation
        switch (strtoupper($country)) {
            case 'TH':
                // Thai: 08-10 digits, starts with 0
                if (!preg_match('/^0[0-9]{8,9}$/', str_replace(['-', ' '], '', $phone))) {
                    return ['valid' => false, 'error' => 'Invalid Thai phone format (should be 08-10 digits starting with 0)'];
                }
                break;
                
            case 'US':
                // US: 10 digits
                if (!preg_match('/^\+?1?\s?[0-9]{3}[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}$/', $phone)) {
                    return ['valid' => false, 'error' => 'Invalid US phone format'];
                }
                break;
                
            default:
                // Generic: at least 7 digits
                $digits = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($digits) < 7 || strlen($digits) > 15) {
                    return ['valid' => false, 'error' => 'Invalid phone format (7-15 digits required)'];
                }
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_date')) {
    /**
     * Validate date string
     * @param string $date Date string
     * @param string $format Expected format (Y-m-d, d/m/Y, etc.)
     * @return array ['valid' => bool, 'error' => string or null, 'timestamp' => int or null]
     */
    function validate_date($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return ['valid' => false, 'error' => 'Date cannot be empty', 'timestamp' => null];
        }
        
        $date = trim($date);
        
        // Parse the date according to format
        $dt = DateTime::createFromFormat($format, $date);
        
        if (!$dt || $dt->format($format) !== $date) {
            return ['valid' => false, 'error' => "Invalid date format (expected $format)", 'timestamp' => null];
        }
        
        // Check if date is not in the future (optional)
        if ($dt->getTimestamp() > time()) {
            // Allow future dates, just flag future
        }
        
        return ['valid' => true, 'error' => null, 'timestamp' => $dt->getTimestamp()];
    }
}

if (!function_exists('validate_role')) {
    /**
     * Validate user role
     * @param string $role Role to validate
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_role($role) {
        $allowedRoles = ['admin', 'staff', 'it_support', 'user', 'guest'];
        
        if (empty($role)) {
            return ['valid' => false, 'error' => 'Role cannot be empty'];
        }
        
        $role = strtolower(trim($role));
        
        if (!in_array($role, $allowedRoles, true)) {
            return ['valid' => false, 'error' => 'Invalid role. Allowed: ' . implode(', ', $allowedRoles)];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_status')) {
    /**
     * Validate status from allowed values
     * @param string $status Status to validate
     * @param array $allowedValues Allowed values
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_status($status, $allowedValues = ['active', 'inactive']) {
        if (empty($status)) {
            return ['valid' => false, 'error' => 'Status cannot be empty'];
        }
        
        $status = strtolower(trim($status));
        
        if (!in_array($status, $allowedValues, true)) {
            return ['valid' => false, 'error' => 'Invalid status. Allowed: ' . implode(', ', $allowedValues)];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_integer')) {
    /**
     * Validate integer with optional min/max
     * @param mixed $value Value to validate
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return array ['valid' => bool, 'error' => string or null, 'value' => int or null]
     */
    function validate_integer($value, $min = null, $max = null) {
        if ($value === '' || $value === null) {
            return ['valid' => false, 'error' => 'Value cannot be empty', 'value' => null];
        }
        
        if (!is_numeric($value) || (int)$value != $value) {
            return ['valid' => false, 'error' => 'Must be an integer', 'value' => null];
        }
        
        $value = (int)$value;
        
        if ($min !== null && $value < $min) {
            return ['valid' => false, 'error' => "Must be at least $min", 'value' => null];
        }
        
        if ($max !== null && $value > $max) {
            return ['valid' => false, 'error' => "Must be at most $max", 'value' => null];
        }
        
        return ['valid' => true, 'error' => null, 'value' => $value];
    }
}

if (!function_exists('validate_string')) {
    /**
     * Validate string length
     * @param string $value String to validate
     * @param int|null $minLen Minimum length
     * @param int|null $maxLen Maximum length
     * @param bool $allowSpecialChars Allow special characters
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_string($value, $minLen = null, $maxLen = null, $allowSpecialChars = true) {
        if (!is_string($value)) {
            return ['valid' => false, 'error' => 'Must be a string'];
        }
        
        $value = trim($value);
        $len = strlen($value);
        
        if ($minLen !== null && $len < $minLen) {
            return ['valid' => false, 'error' => "At least $minLen characters required"];
        }
        
        if ($maxLen !== null && $len > $maxLen) {
            return ['valid' => false, 'error' => "Maximum $maxLen characters allowed"];
        }
        
        if (!$allowSpecialChars && !preg_match('/^[a-zA-Z0-9_\s-]*$/', $value)) {
            return ['valid' => false, 'error' => 'Special characters not allowed'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_url')) {
    /**
     * Validate URL
     * @param string $url URL to validate
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_url($url) {
        if (empty($url)) {
            return ['valid' => false, 'error' => 'URL cannot be empty'];
        }
        
        $url = trim($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_username')) {
    /**
     * Validate username
     * @param string $username Username to validate
     * @param int $minLen Minimum length
     * @param int $maxLen Maximum length
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_username($username, $minLen = 3, $maxLen = 50) {
        if (empty($username)) {
            return ['valid' => false, 'error' => 'Username cannot be empty'];
        }
        
        $username = trim($username);
        $len = strlen($username);
        
        if ($len < $minLen) {
            return ['valid' => false, 'error' => "Username must be at least $minLen characters"];
        }
        
        if ($len > $maxLen) {
            return ['valid' => false, 'error' => "Username cannot exceed $maxLen characters"];
        }
        
        // Username: alphanumeric, underscore, hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return ['valid' => false, 'error' => 'Username can only contain letters, numbers, underscore, and hyphen'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_password')) {
    /**
     * Validate password strength
     * @param string $password Password to validate
     * @param int $minLen Minimum length
     * @param bool $requireNumbers Require numbers
     * @param bool $requireSpecial Require special characters
     * @return array ['valid' => bool, 'error' => string or null, 'strength' => string]
     */
    function validate_password($password, $minLen = 8, $requireNumbers = true, $requireSpecial = true) {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password cannot be empty', 'strength' => 'none'];
        }
        
        $len = strlen($password);
        
        if ($len < $minLen) {
            return ['valid' => false, 'error' => "Password must be at least $minLen characters", 'strength' => 'weak'];
        }
        
        if ($requireNumbers && !preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain numbers', 'strength' => 'weak'];
        }
        
        if ($requireSpecial && !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain special characters', 'strength' => 'weak'];
        }
        
        // Determine strength
        $strength = 'weak';
        $complexity = 0;
        
        if (preg_match('/[a-z]/', $password)) $complexity++;
        if (preg_match('/[A-Z]/', $password)) $complexity++;
        if (preg_match('/[0-9]/', $password)) $complexity++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) $complexity++;
        
        if ($len >= 12 && $complexity >= 3) $strength = 'strong';
        elseif ($len >= 10 && $complexity >= 3) $strength = 'medium';
        elseif ($len >= 8 && $complexity >= 2) $strength = 'fair';
        
        return ['valid' => true, 'error' => null, 'strength' => $strength];
    }
}

if (!function_exists('validate_full_name')) {
    /**
     * Validate full name
     * @param string $name Full name to validate
     * @return array ['valid' => bool, 'error' => string or null]
     */
    function validate_full_name($name) {
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Full name cannot be empty'];
        }
        
        $name = trim($name);
        
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Full name cannot exceed 100 characters'];
        }
        
        // Allow letters, spaces, hyphens, apostrophes (for names like "O'Brien")
        if (!preg_match('/^[a-zA-Z\s\-\'ก-๙]+$/u', $name)) {
            return ['valid' => false, 'error' => 'Full name can only contain letters and spaces'];
        }
        
        // At least 2 characters
        if (strlen($name) < 2) {
            return ['valid' => false, 'error' => 'Full name must be at least 2 characters'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('validate_batch')) {
    /**
     * Validate multiple fields at once
     * @param array $fields Associative array of field => rules
     * Example:
     *   [
     *       'email' => ['type' => 'email'],
     *       'role' => ['type' => 'role'],
     *       'phone' => ['type' => 'phone', 'country' => 'TH'],
     *       'age' => ['type' => 'integer', 'min' => 18, 'max' => 100],
     *   ]
     * @return array ['valid' => bool, 'errors' => array of errors]
     */
    function validate_batch($fields) {
        $errors = [];
        
        foreach ($fields as $fieldName => $field) {
            $value = isset($field['value']) ? $field['value'] : null;
            $type = $field['type'] ?? null;
            $required = $field['required'] ?? true;
            
            // Check if required
            if ($required && empty($value)) {
                $errors[$fieldName] = "$fieldName is required";
                continue;
            }
            
            if (!$required && empty($value)) {
                continue; // Skip validation for optional empty fields
            }
            
            // Validate based on type
            switch ($type) {
                case 'email':
                    $result = validate_email($value);
                    break;
                    
                case 'phone':
                    $country = $field['country'] ?? 'TH';
                    $result = validate_phone($value, $country);
                    break;
                    
                case 'date':
                    $format = $field['format'] ?? 'Y-m-d';
                    $result = validate_date($value, $format);
                    break;
                    
                case 'role':
                    $result = validate_role($value);
                    break;
                    
                case 'status':
                    $allowed = $field['allowed'] ?? ['active', 'inactive'];
                    $result = validate_status($value, $allowed);
                    break;
                    
                case 'integer':
                    $min = $field['min'] ?? null;
                    $max = $field['max'] ?? null;
                    $result = validate_integer($value, $min, $max);
                    break;
                    
                case 'string':
                    $minLen = $field['minLen'] ?? null;
                    $maxLen = $field['maxLen'] ?? null;
                    $allowSpecial = $field['allowSpecial'] ?? true;
                    $result = validate_string($value, $minLen, $maxLen, $allowSpecial);
                    break;
                    
                case 'username':
                    $minLen = $field['minLen'] ?? 3;
                    $maxLen = $field['maxLen'] ?? 50;
                    $result = validate_username($value, $minLen, $maxLen);
                    break;
                    
                case 'password':
                    $minLen = $field['minLen'] ?? 8;
                    $requireNumbers = $field['requireNumbers'] ?? true;
                    $requireSpecial = $field['requireSpecial'] ?? true;
                    $result = validate_password($value, $minLen, $requireNumbers, $requireSpecial);
                    break;
                    
                case 'url':
                    $result = validate_url($value);
                    break;
                    
                default:
                    // Skip unknown field types
                    break;
            }
            
            if (isset($result) && isset($result['valid']) && !$result['valid']) {
                $errors[$fieldName] = $result['error'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

?>
