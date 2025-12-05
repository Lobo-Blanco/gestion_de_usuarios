<?php
// app/controllers/configuracion_controller.php
Load::Lib("ConfigRepository");
Load::Lib("ConfigValidator");
Load::Lib("AuditLogger");
Load::Lib("FlashHelper");

require APP_PATH . "controllers/admin_controller.php";
/*
 * Controlador de Configuración del Sistema
 * Para KumbiaPHP
 */
class ConfiguracionController extends AdminController
{
    /**
     * @var ConfigRepository
     */
    private $config_repository;
    
    /**
     * @var ConfigValidator
     */
    private $validator;
    
    /**
     * @var AuditLogger
     */
    private $audit_logger;
    
    /**
     * Before filter específico para configuración
     */
    protected function before_filter()
    {
        parent::before_filter();

        // Inicializar servicios
        $this->config_repository = new ConfigRepository();
        $this->validator = new ConfigValidator();
        $this->audit_logger = new AuditLogger();
        
        // Configurar menú activo
        $this->active_menu = 'configuracion';

        View::template("configuracion");
    }


    /**
     * Depurador de métodos en clases PHP
     * Uso: php debug_metodo.php
     */

    public function debugMetodo($objeto, $metodo) {
        $texto = "";

        $texto .= "=== DEPURANDO MÉTODO '$metodo' ===<br>";
        $texto .= "Clase del objeto: " . get_class($objeto) . "<br>";

        // 1. Verificar si el método existe en la clase o padres
        if (method_exists($objeto, $metodo)) {
            $texto .= "✔ El método '$metodo' existe en la clase o en un padre.<br>";
        } else {
            $texto .= "❌ El método '$metodo' NO existe en la clase ni en sus padres.<br>";
        }

        // 2. Mostrar métodos accesibles públicamente
        $texto .= "<br>Métodos públicos disponibles:<br>";
        print_r(get_class_methods($objeto));

        // 3. Mostrar jerarquía de herencia
        $padre = get_parent_class($objeto);
        if ($padre) {
            $texto .= "<br>Clase padre: $padre<br>";
            $texto .= "Métodos del padre:<br>";
            print_r(get_class_methods($padre), true);
        } else {
            $texto .= "<br>(No tiene clase padre)<br>";
        }

        // 4. Revisar si el método podría estar en una propiedad-objeto
        $texto .= "<br>Buscando propiedades que sean objetos...<br>";
        foreach (get_object_vars($objeto) as $prop => $valor) {
            if (is_object($valor)) {
                $texto .= "Propiedad \$$prop es instancia de " . get_class($valor) . "<br>";
                if (method_exists($valor, $metodo)) {
                    $texto .= "⚠ El método '$metodo' está en el objeto de la propiedad \$$prop<br>";
                }
            }
        }
        $texto .= "=== FIN DEPURACIÓN ===<br><br>";

        return $texto;
    }

    public function zindex() {
        // Probar depuración
        $this->title = $this->debugMetodo($this, "check_auth");
    }

    /**
     * Panel de configuración principal
     */
    public function index()
    {
        // Verificar permiso para acceder al módulo de configuración
        if (!$this->has_permission('configuracion', 'access')) {
            $this->handle_unauthorized_access('configuracion.access');
        }
        
        try {
            $this->title = 'Dashboard de Configuración';
            
            // Estadísticas
            $this->stats = [
                'total_configs' => count(glob(APP_PATH . 'config/*.php')),
                'last_backup' => $this->get_last_backup_date(),
                'cache_size' => $this->get_cache_size_readable(),
                'system_status' => $this->get_system_health_status()
            ];
            
            // Solo administradores ven información detallada
            if ($this->is_admin) {
                $this->recent_changes = $this->audit_logger->get_recent_changes('configuracion', 5);
            }
            
        } catch (Exception $e) {
            Flash::error('Error al cargar dashboard');
            Logger::error($e->getMessage());
        }
    }
    
    /**
     * Configuración de autenticación
     */
    public function autenticacion()
    {
        // Verificar permiso específico
        if (!$this->has_permission('configuracion', 'auth')) {
            $this->handle_unauthorized_access('configuracion.auth');
        }
        
        $this->title = 'Configuración de Autenticación';
        
        if (Input::hasPost('guardar_auth')) {
            $this->process_auth_config();
        }

        $this->config_auth = $this->config_repository->get('auth', true);

        // 1. Modos de autenticación disponibles
        $this->auth_modes = Config::get('auth.authentication_modes', ['remote_user', 'credentials', 'both']);
        
        // 2. Modo actual seleccionado (puede ser array o string)
        $this->selected_modes = $this->config_auth['authentication_modes'] ?? ['credentials'];
        if (!is_array($this->selected_modes)) {
            $this->selected_modes = [$this->selected_modes];
        }
        
        // 3. Roles disponibles para admin_roles
        $this->available_roles = $this->get_available_roles();
        
        // 4. Roles admin seleccionados
        $this->selected_admin_roles = $this->config_auth['admin_roles'] ?? ['admin'];
        if (!is_array($this->selected_admin_roles)) {
            $this->selected_admin_roles = [$this->selected_admin_roles];
        }
        
        // 5. Variables booleanas para checkboxes
        $this->remote_user_enabled = $this->config_auth['remote_user']['enabled'] ?? false;
        $this->credentials_enabled = $this->config_auth['credentials']['enabled'] ?? true;
        $this->allow_registration = $this->config_auth['credentials']['allow_registration'] ?? false;
        $this->auto_create_user = $this->config_auth['remote_user']['auto_create_user'] ?? true;

        $this->config_auth = $this->config_repository->get('auth', true);
        $this->available_roles = $this->get_available_roles();
    }
    
