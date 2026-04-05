# Input Validation Guide
**Date Created:** April 1, 2026

## Overview

The `includes/validation.php` file provides comprehensive input validation functions for all data types commonly used in the Romar system.

## Usage Examples

### Basic Email Validation
```php
require_once 'includes/validation.php';

$result = validate_email('user@example.com');
if ($result['valid']) {
    echo "Valid email";
} else {
    echo "Error: " . $result['error'];  // Output: Error: Invalid email format
}
```

### Phone Validation (Country-specific)
```php
// Thai phone
$result = validate_phone('0812345678', 'TH');
// US phone
$result = validate_phone('+1-555-123-4567', 'US');
```

### Date Validation
```php
$result = validate_date('2026-04-01', 'Y-m-d');
if ($result['valid']) {
    echo "Timestamp: " . $result['timestamp'];
}
```

### Role Validation
```php
$result = validate_role('admin');  // Valid: admin, staff, it_support, user, guest
```

### Status Validation
```php
$result = validate_status('active', ['active', 'inactive', 'pending']);
```

### Integer Validation with Constraints
```php
// Validate age between 18 and 100
$result = validate_integer($age, 18, 100);
if (!$result['valid']) {
    echo $result['error'];  // "Must be at least 18"
}
```

### String Validation
```php
// Full name: 2-100 chars, no special chars
$result = validate_string('John Doe', 2, 100, false);
```

### Username Validation
```php
// Min 3, Max 50 chars, alphanumeric + underscore + hyphen
$result = validate_username('john_doe-123');
```

### Password Strength Validation
```php
$result = validate_password('MyP@ssw0rd');
// Returns: { valid: true, strength: 'strong' }
echo "Password strength: " . $result['strength'];
```

### Full Name Validation
```php
// Supports Thai and English, hyphens, apostrophes
$result = validate_full_name("O'Brien");
```

### URL Validation
```php
$result = validate_url('https://example.com');
```

## Batch Validation (Most Common)

Validate multiple fields in one call:

```php
$fields = [
    'username' => [
        'value' => $_POST['username'],
        'type' => 'username',
        'required' => true,
        'minLen' => 3,
        'maxLen' => 50
    ],
    'email' => [
        'value' => $_POST['email'],
        'type' => 'email',
        'required' => true
    ],
    'phone' => [
        'value' => $_POST['phone'],
        'type' => 'phone',
        'required' => false,
        'country' => 'TH'
    ],
    'role' => [
        'value' => $_POST['role'],
        'type' => 'role',
        'required' => true
    ],
    'age' => [
        'value' => $_POST['age'] ?? null,
        'type' => 'integer',
        'required' => true,
        'min' => 18,
        'max' => 100
    ]
];

$validation = validate_batch($fields);

if (!$validation['valid']) {
    // Handle errors
    foreach ($validation['errors'] as $field => $error) {
        echo "$field: $error<br>";
    }
} else {
    // All valid, proceed
    echo "All fields are valid!";
}
```

## All Validation Functions

| Function | Usage | Returns |
|----------|-------|---------|
| `validate_email($email)` | Email validation | `{valid, error}` |
| `validate_phone($phone, $country)` | Phone with country support (TH, US, etc) | `{valid, error}` |
| `validate_date($date, $format)` | Date with format check | `{valid, error, timestamp}` |
| `validate_role($role)` | User role from predefined list | `{valid, error}` |
| `validate_status($status, $allowed)` | Status from allowed values | `{valid, error}` |
| `validate_integer($value, $min, $max)` | Integer with range | `{valid, error, value}` |
| `validate_string($value, $minLen, $maxLen, $allowSpecial)` | String with constraints | `{valid, error}` |
| `validate_username($username, $minLen, $maxLen)` | Username (alphanumeric + underscore/hyphen) | `{valid, error}` |
| `validate_password($pwd, $minLen, $requireNum, $requireSpecial)` | Password strength check | `{valid, error, strength}` |
| `validate_full_name($name)` | Full name (letters, spaces, hyphens) | `{valid, error}` |
| `validate_url($url)` | URL format | `{valid, error}` |
| `validate_batch($fields)` | Multiple fields at once | `{valid, errors}` |

## Integration with Existing Code

