<?php
// app/lib/ConfigRepository.php

/**
 * Repositorio para gestión de configuraciones
 * Adaptado para KumbiaPHP
 */
class ConfigRepository
{
    private $config_path;
    
    public function __construct()
    {
        $this->config_path = APP_PATH . 'config/';
    }
    
    /**
     * Obtener configuración
     */
    public function get($config_name, $with_defaults = false)
    {
        $config_file = $this->config_path . $config_name . '.php';
        
        if (!file_exists($config_file)) {
            return $with_defaults ? $this->get_default_config($config_name) : [];
        }
        
        try {
            $config = include $config_file;
            return is_array($config) ? $config : [];
        } catch (Exception $e) {
            Logger::error("Error cargando configuración {$config_name}: " . $e->getMessage());
            return $with_defaults ? $this->get_default_config($config_name) : [];
        }
    }
    
    /**
     * Guardar configuración
     */
    public function save($config_name, $data, $create_backup = true)
    {
        try {
            // Validar nombre
            if (!$this->is_valid_config_name($config_name)) {
                throw new Exception("Nombre de configuración inválido");
            }
            
            // Crear backup si es necesario
            if ($create_backup) {
                $this->create_backup($config_name);
            }
            
            // Generar contenido
            $config_file = $this->config_path . $config_name . '.php';
            $content = $this->generate_config_file($config_name, $data);
            
            // Guardar archivo
            if (file_put_contents($config_file, $content, LOCK_EX) === false) {
                throw new Exception("Error al escribir archivo");
            }
            
            // Cambiar permisos
            chmod($config_file, 0644);
            
            // Invalidar opcache si está habilitado
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($config_file, true);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Restaurar backup en caso de error
            if ($create_backup) {
                $this->restore_backup($config_name);
            }
            throw $e;
        }
    }
    
    /**
     * Exportar configuración
     */
    public function export($config_names, $format = 'json')
    {
        $data = [];
        
        foreach ($config_names as $name) {
            $config = $this->get($name);
            if (!empty($config)) {
                $data[$name] = $config;
            }
        }
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($format === 'php') {
            return "<?php\n\nreturn " . var_export($data, true) . ";\n";
        }
        
        throw new Exception("Formato no soportado: {$format}");
    }
    
    /**
     * Generar contenido de archivo de configuración
     */
    private function generate_config_file($config_name, $data)
    {
        $timestamp = date('Y-m-d H:i:s');
        $user = Auth::get('username') ?? 'system';
        
        return <<<PHP
<?php
/**
 * Configuración: {$config_name}
 * Generado: {$timestamp}
 * Usuario: {$user}
 * 
 * NO MODIFICAR MANUALMENTE
 * Use el panel de administración para cambios
 */

return {$this->var_export_safe($data)};

PHP;
    }
    
    /**
     * Exportación segura de variables
     */
    private function var_export_safe($var)
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = ($indexed ? '' : $this->var_export_safe($key) . ' => ') 
                     . $this->var_export_safe($value);
            }
            return '[' . implode(', ', $r) . ']';
        } elseif (is_string($var)) {
            return "'" . addcslashes($var, "\\'\$\n\r\t\v\f") . "'";
        } elseif (is_bool($var)) {
            return $var ? 'true' : 'false';
        } elseif (is_null($var)) {
            return 'null';
        } else {
            return var_export($var, true);
        }
    }
    
    /**
     * Validar nombre de configuración
     */
    private function is_valid_config_name($name)
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $name);
    }
    
    /**
     * Crear backup de configuración
     */
    private function create_backup($config_name)
    {
        $config_file = $this->config_path . $config_name . '.php';
        $backup_dir = APP_PATH . 'backups/config/';
        
        if (!file_exists($config_file)) {
            return false;
        }
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $backup_file = $backup_dir . $config_name . '_' . $timestamp . '.php';
        
        return copy($config_file, $backup_file);
    }
    
    /**
     * Restaurar desde backup
     */
    private function restore_backup($config_name)
    {
        $backup_dir = APP_PATH . 'backups/config/';
        $backups = glob($backup_dir . $config_name . '_*.php');
        
        if (empty($backups)) {
            return false;
        }
        
        rsort($backups);
        $latest_backup = $backups[0];
        $config_file = $this->config_path . $config_name . '.php';
        
        return copy($latest_backup, $config_file);
    }
    
    /**
     * Obtener configuración por defecto
     */
    private function get_default_config($config_name)
    {
        $defaults = [
            'auth' => [
                'authentication_modes' => ['credentials'],
                'credentials' => ['enabled' => true],
                'session' => ['lifetime' => 7200],
                'admin_roles' => ['admin']
            ],
            'app' => [
                'name' => 'Sistema de Gestión',
                'version' => '1.0.0',
                'debug' => false,
                'url' => 'http://localhost',
                'timezone' => 'America/Lima'
            ],
            'database' => [
                'development' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'username' => 'root'
                ]
            ]
        ];
        
        return $defaults[$config_name] ?? [];
    }
}