<?php
// lang.php — Translation helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Detect and store the current language.
 * Priority:
 *   1. ?lang=xx in URL  (switches language)
 *   2. $_SESSION['language']  (saved preference)
 *   3. Default: 'en'
 */
function init_language() {
    $supported = ['en', 'fi', 'fa'];

    // URL switch
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $_SESSION['language'] = $_GET['lang'];
    }

    // Session fallback
    if (empty($_SESSION['language']) || !in_array($_SESSION['language'], $supported, true)) {
        $_SESSION['language'] = 'en';
    }

    return $_SESSION['language'];
}

/** Current language code (en|fi|fa) */
function current_language() {
    return $_SESSION['language'] ?? 'en';
}

/**
 * Get a translated string by key.
 * Falls back to English if the key is missing in the selected language.
 */
function t(string $key): string {
    static $strings = null;

    if ($strings === null) {
        $lang = current_language();
        $file = __DIR__ . '/lang/' . $lang . '.php';

        if (!file_exists($file)) {
            $file = __DIR__ . '/lang/en.php';
        }

        $strings = require $file;
    }

    if (isset($strings[$key])) {
        return $strings[$key];
    }

    // Fallback to English
    static $fallback = null;
    if ($fallback === null) {
        $fallback = require __DIR__ . '/lang/en.php';
    }

    return $fallback[$key] ?? $key;
}

/**
 * Detect direction: RTL for Farsi, LTR otherwise.
 */
function text_direction(): string {
    return current_language() === 'fa' ? 'rtl' : 'ltr';
}

// Auto-init on include
init_language();
?>
