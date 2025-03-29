<?php
/*
* File: Translator.php
* Category: -
* Author: M.Goldenbaum
* Created: 08.11.24 22:53
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\CalMag;

class Translator {

    private string $locale;
    private array $translations = [];
    private static self $instance;

    public function __construct(string $locale = "en") {
        $this->locale = $locale;
        $this->load();
    }

    public static function getInstance(): self {
        if(!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function translate(string $key, array $params = []): string {
        return self::getInstance()->get($key, $params);
    }

    public function load(): void {
        error_log('=== Starting translation load ===');
        error_log('Current session locale: ' . ($_SESSION['locale'] ?? 'not set'));
        error_log('Current instance locale: ' . $this->locale);
        error_log('Session ID: ' . session_id());
        error_log('Session status: ' . session_status());
        
        // Ensure session is active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('Session restarted in load()');
        }
        
        // Check for session language first
        if (isset($_SESSION['locale'])) {
            error_log('Session locale is set to: ' . $_SESSION['locale']);
            $translationFile = __DIR__ . '/../resources/lang/' . $_SESSION['locale'] . '.php';
            error_log('Checking translation file: ' . $translationFile);
            
            if (file_exists($translationFile)) {
                error_log('Using session locale: ' . $_SESSION['locale']);
                $this->locale = $_SESSION['locale'];
            } else {
                error_log('Translation file not found for session locale: ' . $_SESSION['locale']);
            }
        } else {
            error_log('No session locale found');
        }
        
        // If no valid session locale, check browser language
        if (!isset($_SESSION['locale']) || !file_exists(__DIR__ . '/../resources/lang/' . $_SESSION['locale'] . '.php')) {
            error_log('Checking browser language');
            $browserLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            error_log('Browser language header: ' . $browserLang);
            
            $locale = substr(array_map(function($locale) {
                return explode("-", explode(",", $locale)[0])[0];
            }, array_filter(explode(";", $browserLang), function($locale) {
                return str_contains($locale, ",");
            }))[0] ?? "en", 0, 2);
            
            error_log('Extracted browser locale: ' . $locale);
            $translationFile = __DIR__ . '/../resources/lang/' . $locale . '.php';
            
            if(file_exists($translationFile)) {
                error_log('Using browser locale: ' . $locale);
                $this->locale = $locale;
                $_SESSION['locale'] = $locale;
                error_log('Updated session locale to: ' . $locale);
            } else {
                error_log('No valid browser locale found, defaulting to en');
                $this->locale = "en";
                $_SESSION['locale'] = "en";
                error_log('Updated session locale to: en');
            }
        }
        
        error_log('Final locale selected: ' . $this->locale);
        $translationFile = __DIR__ . '/../resources/lang/' . $this->locale . '.php';
        error_log('Loading translations from: ' . $translationFile);
        
        if (!file_exists($translationFile)) {
            error_log('ERROR: Translation file not found: ' . $translationFile);
            return;
        }
        
        $translations = include $translationFile;
        if (!is_array($translations)) {
            error_log('ERROR: Invalid translation file format');
            return;
        }
        
        $this->translations = array_dot($translations);
        error_log('Translations loaded successfully');
        error_log('=== Translation load complete ===');
    }

    public function setLocale(string $locale): void {
        error_log('=== Starting locale change ===');
        error_log('Requested locale: ' . $locale);
        error_log('Current locale: ' . $this->locale);
        error_log('Current session locale: ' . ($_SESSION['locale'] ?? 'not set'));
        error_log('Session ID: ' . session_id());
        
        // Ensure session is active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('Session restarted in setLocale()');
        }
        
        $translationFile = __DIR__ . '/../resources/lang/' . $locale . '.php';
        error_log('Checking translation file: ' . $translationFile);
        
        if (file_exists($translationFile)) {
            error_log('Setting new locale: ' . $locale);
            $this->locale = $locale;
            $_SESSION['locale'] = $locale;
            error_log('Session locale updated to: ' . $locale);
            error_log('Reloading translations...');
            $this->load();
        } else {
            error_log('ERROR: Invalid locale - translation file not found: ' . $translationFile);
        }
        error_log('=== Locale change complete ===');
    }

    public function getCurrentLocale(): string {
        return $this->locale;
    }

    public function get(string $key, array $params = []): string {
        $translation = $this->translations[$key] ?? $key;
        return $this->replaceParams($translation, $params);
    }

    private function replaceParams(string $translation, array $params): string {
        foreach ($params as $key => $value) {
            $translation = str_replace(':' . $key, $value, $translation);
        }
        return $translation;
    }
}