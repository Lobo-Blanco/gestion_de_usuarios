<?php
// app/lib/Session.php

/**
 * Helper para manejo de sesiones
 */
class Session
{
    /**
     * Iniciar sesión
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Obtener valor de sesión
     */
    public static function get($key, $default = null)
    {
        self::start();
        
        if (strpos($key, '.') !== false) {
            // Acceso profundo (ej: 'user.auth.id')
            $keys = explode('.', $key);
            $value = $_SESSION;
            
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }
            
            return $value;
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Establecer valor de sesión
     */
    public static function set($key, $value)
    {
        self::start();
        
        if (strpos($key, '.') !== false) {
            // Establecer profundamente
            $keys = explode('.', $key);
            $lastKey = array_pop($keys);
            $array = &$_SESSION;
            
            foreach ($keys as $k) {
                if (!isset($array[$k]) || !is_array($array[$k])) {
                    $array[$k] = [];
                }
                $array = &$array[$k];
            }
            
            $array[$lastKey] = $value;
        } else {
            $_SESSION[$key] = $value;
        }
    }
    
    /**
     * Verificar si existe clave
     */
    public static function has($key)
    {
        self::start();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $_SESSION;
            
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return false;
                }
            }
            
            return true;
        }
        
        return isset($_SESSION[$key]);
    }
    
    /**
     * Eliminar clave de sesión
     */
    public static function delete($key)
    {
        self::start();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $lastKey = array_pop($keys);
            $array = &$_SESSION;
            
            foreach ($keys as $k) {
                if (isset($array[$k]) && is_array($array[$k])) {
                    $array = &$array[$k];
                } else {
                    return;
                }
            }
            
            unset($array[$lastKey]);
        } else {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destruir sesión
     */
    public static function destroy()
    {
        self::start();
        session_destroy();
        $_SESSION = [];
    }
    
    /**
     * Regenerar ID de sesión
     */
    public static function regenerate($deleteOld = false)
    {
        self::start();
        session_regenerate_id($deleteOld);
    }
    
    /**
     * Obtener ID de sesión
     */
    public static function id()
    {
        self::start();
        return session_id();
    }
    
    /**
     * Obtener todos los datos de sesión
     */
    public static function all()
    {
        self::start();
        return $_SESSION;
    }
    
    /**
     * Limpiar sesión (mantener algunas claves)
     */
    public static function clear($keep = [])
    {
        self::start();
        
        $backup = [];
        foreach ($keep as $key) {
            if (self::has($key)) {
                $backup[$key] = self::get($key);
            }
        }
        
        session_unset();
        
        foreach ($backup as $key => $value) {
            self::set($key, $value);
        }
    }
    
    /**
     * Flash message - mensaje para una sola petición
     */
    public static function flash($key, $value = null)
    {
        self::start();
        
        if ($value === null) {
            // Obtener y eliminar
            $value = self::get('flash.' . $key);
            self::delete('flash.' . $key);
            return $value;
        }
        
        // Establecer
        self::set('flash.' . $key, $value);
    }
    
    /**
     * Verificar si hay flash message
     */
    public static function hasFlash($key)
    {
        return self::has('flash.' . $key);
    }
}