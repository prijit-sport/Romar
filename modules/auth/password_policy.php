<?php
/**
 * Password Policy Enforcement for Romar
 * ระบบตรวจสอบความปลอดภัยรหัสผ่าน
 */

class PasswordPolicy {
    const MIN_LENGTH = 8;
    const MIN_UPPER = 1;
    const MIN_LOWER = 1;
    const MIN_NUMBER = 1;
    const MIN_SPECIAL = 1;
    const MAX_REPEATS = 3;
    
    /**
     * Validate password complexity
     */
    public static function validate(string $password): array {
        $issues = [];
        
        if (strlen($password) < self::MIN_LENGTH) {
            $issues[] = "ต้องมีอย่างน้อย " . self::MIN_LENGTH . " ตัวอักษร";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $issues[] = "ต้องมีตัวพิมพ์ใหญ่ 1 ตัว";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $issues[] = "ต้องมีตัวพิมพ์เล็ก 1 ตัว";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $issues[] = "ต้องมีตัวเลข 1 ตัว";
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $issues[] = "ต้องมีอักษรพิเศษ 1 ตัว (!@#$%^&* etc.)";
        }
        
        // Check repeats
        for ($i = 0; $i < strlen($password) - self::MAX_REPEATS; $i++) {
            $repeat = substr($password, $i, self::MAX_REPEATS + 1);
            if (preg_match('/(.)\1{' . self::MAX_REPEATS . '}/', $repeat)) {
                $issues[] = "ห้ามใช้ตัวอักษรซ้ำเกิน 3 ตัวติดกัน";
                break;
            }
        }
        
        // Common passwords
        $common = ['123456', 'password', 'admin123', 'qwerty'];
        foreach ($common as $bad) {
            if (stripos($password, $bad) !== false) {
                $issues[] = "ห้ามใช้รหัสผ่านทั่วไป";
                break;
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'score' => self::calculateScore($password)
        ];
    }
    
    private static function calculateScore(string $password): int {
        $score = 0;
        $length = strlen($password);
        
        $score += min($length, 20);  // Length
        $score += preg_match('/[A-Z]/', $password) ? 10 : 0;
        $score += preg_match('/[a-z]/', $password) ? 10 : 0;
        $score += preg_match('/[0-9]/', $password) ? 15 : 0;
        $score += preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password) ? 20 : 0;
        
        return min($score, 100);
    }
    
    /**
     * Generate strong password
     */
    public static function generate(int $length = 12): string {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = $upper[random_int(0, 25)]
                 . $lower[random_int(0, 25)]
                 . $numbers[random_int(0, 9)]
                 . $special[random_int(0, strlen($special)-1)];
        
        // Fill rest
        $chars = $upper . $lower . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars)-1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Check password history (prevent reuse)
     */
    public static function checkHistory(int $userId, string $hashedPassword): bool {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM password_history 
            WHERE user_id = ? AND password_hash = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ");
        $stmt->bind_param('is', $userId, $hashedPassword);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] == 0;
    }
    
    /**
     * Log password change
     */
    public static function logChange(int $userId, string $oldHash, string $newHash): void {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO password_history (user_id, old_hash, new_hash, changed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('iss', $userId, $oldHash, $newHash);
        $stmt->execute();
    }
}

// Usage example:
/*
$policy = PasswordPolicy::validate('weak');
if (!$policy['valid']) {
    echo "รหัสผ่านไม่ผ่าน: " . implode(', ', $policy['issues']);
}

$strongPass = PasswordPolicy::generate(16);
echo "Generated: " . $strongPass;
*/
