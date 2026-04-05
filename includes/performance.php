<?php
/**
 * Performance Optimization Helpers
 * ✅ Database optimization, pagination, caching utilities
 */

if (!function_exists('paginate')) {
    /**
     * Generate pagination
     * @param int $total
     * @param int $currentPage
     * @param int $perPage
     * @param int $range
     * @return array
     */
    function paginate(int $total, int $currentPage = 1, int $perPage = 20, int $range = 5): array {
        $totalPages = ceil($total / $perPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $offset = ($currentPage - 1) * $perPage;
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'per_page' => $perPage,
            'offset' => $offset,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage - 1,
            'next_page' => $currentPage + 1,
            'page_range' => _generatePageRange($currentPage, $totalPages, $range)
        ];
    }
}

if (!function_exists('_generatePageRange')) {
    /**
     * @param int $current
     * @param int $total
     * @param int $range
     * @return array
     */
    function _generatePageRange(int $current, int $total, int $range = 5): array {
        $start = max(1, $current - floor($range / 2));
        $end = min($total, $start + $range - 1);
        $start = max(1, $end - $range + 1);
        
        return range($start, $end);
    }
}

if (!function_exists('optimize_query')) {
    /**
     * Add LIMIT to query for safety
     * @param string $query
     * @param int $limit
     * @return string
     */
    function optimize_query(string $query, int $limit = 1000): string {
        if (!preg_match('/LIMIT\s+\d+/i', $query)) {
            $query .= " LIMIT $limit";
        }
        return $query;
    }
}

if (!function_exists('get_cache')) {
    /**
     * Get from session cache
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_cache(string $key, $default = null) {
        if (!isset($_SESSION['__cache'])) {
            $_SESSION['__cache'] = [];
        }
        return $_SESSION['__cache'][$key] ?? $default;
    }
}

if (!function_exists('set_cache')) {
    /**
     * Set session cache with TTL
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     */
    function set_cache(string $key, $value, int $ttl = 3600): void {
        if (!isset($_SESSION['__cache'])) {
            $_SESSION['__cache'] = [];
        }
        $_SESSION['__cache'][$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
}

if (!function_exists('clear_cache')) {
    /**
     * Clear session cache
     * @param ?string $key
     */
    function clear_cache(?string $key = null): void {
        if ($key === null) {
            $_SESSION['__cache'] = [];
        } else {
            unset($_SESSION['__cache'][$key]);
        }
    }
}
?>

