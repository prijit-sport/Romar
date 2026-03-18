<?php
if (!function_exists('load_ui_texts')) {
    function load_ui_texts(): array
    {
        static $texts = null;
        if ($texts === null) {
            $texts = require __DIR__ . '/ui_texts.php';
            $overridePath = __DIR__ . '/ui_texts_overrides.php';
            if (file_exists($overridePath)) {
                $texts = array_merge($texts, require $overridePath);
            }
        }
        return $texts;
    }
}

if (!function_exists('ui_text')) {
    /**
     * Return a localized string from the ui_texts map.
     *
     * @param string $key
     * @param array $vars
     * @return string
     */
    function ui_text(string $key, array $vars = []): string
    {
        $texts = load_ui_texts();
        $value = $texts[$key] ?? $key;
        foreach ($vars as $placeholder => $replacement) {
            $value = str_replace("{{$placeholder}}", $replacement, $value);
        }
        return $value;
    }
}
