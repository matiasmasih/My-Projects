<?php
// includes/i18n.php — Language + translation helpers

require_once __DIR__ . '/config.php';

/**
 * Supported languages: code => [flag, label, native]
 */
function supported_languages(): array {
    return [
        'en' => ['flag' => '🇬🇧', 'label' => 'English', 'native' => 'English'],
        'fi' => ['flag' => '🇫🇮', 'label' => 'Finnish', 'native' => 'Suomi'],
        'fa' => ['flag' => '🇮🇷', 'label' => 'Persian', 'native' => 'فارسی'],
    ];
}

/**
 * Initialise the current language.
 * Priority: ?lang=xx in URL > $_SESSION > default 'fi'
 */
function init_language(): string {
    $supported = array_keys(supported_languages());

    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $_SESSION['language'] = $_GET['lang'];
    }

    if (empty($_SESSION['language']) || !in_array($_SESSION['language'], $supported, true)) {
        $_SESSION['language'] = 'fi'; // site is Finnish by default
    }

    return $_SESSION['language'];
}

/**
 * Current language code (en|fi|fa).
 */
function current_language(): string {
    return $_SESSION['language'] ?? 'fi';
}

/**
 * Text direction: rtl for Persian, ltr otherwise.
 */
function text_direction(): string {
    return current_language() === 'fa' ? 'rtl' : 'ltr';
}

/**
 * Translate a key.
 * Falls back to English if key missing in current language.
 */
function t(string $key): string {
    static $strings  = null;
    static $fallback = null;

    if ($strings === null) {
        $file = LANG_PATH . '/' . current_language() . '.php';
        $strings = file_exists($file) ? require $file : [];
    }

    if (isset($strings[$key])) {
        return $strings[$key];
    }

    if ($fallback === null) {
        $fb = LANG_PATH . '/en.php';
        $fallback = file_exists($fb) ? require $fb : [];
    }

    return $fallback[$key] ?? $key;
}

// Auto-init
init_language();
