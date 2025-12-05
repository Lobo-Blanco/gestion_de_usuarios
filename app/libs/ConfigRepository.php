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
                'timezone' => 'Europe/Madrid'
            ],
            'database' => [
                'development' => [
                    'type' => 'mysql',
                    'host' => 'localhost',
                    'username' => 'root',
                    'name' => 'test'
                ]
            ]
        ];
        
        return $defaults[$config_name] ?? [];
    }

    /**
     * Obtener todas las bases de datos configuradas
     */
    public function getDatabases()
    {
        $databases_file = $this->config_path . 'databases.php';
        
        if (!file_exists($databases_file)) {
            return $this->getDefaultDatabases();
        }
        
        try {
            $databases = include $databases_file;
            return is_array($databases) ? $databases : [];
        } catch (Exception $e) {
            Logger::error("Error cargando databases: " . $e->getMessage());
            return $this->getDefaultDatabases();
        }
    }

    /**
     * Obtener la base de datos activa por defecto
     */
    public function getActiveDatabase()
    {
        // Obtener el nombre de la BD desde config.php
        $acrtive_b_name = Config::get('config.database') ?? 'default';

        // Obtener todas las configuraciones
        $databases = $this->getDatabases();
        
        if (!isset($databases[$active_db_name])) {
            // Si no existe, usar 'default' o la primera disponible
            $active_db_name = isset($databases['default']) ? 'default' : 
                             (!empty($databases) ? array_key_first($databases) : 'default');
        }
        
        return [
            'name' => $active_db_name,
            'config' => $databases[$active_db_name] ?? [],
            'is_default' => true
        ];
    }

    /**
     * Establecer una configuración de BD como activa
     */
    public function setActiveDatabase($db_name)
    {
        // Verificar que la configuración existe
        $databases = $this->getDatabases();
        if (!isset($databases[$db_name])) {
            throw new Exception("La configuración de BD '{$db_name}' no existe");
        }
        
        // Actualizar config.php
        return $this->updateConfigDatabase($db_name);
    }

    /**
     * Guardar/actualizar una configuración de BD
     */
    public function saveDatabase($db_name, $config, $set_as_active = false)
    {
        try {
            // Validar configuración
            $this->validateDatabaseConfig($config);
            
            // Obtener configuraciones existentes
            $databases = $this->getDatabases();
            $old_config = $databases[$db_name] ?? [];

            // Actualizar o añadir
            $databases[$db_name] = array_merge(
                $databases[$db_name] ?? [],
                $config
            );
            
            // Guardar databases.php
            $this->saveDatabasesFile($databases);

            // Guardar en historial
            $this->createHistoryEntry('database_' . $db_name, $config, 
                isset($old_config) ? 'modify' : 'create');

            // Si se solicita, establecer como activa
            if ($set_as_active) {
                $this->setActiveDatabase($db_name);
            }
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Error guardando BD {$db_name}: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar una configuración de BD
     */
    public function deleteDatabase($db_name)
    {
        // No permitir eliminar la configuración activa
        $active_db = $this->getActiveDatabase();
        if ($active_db['name'] === $db_name) {
            throw new Exception("No se puede eliminar la configuración activa");
        }
        
        // No permitir eliminar 'default' si es la única
        $databases = $this->getDatabases();
        if ($db_name === 'default' && count($databases) === 1) {
            throw new Exception("No se puede eliminar la única configuración disponible");
        }
        
        // Eliminar
        unset($databases[$db_name]);
        
        // Guardar
        $this->saveDatabasesFile($databases);
        
        return true;
    }
    
    /**
     * Actualizar config.php para cambiar la BD activa
     */
    private function updateConfigDatabase($db_name)
    {
        $config_file = $this->config_path . 'config.php';
        
        if (!file_exists($config_file)) {
            throw new Exception("Archivo config.php no encontrado");
        }
        
        // Leer contenido
        $content = file_get_contents($_config_file);
        
        // Buscar y reemplazar config.database
        $pattern = '/(config\.database\s*=\s*[\'"])([^\'"]*)([\'"])/';
        $replacement = '${1}' . $db_name . '${3}';
        
        $new_content = preg_replace($pattern, $replacement, $content);
        
        if ($new_content === null) {
            throw new Exception("Error al procesar config.php");
        }
        
        // Guardar
        if (file_put_contents($config_file, $new_content, LOCK_EX) === false) {
            throw new Exception("Error al guardar config.php");
        }
        
        // Invalidar opcache
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($config_file, true);
        }
        
        return true;
    }
    
    /**
     * Guardar archivo databases.php
     */
    private function saveDatabasesFile($databases)
    {
        $databases_file = $this->config_path . 'databases.php';
        
        // Guardar versión actual en historial antes de modificar
        if (file_exists($databases_file)) {
            $current_content = file_get_contents($databases_file);
            $timestamp = date('Ymd_His');
            $history_file = APP_PATH . 'temp/config_history/databases_full_' . $timestamp . '.php';
            
            file_put_contents($history_file, $current_content, LOCK_EX);
        }

        // Crear contenido
        $content = $this->generateDatabasesContent($databases);
        
        // Crear backup
        $this->createBackup('databases.php');
        
        // Guardar
        if (file_put_contents($databases_file, $content, LOCK_EX) === false) {
            throw new Exception("Error al guardar databases.php");
        }
        
        // Invalidar opcache
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($databases_file, true);
        }
        
        return true;
    }
    
    /**
     * Generar contenido para databases.php
     */
    private function generateDatabasesContent($databases)
    {
        $timestamp = date('Y-m-d H:i:s');
        $user = Auth::get('username') ?? 'system';
        
        return <<<PHP
<?php
/**
 * Configuraciones de Bases de Datos
 * Generado: {$timestamp}
 * Usuario: {$user}
 * 
 * Este archivo contiene todas las configuraciones de conexión a BD
 * La configuración activa se define en config.php -> config.database
 */

return {$this->varExport($databases)};

PHP;
    }
    
    /**
     * Validar configuración de BD
     */
    private function validateDatabaseConfig($config)
    {
        $required = ['type', 'host', 'database'];
        
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("El campo '{$field}' es requerido");
            }
        }
        
        // Validar type
        $valid_types = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];
        if (!in_array($config['type'], $valid_types)) {
            throw new Exception("type no válido: {$config['type']}");
        }
        
        return true;
    }
    
    /**
     * Crear archivo databases.php por defecto
     */
    private function createDefaultDatabasesFile()
    {
        $default_config = [
            'default' => [
                'type' => 'mysql',
                'host' => 'localhost',
                'username' => 'root',
                'password' => '',
                'database' => 'test',
                'port' => 3306,
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci'
            ]
        ];
        
        $this->saveDatabasesFile($default_config);
        
        return $default_config;
    }
    
    /**
     * Crear backup de archivo
     */
    private function createBackup($filename)
    {
        $source = $this->config_path . $filename;
        $backup_dir = APP_PATH . 'temp/config_backups/';
        
        if (!file_exists($source)) {
            return false;
        }
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $backup_file = $backup_dir . $filename . '_' . $timestamp;
        
        return copy($source, $backup_file);
    }
    
    /**
     * Exportar variable en formato PHP
     */
    private function varExport($var, $indent = '')
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];
            foreach ($var as $key => $value) {
                $r[] = $indent . '    ' 
                     . ($indexed ? '' : $this->varExport($key) . ' => ') 
                     . $this->varExport($value, $indent . '    ');
            }
            return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
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
     * Obtener historial de cambios de configuración
     */
    public function getConfigHistory($config_name = null, $limit = 50)
    {
        $history_dir = APP_PATH . 'temp/config_history/';
        
        if (!is_dir($history_dir)) {
            return [];
        }
        
        $history = [];
        
        if ($config_name) {
            // Historial específico de una configuración
            $pattern = $history_dir . $config_name . '_*.php';
            $files = glob($pattern);
        } else {
            // Todo el historial
            $files = glob($history_dir . '*.php');
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Limitar resultados
        $files = array_slice($files, 0, $limit);
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Extraer información del nombre del archivo
            // Formato: config_20241225_143022.php o database_default_20241225_143022.php
            preg_match('/(.+?)_(\d{8})_(\d{6})\.php$/', $filename, $matches);
            
            if ($matches) {
                $config = $matches[1];
                $date = $matches[2];
                $time = $matches[3];
                
                $timestamp = DateTime::createFromFormat('Ymd His', $date . ' ' . $time);
                
                $history[] = [
                    'config' => $config,
                    'filename' => $filename,
                    'filepath' => $file,
                    'timestamp' => $timestamp ? $timestamp->format('Y-m-d H:i:s') : $date . ' ' . $time,
                    'size' => filesize($file),
                    'user' => $this->extractUserFromFile($file)
                ];
            }
        }
        
        return $history;
    }

    /**
     * Restaurar configuración desde historial
     */
    public function restoreFromHistory($filename)
    {
        $history_dir = APP_PATH . 'temp/config_history/';
        $history_file = $history_dir . $filename;
        
        if (!file_exists($history_file)) {
            throw new Exception("Archivo de historial no encontrado");
        }
        
        // Determinar qué configuración restaurar
        preg_match('/(.+?)_\d{8}_\d{6}\.php$/', $filename, $matches);
        if (!$matches) {
            throw new Exception("Formato de archivo de historial inválido");
        }
        
        $config_name = $matches[1];
        
        // Determinar tipo de configuración
        if (strpos($config_name, 'database_') === 0) {
            // Es una configuración de base de datos específica
            $db_name = substr($config_name, 9); // Remover 'database_'
            $config_data = include $history_file;
            
            return $this->saveDatabase($db_name, $config_data, false);
        } else {
            // Es un archivo de configuración general
            $config_file = $this->config_path . $config_name . '.php';
            
            // Crear backup actual antes de restaurar
            $this->createBackup($config_name . '.php');
            
            // Copiar desde historial
            if (!copy($history_file, $config_file)) {
                throw new Exception("Error al restaurar configuración");
            }
            
            return true;
        }
    }

    /**
     * Crear entrada en historial
     */
    private function createHistoryEntry($config_name, $data, $action = 'modify')
    {
        $history_dir = APP_PATH . 'temp/config_history/';
        
        if (!is_dir($history_dir)) {
            mkdir($history_dir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $user = Auth::get('username') ?? 'system';
        
        $history_file = $history_dir . $config_name . '_' . $timestamp . '.php';
        
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Historial: {$config_name}\n";
        $content .= " * Acción: {$action}\n";
        $content .= " * Fecha: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Usuario: {$user}\n";
        $content .= " */\n\n";
        $content .= "return " . $this->varExport($data) . ";\n";
        
        return file_put_contents($history_file, $content, LOCK_EX) !== false;
    }

    /**
     * Extraer usuario del archivo de historial
     */
    private function extractUserFromFile($filepath)
    {
        $content = file_get_contents($filepath);
        
        if (preg_match('/Usuario:\s*([^\n]+)/', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return 'Desconocido';
    }

    /**
     * Limpiar historial antiguo
     */
    public function cleanOldHistory($days_to_keep = 30)
    {
        $history_dir = APP_PATH . 'temp/config_history/';
        
        if (!is_dir($history_dir)) {
            return 0;
        }
        
        $files = glob($history_dir . '*.php');
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * Importar configuraciones desde archivo
     */
    public function importConfig($file_content, $format = 'auto', $options = [])
    {
        try {
            // Determinar formato si es auto
            if ($format === 'auto') {
                $format = $this->detectFormat($file_content);
            }
            
            // Parsear según formato
            $data = $this->parseImport($file_content, $format);
            
            if (empty($data)) {
                throw new Exception("No se encontraron datos válidos para importar");
            }
            
            // Validar estructura
            $this->validateImportData($data);
            
            // Procesar importación según opciones
            return $this->processImport($data, $options);
            
        } catch (Exception $e) {
            Logger::error("Error en importación: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detectar formato del archivo
     */
    private function detectFormat($content)
    {
        // Intentar JSON
        json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            return 'json';
        }
        
        // Intentar PHP array (buscar 'return array(' o 'return [')
        if (preg_match('/^\s*<\?php\s+return\s+(array\(|\[)/', $content)) {
            return 'php';
        }
        
        // Intentar INI
        if (preg_match('/^\s*\[[^\]]+\]\s*[\w]+\s*=/m', $content)) {
            return 'ini';
        }
        
        // Intentar YAML (básico)
        if (preg_match('/^\s*[\w-]+:\s*(?:[\w@\.-]+|\[)/m', $content)) {
            return 'yaml';
        }
        
        throw new Exception("Formato de archivo no reconocido");
    }

    /**
     * Parsear contenido según formato
     */
    private function parseImport($content, $format)
    {
        switch ($format) {
            case 'json':
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("JSON inválido: " . json_last_error_msg());
                }
                return $data;
                
            case 'php':
                // Crear archivo temporal para incluir
                $temp_file = tempnam(sys_get_temp_dir(), 'import_');
                file_put_contents($temp_file, $content);
                
                // Incluir archivo
                $data = include $temp_file;
                
                // Limpiar
                unlink($temp_file);
                
                if (!is_array($data)) {
                    throw new Exception("El archivo PHP debe retornar un array");
                }
                return $data;
                
            case 'ini':
                return parse_ini_string($content, true);
                
            case 'yaml':
                if (function_exists('yaml_parse')) {
                    return yaml_parse($content);
                } else {
                    // Implementación básica para YAML simple
                    return $this->parseSimpleYaml($content);
                }
                
            default:
                throw new Exception("Formato no soportado: {$format}");
        }
    }

    /**
     * Parsear YAML simple (sin dependencias)
     */
    private function parseSimpleYaml($content)
    {
        $lines = explode("\n", $content);
        $result = [];
        $current_section = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Saltar comentarios y líneas vacías
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Secciones [section]
            if (preg_match('/^\[([^\]]+)\]\s*$/', $line, $matches)) {
                $current_section = $matches[1];
                $result[$current_section] = [];
                continue;
            }
            
            // Clave: valor
            if (preg_match('/^([\w-]+):\s*(.+)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                
                // Procesar valores especiales
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                elseif ($value === 'null') $value = null;
                elseif (is_numeric($value)) $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                
                if ($current_section) {
                    $result[$current_section][$key] = $value;
                } else {
                    $result[$key] = $value;
                }
            }
        }
        
        return $result;
    }

    /**
     * Validar datos de importación
     */
    private function validateImportData($data)
    {
        if (!is_array($data)) {
            throw new Exception("Los datos deben ser un array");
        }
        
        // Validar estructura básica
        $valid_keys = ['databases', 'app', 'auth', 'config', 'database'];
        $has_valid_data = false;
        
        foreach ($data as $key => $value) {
            if (in_array($key, $valid_keys) || preg_match('/^database_/', $key)) {
                $has_valid_data = true;
                
                // Validar configuraciones de BD específicas
                if ($key === 'databases' || preg_match('/^database_/', $key)) {
                    $this->validateDatabaseImport($value, $key);
                }
            }
        }
        
        if (!$has_valid_data) {
            throw new Exception("No se encontraron datos de configuración válidos");
        }
        
        return true;
    }

    /**
     * Validar importación de base de datos
     */
    private function validateDatabaseImport($data, $key)
    {
        if (!is_array($data)) {
            throw new Exception("La configuración de BD debe ser un array");
        }
        
        // Si es 'databases', debe ser un array de configuraciones
        if ($key === 'databases') {
            foreach ($data as $db_name => $db_config) {
                if (!is_array($db_config)) {
                    throw new Exception("La configuración de BD '{$db_name}' debe ser un array");
                }
                $this->validateDatabaseConfig($db_config);
            }
        } else {
            // Es una configuración individual
            $this->validateDatabaseConfig($data);
        }
    }

    /**
     * Procesar importación
     */
    private function processImport($data, $options)
    {
        $results = [
            'imported' => [],
            'skipped' => [],
            'errors' => []
        ];
        
        $overwrite = $options['overwrite'] ?? false;
        $backup = $options['backup'] ?? true;
        
        foreach ($data as $key => $value) {
            try {
                // Determinar qué tipo de configuración es
                if ($key === 'databases') {
                    // Importar múltiples bases de datos
                    foreach ($value as $db_name => $db_config) {
                        $result = $this->importDatabase($db_name, $db_config, $overwrite, $backup);
                        if ($result['success']) {
                            $results['imported'][] = "database:{$db_name}";
                        } else {
                            $results['skipped'][] = "database:{$db_name} - " . $result['message'];
                        }
                    }
                } elseif (preg_match('/^database_/', $key)) {
                    // Importar configuración de BD específica
                    $db_name = substr($key, 9); // Remover 'database_'
                    $result = $this->importDatabase($db_name, $value, $overwrite, $backup);
                    if ($result['success']) {
                        $results['imported'][] = "database:{$db_name}";
                    } else {
                        $results['skipped'][] = "database:{$db_name} - " . $result['message'];
                    }
                } elseif (in_array($key, ['app', 'auth', 'config'])) {
                    // Importar archivo de configuración específico
                    $result = $this->importConfigFile($key, $value, $overwrite, $backup);
                    if ($result['success']) {
                        $results['imported'][] = $key;
                    } else {
                        $results['skipped'][] = "{$key} - " . $result['message'];
                    }
                }
            } catch (Exception $e) {
                $results['errors'][] = "{$key}: " . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Importar configuración de base de datos
     */
    private function importDatabase($db_name, $config, $overwrite, $backup)
    {
        // Obtener configuraciones existentes
        $databases = $this->getDatabases();
        
        // Verificar si ya existe
        if (isset($databases[$db_name]) && !$overwrite) {
            return [
                'success' => false,
                'message' => 'Ya existe (usar overwrite para sobrescribir)'
            ];
        }
        
        // Validar configuración
        $this->validateDatabaseConfig($config);
        
        // Guardar
        $this->saveDatabase($db_name, $config, false);
        
        return ['success' => true];
    }

    /**
     * Importar archivo de configuración
     */
    private function importConfigFile($config_name, $data, $overwrite, $backup)
    {
        $config_file = $this->config_path . $config_name . '.php';
        
        // Verificar si ya existe
        if (file_exists($config_file) && !$overwrite) {
            return [
                'success' => false,
                'message' => 'Ya existe (usar overwrite para sobrescribir)'
            ];
        }
        
        // Crear backup si es necesario
        if ($backup && file_exists($config_file)) {
            $this->createBackup($config_name . '.php');
        }
        
        // Generar contenido
        $content = $this->generateConfigFile($config_name, $data);
        
        // Guardar
        if (file_put_contents($config_file, $content, LOCK_EX) === false) {
            throw new Exception("Error al guardar archivo");
        }
        
        return ['success' => true];
    }

    /**
     * Generar archivo de configuración
     */
    private function generateConfigFile($config_name, $data)
    {
        $timestamp = date('Y-m-d H:i:s');
        $user = Auth::get('username') ?? 'import';
        
        return <<<PHP
    <?php
    /**
     * Configuración: {$config_name}
     * Importado: {$timestamp}
     * Usuario: {$user}
     * 
     * Generado automáticamente desde importación
     */

    return {$this->varExport($data)};

    PHP;
    }

    /**
     * Obtener plantillas de configuración
     */
    public function getConfigTemplates()
    {
        return [
            'database_simple' => [
                'name' => 'Base de Datos Simple',
                'description' => 'Configuración básica de MySQL',
                'content' => json_encode([
                    'databases' => [
                        'default' => [
                            'driver' => 'mysql',
                            'host' => 'localhost',
                            'port' => 3306,
                            'database' => 'mi_base_datos',
                            'username' => 'root',
                            'password' => '',
                            'charset' => 'utf8'
                        ]
                    ]
                ], JSON_PRETTY_PRINT)
            ],
            'database_multiple' => [
                'name' => 'Múltiples Bases de Datos',
                'description' => 'Configuraciones para desarrollo y producción',
                'content' => json_encode([
                    'databases' => [
                        'development' => [
                            'driver' => 'mysql',
                            'host' => 'localhost',
                            'database' => 'app_dev',
                            'username' => 'root',
                            'password' => '',
                            'charset' => 'utf8'
                        ],
                        'production' => [
                            'driver' => 'mysql',
                            'host' => 'db.produccion.com',
                            'database' => 'app_prod',
                            'username' => 'prod_user',
                            'password' => 'secreto',
                            'charset' => 'utf8mb4'
                        ]
                    ]
                ], JSON_PRETTY_PRINT)
            ],
            'app_config' => [
                'name' => 'Configuración de Aplicación',
                'description' => 'Configuración general del sistema',
                'content' => json_encode([
                    'app' => [
                        'name' => 'Mi Aplicación',
                        'debug' => false,
                        'url' => 'https://midominio.com',
                        'timezone' => 'Europe/Madrid'
                    ]
                ], JSON_PRETTY_PRINT)
            ]
        ];
    }
}