### Example 1: Add User Form
```php
<?php
require_once '../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use batch validation
    $validation = validate_batch([
        'username' => ['value' => $_POST['username'] ?? '', 'type' => 'username'],
        'email' => ['value' => $_POST['email'] ?? '', 'type' => 'email'],
        'phone' => ['value' => $_POST['phone'] ?? '', 'type' => 'phone', 'required' => false],
        'password' => ['value' => $_POST['password'] ?? '', 'type' => 'password'],
        'full_name' => ['value' => $_POST['full_name'] ?? '', 'type' => 'string', 'minLen' => 2, 'maxLen' => 100],
        'role' => ['value' => $_POST['role'] ?? '', 'type' => 'role'],
    ]);
    
    if ($validation['valid']) {
        // Proceed with user creation
        // $username = $_POST['username'];
        // etc...
    } else {
        $message = "Validation errors: " . implode(", ", $validation['errors']);
    }
}
?>
```

### Example 2: Update User Profile
```php
<?php
require_once '../includes/validation.php';

if ($_REQUEST['action'] === 'update') {
    $validation = validate_batch([
        'full_name' => [
            'value' => $_POST['full_name'] ?? '',
            'type' => 'string',
            'minLen' => 2,
            'maxLen' => 100
        ],
        'phone' => [
            'value' => $_POST['phone'] ?? '',
            'type' => 'phone',
            'required' => false,
            'country' => 'TH'
        ],
        'email' => [
            'value' => $_POST['email'] ?? '',
            'type' => 'email'
        ],
    ]);
    
    if (!$validation['valid']) {
        $error = json_encode($validation['errors']);
        die("Validation failed: $error");
    }
    
    // Update database
}
?>
```

## Best Practices

1. **Always validate on server-side** - Never rely only on client-side validation
2. **Use batch validation** - For multiple fields, batch is more efficient
3. **Set appropriate constraints** - Min/max lengths, ranges, formats
4. **Provide clear error messages** - Return specific errors to users
5. **Log validation failures** - For security audit
6. **Sanitize after validation** - Validation checks format, sanitize removes harmful chars

## Error Response Format

All validation functions return consistent format:
```php
[
    'valid' => true/false,
    'error' => 'Error message or null',
    // Type-specific fields:
    'timestamp' => 1234567890,  // For date validation
    'value' => 123,              // For integer validation
    'strength' => 'strong'       // For password validation
]
```

## Security Considerations

- ✅ Functions use prepared statements compatible patterns
- ✅ No SQL injection - values not used in queries directly
- ✅ Regex patterns are safe and tested
- ✅ Special characters handled properly
- ✅ Length limits prevent buffer overflow
- ✅ Works with UTF-8 (supports Thai characters)

## Testing Validation

```php
// Test the validation system
require_once 'includes/validation.php';

$tests = [
    ['validate_email', ['test@example.com'], true],
    ['validate_phone', ['0812345678', 'TH'], true],
    ['validate_role', ['admin'], true],
    ['validate_integer', [50, 1, 100], true],
    ['validate_password', ['MyP@ssw0rd'], true],
];

foreach ($tests as $test) {
    $func = $test[0];
    $args = $test[1];
    $expected = $test[2];
    $result = $func(...$args);
    $actual = $result['valid'];
    
    echo "[$func] Expected: " . ($expected ? 'VALID' : 'INVALID');
    echo " | Got: " . ($actual ? 'VALID' : 'INVALID');
    echo " | " . ($actual === $expected ? "PASS" : "FAIL") . "\n";
}
```

---

**Next Steps:**
1. Include this file in your main projects: `require_once 'includes/validation.php';`
2. Replace manual validation with these functions
3. Convert POST handlers to use `validate_batch()`
4. Add validation logging for security audit

## Session & Form Audit

The new `tests/session_form_audit.php` script is a CLI helper that checks:

- Every admin/module/api/`index.php` entry point guards `session_start()` by inspecting `session_status()` before sending output.
- Each POST `<form>` rendered in these files includes either `csrf_input()`, a hidden `csrf_token`, or an explicit `csrf_token()` call.

Run it from the repo root with `php tests/session_form_audit.php`. The script exits with `0` when both checks pass and lists offending files when the guard or CSRF coverage is missing, helping developers keep sessions and CSRF handling consistent without manual inspection.
