<?php
// app/helpers/config_helper.php

/**
 * Helper para trabajar con configuraciones
 */

if (!function_exists('config')) {
    /**
     * Obtener valor de configuración
     */
    function config($key, $default = null)
    {
        static $configs = [];
        
        // Parsear key (ej: 'app.name' o 'database.development.host')
        $parts = explode('.', $key);
        $configName = array_shift($parts);
        
        if (!isset($configs[$configName])) {
            $configFile = APP_PATH . "config/{$configName}.php";
            
            if (!file_exists($configFile)) {
                return $default;
            }
            
            $configs[$configName] = require $configFile;
        }
        
        $value = $configs[$configName];
        
        // Buscar en profundidad
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }
        
        return $value;
    }
}

if (!function_exists('config_set')) {
    /**
     * Establecer valor de configuración temporalmente
     */
    function config_set($key, $value)
    {
        $parts = explode('.', $key);
        $configName = array_shift($parts);
        
        // Cargar configuración actual
        $configFile = APP_PATH . "config/{$configName}.php";
        if (!file_exists($configFile)) {
            return false;
        }
        
        $config = require $configFile;
        $target = &$config;
        
        // Navegar hasta el último nivel
        foreach ($parts as $part) {
            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }
            $target = &$target[$part];
        }
        
        $target = $value;
        return true;
    }
}

if (!function_exists('config_has')) {
    /**
     * Verificar si existe configuración
     */
    function config_has($key)
    {
        return config($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }
}