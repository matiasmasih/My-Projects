<?php
// includes/i18n.php — Language system (EN / FI / FA)

// Available languages
function supported_languages(): array {
    return [
        'en' => ['native' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
        'fi' => ['native' => 'Suomi',   'flag' => '🇫🇮', 'dir' => 'ltr'],
        'fa' => ['native' => 'فارسی',   'flag' => '🇮🇷', 'dir' => 'rtl'],
    ];
}

// Language → Currency auto-link
function language_currency_map(): array {
    return [
        'en' => 'USD',
        'fi' => 'EUR',
        'fa' => 'AFN',
    ];
}

// Get current language: URL → session → default 'en'
function current_language(): string {
    $langs = supported_languages();

    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $langs)) {
        $_SESSION['language'] = $_GET['lang'];
        // Auto-switch currency to match language
        $map = language_currency_map();
        if (isset($map[$_GET['lang']])) {
            $_SESSION['currency'] = $map[$_GET['lang']];
        }
        return $_GET['lang'];
    }
    if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], $langs)) {
        return $_SESSION['language'];
    }
    return 'en';
}

// Text direction for current language
function text_direction(): string {
    $lang = current_language();
    $langs = supported_languages();
    return $langs[$lang]['dir'] ?? 'ltr';
}

// Load translations from lang/{code}.php
function load_translations(string $lang): array {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];

    $file = __DIR__ . '/../lang/' . $lang . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/../lang/en.php';
    }
    $translations = file_exists($file) ? include $file : [];
    if (!is_array($translations)) $translations = [];

    $cache[$lang] = $translations;
    return $translations;
}

// Translate a key: t('menu_title')
function t(string $key, ?string $default = null): string {
    static $loaded = [];
    $lang = current_language();

    if (!isset($loaded[$lang])) {
        $loaded[$lang] = load_translations($lang);
    }
    if (isset($loaded[$lang][$key])) {
        return $loaded[$lang][$key];
    }
    // Fallback to English
    if ($lang !== 'en') {
        if (!isset($loaded['en'])) $loaded['en'] = load_translations('en');
        if (isset($loaded['en'][$key])) return $loaded['en'][$key];
    }
    return $default !== null ? $default : $key;
}
