<?php

namespace Webklex\CalMag;

/**
 * Class Config
 *
 * Provides a simple way to load and access configuration values from PHP files
 * stored in the `src/config/` directory. It uses a singleton pattern to ensure
 * configuration is loaded only once. Access to configuration values is provided
 * via a static `get` method or an instance `config` method, supporting dot notation
 * for nested keys (e.g., 'app.name').
 *
 * @package Webklex\CalMag
 * @method static mixed get(string $key, mixed $default = null) Allows static access like Config::get('app.key').
 */
class Config {

    /** @var array Holds the loaded configuration values. */
    protected array $config = [];

    /** @var ?Config Singleton instance of the Config class. */
    private static ?Config $instance = null;

    /**
     * Config constructor.
     * Private constructor to enforce singleton pattern. Loads configuration on instantiation.
     */
    private function __construct() {
        $this->load();
    }

    /**
     * Magic method to handle static calls (e.g., Config::get()).
     * Currently only supports 'get'.
     *
     * @param string $name Method name ('get').
     * @param array $arguments Arguments passed (key, default).
     * @return mixed The configuration value or default.
     */
    public static function __callStatic($name, $arguments) {
        return match ($name) {
            // Delegate static 'get' calls to the instance's 'config' method.
            'get' => self::getInstance()->config(...$arguments),
            default => null, // Return null for unsupported static methods.
        };
    }

    /**
     * Magic method to handle instance calls (e.g., $config->get()).
     * This seems redundant as the primary access is intended to be static or via the instance `config` method.
     *
     * @param string $name Method name ('get').
     * @param array $arguments Arguments passed (key, default).
     * @return mixed The configuration value or default.
     */
    public function __call($name, $arguments) {
        return match ($name) {
            // Delegate instance 'get' calls to the 'config' method.
            'get' => $this->config(...$arguments),
            default => null, // Return null for unsupported instance methods.
        };
    }

    /**
     * Gets the singleton instance of the Config class.
     * Creates the instance if it doesn't exist yet.
     *
     * @return Config The singleton Config instance.
     */
    public static function getInstance(): Config {
        // Create instance if it's the first call.
        if(self::$instance === null){
            self::$instance = new Config();
        }
        // Return the existing instance.
        return self::$instance;
    }

    /**
     * Loads configuration files from the `src/config/` directory.
     * Each file's name (without extension) becomes the top-level key,
     * and the returned array from the file becomes its value.
     *
     * @return void
     */
    private function load(): void {
        $config = [];
        // Find all .php files in the config directory.
        foreach (glob(__DIR__ . '/config/*.php') as $file) {
            // Get the filename without extension (e.g., 'app', 'fertilizers').
            $name = pathinfo($file, PATHINFO_FILENAME);
            // Require the file and store its returned array under the filename key.
            $config[$name] = require $file;
        }
        // Store the loaded configuration.
        $this->config = $config;
    }

    /**
     * Retrieves a configuration value using dot notation.
     *
     * @param string $key The configuration key (e.g., 'app.name', 'fertilizers.brand.product').
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The configuration value or the default value.
     */
    public function config(string $key, $default = null) {
        // Split the key into parts based on the dot separator.
        $parts = explode('.', $key);
        // Start with the full configuration array.
        $config = $this->config;
        // Traverse the array using the key parts.
        foreach ($parts as $part) {
            // Check if the current part exists as a key in the current level of the array.
            if (isset($config[$part])) {
                // Move deeper into the array.
                $config = $config[$part];
            } else {
                // Key part not found, return the default value.
                return $default;
            }
        }
        // Return the final value found after traversing all parts.
        return $config;
    }
}