    /**
     * Configuración de la aplicación
     */
    public function aplicacion()
    {
        if (!$this->has_permission('configuracion', 'app')) {
            $this->handle_unauthorized_access('configuracion.app');
        }
        
        $this->title = 'Configuración de la Aplicación';
        
        $this->config_app = $this->config_repository->get('app', true);

        // systeminfo
        $this->systemInfo = $this->get_system_info();
        // locales/idiomas
        $this->locales = $this->get_available_locales();

        // zonas horarias
        $this->timezones = $this->get_available_timezones(); 
        
        if (Input::hasPost('guardar_app')) {
            $this->process_app_config();
        }

    }
    
    public function base_datos()
    {
        $this->title = 'Configuración de Base de Datos';
        
        $configRepo = new ConfigRepository();
        
        // Obtener datos
        $this->databases = $configRepo->getDatabases();
        $this->active_db = $configRepo->getActiveDatabase();
        
        // Procesar formulario
        if (Input::hasPost('guardar_db')) {
            $this->saveDatabase($configRepo);
        }
        
        if (Input::hasPost('eliminar_db')) {
            $this->deleteDatabase($configRepo);
        }
        
        if (Input::hasPost('set_active_db')) {
            $this->setActiveDatabase($configRepo);
        }
    }
    
    private function saveDatabase($configRepo)
    {
        try {
            $db_name = Input::post('db_name', 'default');
            $is_new = Input::post('is_new', false);
            
            $config = [
                'type' => Input::post('db_type'),
                'host' => Input::post('db_host'),
                'port' => Input::post('db_port', 3306),
                'username' => Input::post('db_username'),
                'password' => Input::post('db_password'),
                'database' => Input::post('db_database'),
                'charset' => Input::post('db_charset', 'utf8')
            ];
            
            // Opciones adicionales según type
            if ($config['type'] === 'mysql') {
                $config['collation'] = Input::post('db_collation', 'utf8_unicode_ci');
            }
            
            $set_as_active = Input::post('set_as_active', false) || $is_new;
            
            // Guardar
            $configRepo->saveDatabase($db_name, $config, $set_as_active);
            
            Flash::success("Configuración '{$db_name}' guardada correctamente.");
            
            if ($set_as_active) {
                Flash::info("La configuración '{$db_name}' se ha establecido como activa.");
            }
            
            Redirect::to('configuracion/base_datos');
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    private function deleteDatabase($configRepo)
    {
        try {
            $db_name = Input::post('db_name');
            
            if (empty($db_name)) {
                Flash::error('No se especificó la configuración a eliminar');
                return;
            }
            
            $configRepo->deleteDatabase($db_name);
            Flash::success("Configuración '{$db_name}' eliminada correctamente.");
            
            Redirect::to('configuracion/base_datos');
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    private function setActiveDatabase($configRepo)
    {
        try {
            $db_name = Input::post('active_db_name');
            
            if (empty($db_name)) {
                Flash::error('No se especificó la configuración');
                return;
            }
            
            $configRepo->setActiveDatabase($db_name);
            Flash::success("Configuración '{$db_name}' establecida como activa.");
            
            Redirect::to('configuracion/base_datos');
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * API para obtener configuración específica (AJAX)
     */
    public function get_config($db_name)
    {
        $this->auto_render = false;
        
        try {
            $configRepo = new ConfigRepository();
            $databases = $configRepo->getDatabases();
            
            if (isset($databases[$db_name])) {
                echo json_encode([
                    'success' => true,
                    'config' => $databases[$db_name]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Configuración no encontrada'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Configuración de ACL
     */
    public function acl()
    {
        if (!$this->has_permission('configuracion', 'acl')) {
            $this->handle_unauthorized_access('configuracion.acl');
        }
        
        $this->title = 'Configuración de Control de Acceso';
        
        if (Input::hasPost('guardar_acl')) {
            $this->process_acl_config();
        }
        
        $this->config_acl = $this->config_repository->get('acl', true);
    }
    
    /**
     * Backup de base de datos
     */
    public function backup()
    {
        // Solo administradores pueden hacer backup
        if (!$this->is_user_admin()) {
            $this->handle_unauthorized_access('configuracion.backup');
        }
        
        $this->title = 'Backup de Base de Datos';
        
        if (Input::hasPost('generar_backup')) {
            $this->generar_backup();
        }
        
        $this->backups = $this->listar_backups();
    }
    
    /**
     * Limpiar cache del sistema
     */
    public function limpiar_cache()
    {
        if (!$this->has_permission('configuracion', 'cache.clear')) {
            $this->handle_unauthorized_access('configuracion.cache.clear');
        }
        
        $this->title = 'Limpiar Cache del Sistema';
        
        if (Input::hasPost('confirmar')) {
            $this->process_cache_cleaning();
        }
    }
    
    /**
     * Información del sistema
     */
    public function sistema()
    {
        if (!$this->has_permission('configuracion', 'system.info')) {
            $this->handle_unauthorized_access('configuracion.system.info');
        }
        
        $this->title = 'Información del Sistema';
        
        $this->info_php = [
            'version' => phpversion(),
            'sapi' => php_sapi_name(),
            'memory_limit' => ini_get('memory_limit')
        ];
        
        $this->info_server = $_SERVER;
        $this->info_app = Config::get('app');
    }
    
    /**
     * ============================================
     * MÉTODOS PRIVADOS
     * ============================================
     */
    
    /**
     * Procesar configuración de autenticación
     */
    private function process_auth_config()
    {
        try {
            // Obtener modos seleccionados (puede venir como array o string)
            $modes = Input::post('authentication_modes', []);
            if (!is_array($modes)) {
                $modes = [$modes];
            }

            $config_data = [
                'authentication_modes' => $modes,
                'remote_user' => [
                    'enabled' => (bool)Input::post('remote_user_enabled', 0),
                    'field_name' => Input::post('remote_user_field', 'REMOTE_USER'),
                    'auto_create_user' => (bool)Input::post('auto_create_user', 0),
                    'default_role' => Input::post('default_role', 'usuario')
                ],
                'credentials' => [
                    'enabled' => (bool)Input::post('credentials_enabled', 1),
                    'allow_registration' => (bool)Input::post('allow_registration', 0),
                    'verify_email' => (bool)Input::post('verify_email', 0),
                    'auto_activate' => (bool)Input::post('auto_activate', 0),
                    'allow_restore_password_by_email' => (bool)Input::post('allow_restore_password', 1)
                ],
                'session' => [
                    'namespace' => Input::post('session_namespace', 'app_auth'),
                    'key' => Input::post('session_key', 'app_user_session'),
                    'lifetime' => (int)Input::post('session_lifetime', 7200)
                ]
            ];
            
            // Obtener roles admin seleccionados
            $admin_roles = Input::post('admin_roles', []);
            if (!is_array($admin_roles)) {
                $admin_roles = [$admin_roles];
            }
            $config_data['admin_roles'] = $admin_roles;
            
            // Mantener algoritmo de hash
            if (isset($this->config_auth['auth_algorithm'])) {
                $config_data['auth_algorithm'] = $this->config_auth['auth_algorithm'];
            }
            
            // Mantener configuración de email si existe
            if (isset($this->config_auth['email'])) {
                $config_data['email'] = $this->config_auth['email'];
            }
            
            if ($this->config_repository->save('auth', $config_data)) {
                Flash::success('Configuración de autenticación guardada');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'auth',
                    'Configuración de autenticación actualizada'
                );
                
                // Recargar página para mostrar cambios
                Redirect::toAction('autenticacion');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
            Logger::error('Error en configuración de autenticación: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar configuración de aplicación
     */
    private function process_app_config()
    {
        try {
            $config_data = [
                'name' => Input::post('app_name', 'Sistema de Gestión'),
                'version' => Input::post('app_version', '1.0'),
                'debug' => Input::post('app_debug', 0),
                'url' => Input::post('app_url', 'http://localhost'),
                'timezone' => Input::post('app_timezone', 'Europe/Madrid'),
                'locale' => Input::post('app_locale', 'es_ES')
            ];
            
            if ($this->config_repository->save('app', $config_data)) {
                Flash::success('Configuración de la aplicación guardada');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'app',
                    'Configuración de aplicación actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar configuración de base de datos
     */
    private function process_database_config()
    {
        try {
            $config_data = [
                'development' => [
                    'type' => Input::post('db_type', 'mysql'),
                    'host' => Input::post('db_host', 'localhost'),
                    'username' => Input::post('db_username', 'root'),
                    'password' => Input::post('db_password', ''),
                    'database' => Input::post('db_database', 'sistema')
                ]
            ];
            
            if ($this->config_repository->save('database', $config_data)) {
                Flash::success('Configuración de base de datos guardada');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'database',
                    'Configuración de base de datos actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar configuración de ACL
     */
    private function process_acl_config()
    {
        try {
            $config_data = [
                'default_role' => Input::post('acl_default_role', 'usuario'),
                'cache_enabled' => Input::post('acl_cache_enabled', 1)
            ];
            
            if ($this->config_repository->save('acl', $config_data)) {
                Flash::success('Configuración de ACL guardada');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'acl',
                    'Configuración de ACL actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar limpieza de cache
     */
    private function process_cache_cleaning()
    {
        try {
            $tipos = Input::post('tipos', []);
            $resultados = [];
            
            if (in_array('vistas', $tipos)) {
                $cache_path = APP_PATH . 'temp/cache/views/';
                if (is_dir($cache_path)) {
                    $this->eliminar_directorio($cache_path);
                    $resultados[] = 'Cache de vistas limpiada';
                }
            }
            
            if (in_array('datos', $tipos)) {
                $cache_path = APP_PATH . 'temp/cache/data/';
                if (is_dir($cache_path)) {
                    $this->eliminar_directorio($cache_path);
                    $resultados[] = 'Cache de datos limpiada';
                }
            }
            
            Flash::success('Cache limpiada: ' . implode(', ', $resultados));
            
            $this->audit_logger->log_cache_cleared(
                $this->auth_user['id'],
                $tipos,
                $resultados
            );
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generar backup
     */
    private function generar_backup()
    {
        try {
            $backup_file = $this->crear_backup_archivo();
            
            Flash::success("Backup generado: " . basename($backup_file));
            
            $this->audit_logger->log_backup_created(
                $this->auth_user['id'],
                $backup_file,
                'manual'
            );
            
        } catch (Exception $e) {
            Flash::error('Error al generar backup: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener fecha del último backup
     */
    private function get_last_backup_date()
    {
        $backups = $this->listar_backups();
        return !empty($backups) ? date('d/m/Y H:i', $backups[0]['fecha']) : 'Nunca';
    }
    
    /**
     * Obtener tamaño de cache legible
     */
    private function get_cache_size_readable()
    {
        $size = $this->calculate_cache_size();
        
        if ($size < 1024) return $size . ' B';
        if ($size < 1048576) return round($size / 1024, 2) . ' KB';
        return round($size / 1048576, 2) . ' MB';
    }
    
    /**
     * Calcular tamaño total de cache
     */
    private function calculate_cache_size()
    {
        $size = 0;
        $cache_path = APP_PATH . 'temp/cache/';
        
        if (is_dir($cache_path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cache_path)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }

    /**
     * Obtener estado de salud del sistema
     */
    private function get_system_health_status()
    {
        $checks = [
            'database' => $this->check_database_connection(),
            'cache' => is_writable(APP_PATH . 'temp/cache/'),
            'logs' => is_writable(APP_PATH . 'logs/')
        ];
        
        $passed = count(array_filter($checks));
        $total = count($checks);
        
        return round(($passed / $total) * 100) . '%';
    }

    /**
     * Verificar conexión a base de datos
     */
    private function check_database_connection()
    {
        try {
            $db = Db::factory();
            $db->query('SELECT 1');
            unset($db);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener roles disponibles
     */
    private function get_available_roles()
    {
        try {
            $db = Db::factory();
            $sql = "SELECT id, codigo, nombre FROM roles WHERE activo = 1 ORDER BY nivel DESC";
            $stmt = $db->query($sql);
            
            $roles = [];
            while ($row = $db->fetch_array($stmt)) {
                $roles[] = $row;
            }
            
            return $roles;
        } catch (Exception $e) {
            return [
                ['id' => 1, 'codigo' => 'admin', 'nombre' => 'Administrador'],
                ['id' => 2, 'codigo' => 'usuario', 'nombre' => 'Usuario']
            ];
        } finally {
            // Esto se ejecutará siempre, incluso si hay excepciones
            if (isset($db)) {
                unset($db);
        }
}
    }
    
    /**
     * Listar backups
     */
    private function listar_backups()
    {
        $backup_dir = APP_PATH . 'backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
            return [];
        }
        
        $files = glob($backup_dir . 'backup_*.sql');
        $backups = [];
        
        foreach ($files as $file) {
            $backups[] = [
                'nombre' => basename($file),
                'ruta' => $file,
                'tamano' => filesize($file),
                'fecha' => filemtime($file)
            ];
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backups, function($a, $b) {
            return $b['fecha'] - $a['fecha'];
        });
        
        return $backups;
    }
    
    /**
     * Crear archivo de backup
     */
    private function crear_backup_archivo()
    {
        $config = Config::get('database');
        $db_config = $config['development'];
        
        $backup_dir = APP_PATH . 'backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $fecha = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "backup_{$db_config['database']}_$fecha.sql";
        
        // Comando mysqldump
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($db_config['username']),
            escapeshellarg($db_config['password']),
            escapeshellarg($db_config['host']),
            escapeshellarg($db_config['database']),
            escapeshellarg($backup_file)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception('Error al ejecutar mysqldump');
        }
        
        return $backup_file;
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private function eliminar_directorio($dir)
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->eliminar_directorio($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

    /**
     * Procesar configuración de correo
     */
    private function process_mail_config()
    {
        try {
            $config_data = [
                'driver' => Input::post('mail_driver', 'smtp'),
                'from' => [
                    'address' => Input::post('from_address', 'noreply@ejemplo.com'),
                    'name' => Input::post('from_name', 'Sistema de Gestión')
                ],
                'smtp' => [
                    'host' => Input::post('smtp_host', 'smtp.mailtrap.io'),
                    'port' => Input::post('smtp_port', 2525),
                    'username' => Input::post('smtp_username', ''),
                    'password' => Input::post('smtp_password', ''),
                    'encryption' => Input::post('smtp_encryption', '')
                ]
            ];
            
            if ($this->config_repository->save('mail', $config_data)) {
                Flash::success('Configuración de correo guardada correctamente');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'mail',
                    'Configuración de correo actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Procesar configuración de cache
     */
    private function process_cache_config()
    {
        try {
            $config_data = [
                'default' => Input::post('cache_default', 'file'),
                'prefix' => Input::post('cache_prefix', 'app_'),
                'ttl' => Input::post('cache_ttl', 3600),
                'enabled' => (bool)Input::post('cache_enabled', true),
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => APP_PATH . 'temp/cache/'
                    ],
                    'memcached' => [
                        'driver' => 'memcached',
                        'servers' => [
                            [
                                'host' => Input::post('memcached_host', '127.0.0.1'),
                                'port' => Input::post('memcached_port', 11211),
                                'weight' => 100
                            ]
                        ]
                    ]
                ]
            ];
            
            if ($this->config_repository->save('cache', $config_data)) {
                Flash::success('Configuración de cache guardada correctamente');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'cache',
                    'Configuración de cache actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Construir query string para paginación
     */
    protected function buildQueryString()
    {
        $params = [];
        
        if (!empty($this->filterUser)) {
            $params[] = 'usuario=' . urlencode($this->filterUser);
        }
        
        if (!empty($this->filterType)) {
            $params[] = 'tipo=' . urlencode($this->filterType);
        }
        
        if (!empty($this->filterDateFrom)) {
            $params[] = 'fecha_desde=' . urlencode($this->filterDateFrom);
        }
        
        if (!empty($this->filterDateTo)) {
            $params[] = 'fecha_hasta=' . urlencode($this->filterDateTo);
        }
        
        return !empty($params) ? '&' . implode('&', $params) : '';
    }    

    public function correo() {
        // Verificar permiso
        if (!$this->has_permission('configuracion', 'mail')) {
            $this->handle_unauthorized_access('configuracion.mail');
        }
        
        $this->title = 'Configuración de Correo Electrónico';
        
        if (Input::hasPost('guardar_mail')) {
            $this->process_mail_config();
        }
        
        $this->config_mail = $this->config_repository->get('mail', true);
    }

    public function cache() {
        if (!$this->has_permission('configuracion', 'cache')) {
            $this->handle_unauthorized_access('configuracion.cache');
        }
        
        $this->title = 'Configuración de Cache';
        
        if (Input::hasPost('guardar_cache')) {
            $this->process_cache_config();
        }
        
        $this->config_cache = $this->config_repository->get('cache', true);
    }

    private function get_available_locales()
    {
        return [
            // Español
            'es_PE' => 'Español (Perú)',
            'es_ES' => 'Español (España)',
            'es_MX' => 'Español (México)',
            'es_AR' => 'Español (Argentina)',
            'es_CL' => 'Español (Chile)',
            'es_CO' => 'Español (Colombia)',
            'es_VE' => 'Español (Venezuela)',
            'es_419' => 'Español (Latinoamérica)',
            
            // Inglés
            'en_US' => 'English (United States)',
            'en_GB' => 'English (United Kingdom)',
            'en_CA' => 'English (Canada)',
            'en_AU' => 'English (Australia)',
            
            // Portugués
            'pt_BR' => 'Português (Brasil)',
            'pt_PT' => 'Português (Portugal)',
            
            // Francés
            'fr_FR' => 'Français (France)',
            'fr_CA' => 'Français (Canada)',
            'fr_BE' => 'Français (Belgique)',
            
            // Alemán
            'de_DE' => 'Deutsch (Deutschland)',
            'de_AT' => 'Deutsch (Österreich)',
            'de_CH' => 'Deutsch (Schweiz)',
            
            // Italiano
            'it_IT' => 'Italiano (Italia)',
            'it_CH' => 'Italiano (Svizzera)',
            
            // Otros europeos
            'nl_NL' => 'Nederlands (Nederland)',
            'nl_BE' => 'Nederlands (België)',
            'pl_PL' => 'Polski (Polska)',
            'ru_RU' => 'Русский (Россия)',
            'uk_UA' => 'Українська (Україна)',
            
            // Asiáticos
            'zh_CN' => '中文 (简体, 中国)',
            'zh_TW' => '中文 (繁體, 台灣)',
            'ja_JP' => '日本語 (日本)',
            'ko_KR' => '한국어 (대한민국)',
            'ar_SA' => 'العربية (السعودية)',
            
            // Sistemas
            'C' => 'Sistema (C)',
            'POSIX' => 'POSIX'
        ];
    }

    private function get_available_timezones()
    {
        return[
            'America/Lima' => 'Perú (Lima)',
            'America/New_York' => 'USA (Nueva York)',
            'America/Chicago' => 'USA (Chicago)',
            'America/Denver' => 'USA (Denver)',
            'America/Los_Angeles' => 'USA (Los Ángeles)',
            'America/Mexico_City' => 'México (Ciudad de México)',
            'America/Bogota' => 'Colombia (Bogotá)',
            'America/Santiago' => 'Chile (Santiago)',
            'America/Argentina/Buenos_Aires' => 'Argentina (Buenos Aires)',
            'America/Sao_Paulo' => 'Brasil (São Paulo)',
            'Europe/Madrid' => 'España (Madrid)',
            'Europe/Paris' => 'Francia (París)',
            'Europe/London' => 'Reino Unido (Londres)',
            'Europe/Berlin' => 'Alemania (Berlín)',
            'Europe/Rome' => 'Italia (Roma)',
            'Europe/Moscow' => 'Rusia (Moscú)',
            'Asia/Tokyo' => 'Japón (Tokio)',
            'Asia/Shanghai' => 'China (Shanghái)',
            'Asia/Singapore' => 'Singapur',
            'Asia/Dubai' => 'Emiratos Árabes (Dubái)',
            'Australia/Sydney' => 'Australia (Sídney)',
            'Pacific/Auckland' => 'Nueva Zelanda (Auckland)',
            'UTC' => 'Tiempo Universal Coordinado (UTC)'
        ];
    }

    /**
     * Obtener información del sistema
     */
    private function get_system_info()
    {
        return [
            'php' => [
                'version' => phpversion(),
                'sapi' => php_sapi_name(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'extensions' => get_loaded_extensions()
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
                'name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ],
            'database' => $this->get_database_info(),
            'kumbiaphp' => [
                'version' => KUMBIA_VERSION, //class_exists('KumbiaVersion') ? KumbiaVersion::FRAMEWORK_VERSION : 'Desconocida',
                'path' => defined('CORE_PATH') ? CORE_PATH : 'Desconocido'
            ],
            'application' => [
                'path' => APP_PATH,
                'public_path' => PUBLIC_PATH,
                'environment' => Config::get("config.application.database"),
                'url' => Config::get('app.url', 'http://localhost')
            ]
        ];
    }

    /**
     * Obtener información de la base de datos
     */
    private function get_database_info()
    {
        try {
            $db = Db::factory();
            $info = [];
            
            // Versión del servidor de BD
            $stmt = $db->query("SELECT VERSION() as version");
            $info['version'] = $db->fetch_array($stmt);
            
            // Obtener la conexión PDO
            // Obtener configuración de la base de datos
            $database = Config::get("config.application.database");
            $databaseConfig = Config::get('databases.$database');

            // El driver suele estar en 'type' o 'driver'
            $info['type'] = $databaseConfig['type'] ?? 
                            'mysql'; // Valor por defecto            

                            // Estadísticas de tablas
            $stmt = $db->query("SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = DATABASE()");
            $info['total_tables'] = $stmt->fetch_array();

            unset($db);
            
            return $info;
        } catch (Exception $e) {
            return [
                'version' => 'Error: ' . $e->getMessage(),
                'type' => 'Desconocido',
                'total_tables' => 0
            ];
        }
    }

    // En configuracion_controller.php, método historial():
    public function historial()
    {
        $this->title = 'Historial de Configuraciones';
        
        $configRepo = new ConfigRepository();
        
        // Obtener parámetros
        $config_filter = Input::get('config') ?? null;
        $page = Input::get('page') ?: 1;
        $limit = Input::get('limit') ?: 20;
        $order = Input::get('order', 'newest');
        
        // Obtener historial
        $all_history = $configRepo->getConfigHistory($config_filter, 1000);
        
        // Aplicar orden
        $all_history = $this->sortHistory($all_history, $order);
        
        // Paginar
        $total_items = count($all_history);
        $offset = ($page - 1) * $limit;
        $this->history = array_slice($all_history, $offset, $limit);
        
        $this->total_pages = ceil($total_items / $limit);
        $this->current_page = $page;
        $this->limit = $limit;
        $this->config_filter = $config_filter;
        $this->all_history = $all_history;
        
        // Procesar acciones
        if (Input::hasPost('limpiar_historial')) {
            $dias = Input::post('dias', 30);
            $eliminados = $configRepo->cleanOldHistory($dias);
            Flash::success("Historial limpiado: {$eliminados} registros eliminados.");
            Router::redirect('configuracion/historial');
        }
    }

    private function sortHistory($history, $order)
    {
        switch ($order) {
            case 'oldest':
                usort($history, function($a, $b) {
                    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
                });
                break;
                
            case 'name':
                usort($history, function($a, $b) {
                    return strcmp($a['config'], $b['config']);
                });
                break;
                
            case 'size':
                usort($history, function($a, $b) {
                    return $a['size'] - $b['size'];
                });
                break;
                
            case 'newest':
            default:
                // Ya viene ordenado por defecto como más reciente primero
                break;
        }
        
        return $history;
    }

    private function restaurarDesdeHistorial($configRepo)
    {
        try {
            $filename = Input::post('historial_file');
            
            if (empty($filename)) {
                Flash::error('No se especificó archivo de historial');
                Redirect::to('configuracion/historial');
            }
            
            $configRepo->restoreFromHistory($filename);
            Flash::success('Configuración restaurada correctamente desde el historial.');
            
            Redirect::to('configuracion/historial');
            
        } catch (Exception $e) {
            Flash::error('Error al restaurar: ' . $e->getMessage());
        }
    }

    private function limpiarHistorial($configRepo)
    {
        try {
            $dias = Input::post('dias', 30);
            $eliminados = $configRepo->cleanOldHistory($dias);
            
            Flash::success("Historial limpiado: {$eliminados} archivos antiguos eliminados.");
            Redirect::to('configuracion/historial');
            
        } catch (Exception $e) {
            Flash::error('Error al limpiar historial: ' . $e->getMessage());
        }
    }

    public function ver_historial($filename = null)
    {
        $this->title = 'Ver Historial';
        
        if (!$filename) {
            Flash::error('No se especificó archivo de historial');
            Redirect::to('configuracion/historial');
        }
        
        try {
            $configRepo = new ConfigRepository();
            $history_dir = APP_PATH . 'temp/config_history/';
            $filepath = $history_dir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Archivo de historial no encontrado: {$filename}");
            }
            
            // Extraer información del nombre del archivo
            preg_match('/(.+?)_(\d{8})_(\d{6})\.php$/', $filename, $matches);
            
            if ($matches) {
                $config_name = $matches[1];
                $date = $matches[2];
                $time = $matches[3];
                
                $timestamp = DateTime::createFromFormat('Ymd His', $date . ' ' . $time);
                $formatted_date = $timestamp ? $timestamp->format('d/m/Y H:i:s') : "{$date} {$time}";
                
                $this->historial_info = [
                    'filename' => $filename,
                    'config_name' => $config_name,
                    'date' => $formatted_date,
                    'filepath' => $filepath,
                    'size' => filesize($filepath)
                ];
            } else {
                $this->historial_info = [
                    'filename' => $filename,
                    'config_name' => 'Desconocido',
                    'date' => 'Fecha desconocida',
                    'filepath' => $filepath,
                    'size' => filesize($filepath)
                ];
            }
            
            // Leer contenido
            $content = file_get_contents($filepath);
            
            // Extraer metadatos del comentario
            $metadata = [];
            if (preg_match('/\/\*\*(.*?)\*\//s', $content, $comment_match)) {
                $comment = $comment_match[1];
                if (preg_match('/Historial:\s*(.+)/', $comment, $m)) $metadata['historial'] = trim($m[1]);
                if (preg_match('/Acción:\s*(.+)/', $comment, $m)) $metadata['accion'] = trim($m[1]);
                if (preg_match('/Fecha:\s*(.+)/', $comment, $m)) $metadata['fecha'] = trim($m[1]);
                if (preg_match('/Usuario:\s*(.+)/', $comment, $m)) $metadata['usuario'] = trim($m[1]);
            }
            
            $this->metadata = $metadata;
            
            // Extraer el array PHP
            $array_content = '';
            if (preg_match('/return\s+(array\(|\[).*/s', $content, $matches)) {
                $array_content = $matches[0];
            } else {
                $array_content = $content;
            }
            
            $this->content = $array_content;
            $this->raw_content = $content;
            
            // Determinar si es una configuración de BD
            $this->is_database_config = strpos($config_name, 'database_') === 0;
            if ($this->is_database_config) {
                $this->db_config_name = substr($config_name, 9); // Remover 'database_'
                
                // Parsear el array para mostrar de forma estructurada
                try {
                    $config_data = include $filepath;
                    $this->parsed_config = $config_data;
                } catch (Exception $e) {
                    $this->parsed_config = null;
                }
            }
            
        } catch (Exception $e) {
            Flash::error('Error al cargar historial: ' . $e->getMessage());
            Redirect::to('configuracion/historial');
        }
    }

    public function descargar_historial($filename)
    {
        View::Select(null);
        
        try {
            $configRepo = new ConfigRepository();
            $history_dir = APP_PATH . 'temp/config_history/';
            $filepath = $history_dir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Archivo no encontrado");
            }
            
            // Configurar headers para descarga
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            readfile($filepath);
            exit;
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
            Redirect::to('admin/configuracion/historial');
        }
    }

    public function restaurar_historial()
    {
        if (Input::hasPost('restaurar')) {
            try {
                $filename = Input::post('filename');
                $config_name = Input::post('config_name');
                
                if (empty($filename)) {
                    Flash::error('No se especificó archivo de historial');
                    Redirect::to('configuracion/historial');
                }
                
                $configRepo = new ConfigRepository();
                $configRepo->restoreFromHistory($filename);
                
                Flash::success("Configuración '{$config_name}' restaurada correctamente desde el historial.");
                Redirect::to('configuracion/historial');
                
            } catch (Exception $e) {
                Flash::error('Error al restaurar: ' . $e->getMessage());
                Redirect::to('configuracion/ver_historial/' . urlencode($filename));
            }
        }
    }

    // Y la función getPageUrl para la paginación
    public function getPageUrl($page)
    {
        $params = $_GET;
        $params['page'] = $page;
        
        $query_string = http_build_query($params);
        return PUBLIC_PATH . 'configuracion/historial?' . $query_string;
    }

    public function importar()
    {
        $this->title = 'Importar Configuraciones';
        
        $configRepo = new ConfigRepository();
        
        // Obtener plantillas
        $this->templates = $configRepo->getConfigTemplates();
        
        // Procesar importación
        if (Input::hasPost('importar')) {
            $this->processImport($configRepo);
        }
        
        // Procesar carga de plantilla
        if (Input::hasPost('cargar_plantilla')) {
            $this->loadTemplate($configRepo);
        }
    }

    private function processImport($configRepo)
    {
        try {
            $format = Input::post('format', 'auto');
            $overwrite = Input::post('overwrite', false);
            $backup = Input::post('backup', true);
            
            // Verificar si se subió archivo
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
                $filename = $_FILES['import_file']['name'];
            } 
            // O usar contenido de texto
            elseif (Input::hasPost('import_content')) {
                $file_content = Input::post('import_content');
                $filename = 'manual_input.txt';
            } else {
                throw new Exception('No se proporcionó contenido para importar');
            }
            
            if (empty($file_content)) {
                throw new Exception('El contenido está vacío');
            }
            
            // Opciones de importación
            $options = [
                'overwrite' => (bool)$overwrite,
                'backup' => (bool)$backup
            ];
            
            // Ejecutar importación
            $results = $configRepo->importConfig($file_content, $format, $options);
            
            // Preparar resultado para mostrar
            $message = "<strong>Importación completada:</strong><br>";
            
            if (!empty($results['imported'])) {
                $message .= "<div class='text-success'><i class='glyphicon glyphicon-ok'></i> Importados: " . 
                        implode(', ', $results['imported']) . "</div>";
            }
            
            if (!empty($results['skipped'])) {
                $message .= "<div class='text-warning'><i class='glyphicon glyphicon-warning-sign'></i> Omitidos: " . 
                        implode('<br>', $results['skipped']) . "</div>";
            }
            
            if (!empty($results['errors'])) {
                $message .= "<div class='text-danger'><i class='glyphicon glyphicon-remove'></i> Errores: " . 
                        implode('<br>', $results['errors']) . "</div>";
            }
            
            Flash::raw($message);
            
            Router::redirect('configuracion/importar');
            
        } catch (Exception $e) {
            Flash::error('Error en importación: ' . $e->getMessage());
        }
    }

    private function loadTemplate($configRepo)
    {
        $template_id = Input::post('template_id');
        $templates = $configRepo->getConfigTemplates();
        
        if (isset($templates[$template_id])) {
            $this->template_content = $templates[$template_id]['content'];
            Flash::info("Plantilla '{$templates[$template_id]['name']}' cargada");
        } else {
            Flash::error('Plantilla no encontrada');
        }
    }

    /**
     * Exportar configuraciones actuales
     */
    public function exportar()
    {
        $this->auto_render = false;
        
        try {
            $configRepo = new ConfigRepository();
            $format = Input::get('format', 'json');
            $configs = Input::get('configs', ['databases']);
            
            if (!is_array($configs)) {
                $configs = explode(',', $configs);
            }
            
            // Obtener datos
            $export_data = [];
            
            foreach ($configs as $config_name) {
                if ($config_name === 'databases') {
                    $export_data['databases'] = $configRepo->getDatabases();
                } else {
                    $export_data[$config_name] = $configRepo->get($config_name);
                }
            }
            
            // Generar contenido según formato
            switch ($format) {
                case 'json':
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="config_export_' . date('Ymd_His') . '.json"');
                    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    break;
                    
                case 'php':
                    header('Content-Type: application/x-httpd-php');
                    header('Content-Disposition: attachment; filename="config_export_' . date('Ymd_His') . '.php"');
                    echo "<?php\n\nreturn " . var_export($export_data, true) . ";\n";
                    break;
                    
                default:
                    throw new Exception("Formato no soportado: {$format}");
            }
            
        } catch (Exception $e) {
            Flash::error('Error en exportación: ' . $e->getMessage());
            Router::redirect('configuracion/importar');
        }
    }
}
