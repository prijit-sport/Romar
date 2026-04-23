<?php
/**
 * Modern cURL handle closer - removes PHP8.5 deprecation warning
 */
if (!function_exists('curl_handle_close')) {
    /**
     * @param CurlHandle $handle
     */
    function curl_handle_close($handle): void {
        \curl_handle_close($handle);
    }
}
?>

