<?php
/**
 * Performance Optimization Helpers
 * ✅ Database optimization, pagination, caching utilities
 */

if (!function_exists('paginate')) {
    /**
     * Generate pagination
     */
    function paginate($total, $currentPage = 1, $perPage = 20, $range = 5) {
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
    function _generatePageRange($current, $total, $range = 5) {
        $start = max(1, $current - floor($range / 2));
        $end = min($total, $start + $range - 1);
        $start = max(1, $end - $range + 1);
        
        return range($start, $end);
    }
}

if (!function_exists('optimize_query')) {
    /**
     * Add LIMIT to query for safety
     */
    function optimize_query($query, $limit = 1000) {
        if (!preg_match('/LIMIT\s+\d+/i', $query)) {
            $query .= " LIMIT $limit";
        }
        return $query;
    }
}

if (!function_exists('get_cache')) {
    /**
     * Get from session cache
     */
    function get_cache($key, $default = null) {
        if (!isset($_SESSION['__cache'])) {
            $_SESSION['__cache'] = [];
        }
        return $_SESSION['__cache'][$key] ?? $default;
    }
}

if (!function_exists('set_cache')) {
    /**
     * Set session cache with TTL
     */
    function set_cache($key, $value, $ttl = 3600) {
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
     */
    function clear_cache($key = null) {
        if ($key === null) {
            $_SESSION['__cache'] = [];
        } else {
            unset($_SESSION['__cache'][$key]);
        }
    }
}

?>
