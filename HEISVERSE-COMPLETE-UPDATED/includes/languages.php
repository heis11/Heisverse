<?php
/**
 * HEISVERSE — Internationalization Engine (i18n)
 * Supports English, Kinyarwanda, French, and Swahili with English fallback.
 */

declare(strict_types=1);

class Lang {
    private static string $currentLang = 'en';
    private static array $translations = [];
    private static array $supportedLanguages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'rw' => ['name' => 'Kinyarwanda', 'flag' => '🇷🇼'],
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
        'sw' => ['name' => 'Kiswahili', 'flag' => '🇹🇿']
    ];

    /**
     * Initialize language based on user session, preference or cookie
     */
    public static function init(): void {
        if (!empty($_SESSION['lang']) && array_key_exists($_SESSION['lang'], self::$supportedLanguages)) {
            self::$currentLang = $_SESSION['lang'];
        } elseif (!empty($_COOKIE['heisverse_lang']) && array_key_exists($_COOKIE['heisverse_lang'], self::$supportedLanguages)) {
            self::$currentLang = $_COOKIE['heisverse_lang'];
        } else {
            self::$currentLang = 'en';
        }

        self::load(self::$currentLang);
    }

    /**
     * Switch language and save preference
     */
    public static function setLanguage(string $lang): void {
        if (array_key_exists($lang, self::$supportedLanguages)) {
            self::$currentLang = $lang;
            $_SESSION['lang'] = $lang;
            setcookie('heisverse_lang', $lang, time() + (86400 * 365), '/', '', false, true);
            self::load($lang);
        }
    }

    /**
     * Load translation JSON dictionary
     */
    private static function load(string $lang): void {
        $path = __DIR__ . '/../languages/' . $lang . '.json';
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data)) {
                self::$translations = $data;
                return;
            }
        }

        // Fallback to English
        $fallback = __DIR__ . '/../languages/en.json';
        if (file_exists($fallback)) {
            self::$translations = json_decode(file_get_contents($fallback), true) ?: [];
        }
    }

    /**
     * Translate key with optional parameter substitution
     */
    public static function get(string $key, array $replace = []): string {
        $text = self::$translations[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string)$v, $text);
        }
        return $text;
    }

    public static function current(): string {
        return self::$currentLang;
    }

    public static function getSupported(): array {
        return self::$supportedLanguages;
    }
}

// Global translation shorthand
function __(string $key, array $replace = []): string {
    return Lang::get($key, $replace);
}

// Auto-initialize on include
Lang::init();
