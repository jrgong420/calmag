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

/**
 * Class Translator
 *
 * Handles loading and retrieving translation strings based on the current locale.
 * It uses a singleton pattern and determines the locale based on session data
 * or browser preferences, falling back to a default ('en'). Translations are
 * loaded from PHP files in `resources/lang/`. It supports dot notation for keys
 * and placeholder replacement.
 * Includes extensive error logging for debugging locale detection and loading.
 */
class Translator {

    /** @var string The currently active locale (e.g., 'en', 'de'). */
    private string $locale;
    /** @var array Loaded translations, flattened with dot notation keys. */
    private array $translations = [];
    /** @var ?Translator Singleton instance. */
    private static ?self $instance = null;

    /**
     * Translator constructor.
     * Private to enforce singleton. Sets initial locale and loads translations.
     *
     * @param string $locale Default locale if none detected.
     */
    private function __construct(string $locale = "en") {
        $this->locale = $locale; // Set initial/default locale
        $this->load(); // Load translations based on detected locale
    }

    /**
     * Gets the singleton instance of the Translator.
     *
     * @return self The singleton instance.
     */
    public static function getInstance(): self {
        // Create instance on first call.
        if(!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Static helper method to translate a key.
     * Convenience method for accessing translations without getting the instance first.
     *
     * @param string $key The translation key (dot notation supported).
     * @param array $params Associative array of placeholders => values for replacement.
     * @return string The translated string or the key itself if not found.
     */
    public static function translate(string $key, array $params = []): string {
        // Get instance and call the 'get' method.
        return self::getInstance()->get($key, $params);
    }

    /**
     * Loads translations for the appropriate locale.
     * Priority: Session -> Browser Accept-Language -> Default ('en').
     * Loads the corresponding PHP file from `resources/lang/` and flattens the array using dot notation.
     * Includes detailed logging for debugging locale detection.
     *
     * @return void
     */
    public function load(): void {
        error_log('=== Starting translation load ===');
        error_log('Current session locale: ' . ($_SESSION['locale'] ?? 'not set'));
        error_log('Current instance locale: ' . $this->locale);
        error_log('Session ID: ' . session_id());
        error_log('Session status: ' . session_status());

        // Ensure PHP session is active before accessing $_SESSION.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('Session restarted in load()');
        }

        $locale_found = false; // Flag to track if a valid locale was determined

        // 1. Check Session for locale preference.
        if (isset($_SESSION['locale'])) {
            error_log('Session locale is set to: ' . $_SESSION['locale']);
            $translationFile = __DIR__ . '/../resources/lang/' . $_SESSION['locale'] . '.php';
            error_log('Checking translation file: ' . $translationFile);

            if (file_exists($translationFile)) {
                error_log('Using session locale: ' . $_SESSION['locale']);
                $this->locale = $_SESSION['locale']; // Set instance locale from session
                $locale_found = true;
            } else {
                error_log('Translation file not found for session locale: ' . $_SESSION['locale'] . '. Unsetting session locale.');
                unset($_SESSION['locale']); // Invalidate session locale if file missing
            }
        } else {
            error_log('No session locale found');
        }

        // 2. If no valid session locale, check Browser Accept-Language header.
        if (!$locale_found) {
            error_log('Checking browser language');
            $browserLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''; // Get header, default empty
            error_log('Browser language header: ' . $browserLang);

            // Basic parsing of Accept-Language header (takes first language preference).
            // Example: "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7" -> "de"
            // This parsing logic might be simplified or made more robust.
            $preferred_locales = explode(',', $browserLang);
            $browser_locale = 'en'; // Default if parsing fails
            if (!empty($preferred_locales[0])) {
                $parts = explode(';', $preferred_locales[0]);
                $locale_tag = explode('-', $parts[0])[0]; // Get primary language subtag (e.g., 'de' from 'de-DE')
                if (strlen($locale_tag) === 2) { // Basic validation
                    $browser_locale = strtolower($locale_tag);
                }
            }

            error_log('Extracted browser locale: ' . $browser_locale);
            $translationFile = __DIR__ . '/../resources/lang/' . $browser_locale . '.php';

            // Check if a translation file exists for the detected browser locale.
            if(file_exists($translationFile)) {
                error_log('Using browser locale: ' . $browser_locale);
                $this->locale = $browser_locale; // Set instance locale
                $_SESSION['locale'] = $browser_locale; // Store detected locale in session for future requests
                error_log('Updated session locale to: ' . $browser_locale);
                $locale_found = true;
            } else {
                error_log('Translation file not found for browser locale: ' . $browser_locale);
            }
        }

        // 3. If still no locale found, default to 'en'.
        if (!$locale_found) {
             error_log('No valid session or browser locale found, defaulting to en');
             $this->locale = "en";
             $_SESSION['locale'] = "en"; // Set session to default
             error_log('Updated session locale to: en');
        }

        // Load the translation file for the final determined locale.
        error_log('Final locale selected: ' . $this->locale);
        $translationFile = __DIR__ . '/../resources/lang/' . $this->locale . '.php';
        error_log('Loading translations from: ' . $translationFile);

        if (!file_exists($translationFile)) {
            error_log('ERROR: Translation file not found: ' . $translationFile);
            $this->translations = []; // Ensure translations are empty if file missing
            return;
        }

        // Include the translation file (which should return an array).
        $translations = include $translationFile;
        if (!is_array($translations)) {
            error_log('ERROR: Invalid translation file format (did not return an array): ' . $translationFile);
            $this->translations = [];
            return;
        }

        // Flatten the translation array using dot notation for keys.
        $this->translations = $this->array_dot($translations);
        error_log('Translations loaded successfully');
        error_log('=== Translation load complete ===');
    }

    /**
     * Sets the current locale and reloads translations.
     * Updates the session locale as well.
     *
     * @param string $locale The new locale code (e.g., 'en', 'de').
     * @return void
     */
    public function setLocale(string $locale): void {
        error_log('=== Starting locale change ===');
        error_log('Requested locale: ' . $locale);
        error_log('Current locale: ' . $this->locale);
        error_log('Current session locale: ' . ($_SESSION['locale'] ?? 'not set'));
        error_log('Session ID: ' . session_id());

        // Ensure session is active.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('Session restarted in setLocale()');
        }

        // Check if the requested locale is valid (i.e., has a translation file).
        $translationFile = __DIR__ . '/../resources/lang/' . $locale . '.php';
        error_log('Checking translation file: ' . $translationFile);

        if (file_exists($translationFile)) {
            error_log('Setting new locale: ' . $locale);
            $this->locale = $locale; // Update instance locale
            $_SESSION['locale'] = $locale; // Update session locale
            error_log('Session locale updated to: ' . $locale);
            error_log('Reloading translations...');
            $this->load(); // Reload translations for the new locale
        } else {
            // Log error if requested locale file doesn't exist. Do not change locale.
            error_log('ERROR: Invalid locale - translation file not found: ' . $translationFile);
        }
        error_log('=== Locale change complete ===');
    }

