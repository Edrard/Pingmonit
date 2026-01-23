<?php

namespace Edrard\Pingmonit;

use Edrard\Exceptions\ConfigException;

/**
 * Helper class for accessing application configuration
 */
class Config
{
    private static $config = null;

    /**
     * Load configuration from a file
     *
     * @param string $configFile Path to config file
     * @return array
     * @throws Exception
     */
    public static function load($configFile = null)
    {
        if ($configFile === null) {
            $configFile = __DIR__ . '/../config/config.php';
        }

        if (!file_exists($configFile)) {
            throw new ConfigException("Config file not found: {$configFile}", 'error');
        }

        self::$config = require $configFile;

        if (!is_array(self::$config)) {
            throw new ConfigException('Invalid config file format', 'error');
        }

        return self::$config;
    }

    /**
     * Get a value from configuration
     *
     * @param string $key Key (dot notation supported)
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Key
     * @param mixed $value Value
     */
    public static function set($key, $value)
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key Key
     * @return bool
     */
    public static function has($key)
    {
        return self::get($key) !== null;
    }

    /**
     * Get full configuration array
     *
     * @return array|null
     */
    public static function all()
    {
        if (self::$config === null) {
            self::load();
        }

        return self::$config;
    }
}