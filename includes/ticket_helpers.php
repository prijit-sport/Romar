<?php
/**
 * Shared helpers for ticket modules.
 */

if (!function_exists('calculateSLA')) {
    /**
     * Return SLA hours based on priority and impact.
     */
    function calculateSLA(string $priority, string $impact): int
    {
        $slaMatrix = [
            'urgent' => ['critical' => 2, 'high' => 4, 'medium' => 8, 'low' => 16],
            'high'   => ['critical' => 4, 'high' => 8, 'medium' => 16, 'low' => 24],
            'normal' => ['critical' => 8, 'high' => 16, 'medium' => 24, 'low' => 48],
            'low'    => ['critical' => 16, 'high' => 24, 'medium' => 48, 'low' => 72],
        ];

        return $slaMatrix[$priority][$impact] ?? 24;
    }
}

if (!function_exists('addTimeline')) {
    /**
     * Insert a timeline entry for the given ticket.
     */
    function addTimeline(mysqli $db, int $ticketId, string $eventType, string $description): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, event_type, description, user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return false;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $stmt->bind_param('issi', $ticketId, $eventType, $description, $userId);
        return $stmt->execute();
    }
}

if (!function_exists('getUserName')) {
    /**
     * Return the full name of a user.
     */
    function getUserName(mysqli $db, int $userId): string
    {
        $stmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
        if (!$stmt) {
            return 'Unknown';
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['full_name'] ?? 'Unknown';
    }
}