    /**
     * Gets the currently active locale code.
     *
     * @return string The locale code (e.g., 'en').
     */
    public function getCurrentLocale(): string {
        return $this->locale;
    }

    /**
     * Retrieves a translation string for the given key.
     * Supports dot notation for nested keys. Replaces placeholders with provided parameters.
     *
     * @param string $key The translation key (e.g., 'messages.welcome').
     * @param array $params Associative array of placeholders => values (e.g., ['name' => 'Cline']). Placeholders in the string should be like ':name'.
     * @return string The translated string, or the key itself if the translation is not found.
     */
    public function get(string $key, array $params = []): string {
        // Look up the key in the flattened translations array. Default to the key itself if not found.
        $translation = $this->translations[$key] ?? $key;
        // Replace placeholders like :key with values from $params.
        return $this->replaceParams($translation, $params);
    }

    /**
     * Replaces placeholders in a string with given parameter values.
     * Placeholders are expected in the format ':key'.
     *
     * @param string $translation The string containing placeholders.
     * @param array $params Associative array ['key' => 'value'].
     * @return string The string with placeholders replaced.
     */
    private function replaceParams(string $translation, array $params): string {
        // Iterate through parameters and replace corresponding placeholders.
        foreach ($params as $key => $value) {
            $translation = str_replace(':' . $key, (string)$value, $translation); // Ensure value is string
        }
        return $translation;
    }

    /**
     * Flattens a multi-dimensional array into a single level array with dot notation keys.
     * Example: ['a' => ['b' => 'c']] becomes ['a.b' => 'c'].
     *
     * @param array $array The array to flatten.
     * @param string $prepend Internal use for recursion: prefix for keys.
     * @return array The flattened array.
     */
    private function array_dot(array $array, string $prepend = ''): array {
        $results = [];
        foreach ($array as $key => $value) {
            // If the value is an array and not empty, recurse deeper.
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, $this->array_dot($value, $prepend . $key . '.'));
            } else {
                // Otherwise, add the value with the dot-prefixed key.
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }
}
