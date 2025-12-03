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
                'cache_size' => $this->get_cache_size_readable()
            ];
            
            // Solo administradores ven información detallada
            if ($this->is_admin) {
                $this->recent_changes = $this->audit_logger->get_recent_changes('configuracion', 5);
            }
            
        } catch (Exception $e) {
            Flash::error('Error al cargar dashboard: ' . $e->getMessage());
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
        
        if (Input::hasPost('guardar_app')) {
            $this->process_app_config();
        }
        
        $this->config_app = $this->config_repository->get('app', true);
    }
    
    /**
     * Configuración de base de datos
     */
    public function base_datos()
    {
        if (!$this->has_permission('configuracion', 'database')) {
            $this->handle_unauthorized_access('configuracion.database');
        }
        
        $this->title = 'Configuración de Base de Datos';
        
        if (Input::hasPost('guardar_db')) {
            $this->process_database_config();
        }
        
        $this->config_db = $this->config_repository->get('database', true);
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
        if (!$this->is_admin) {
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
            $config_data = [
                'authentication_modes' => Input::post('authentication_modes', ['credentials']),
                'remote_user' => [
                    'enabled' => Input::post('remote_user_enabled', 0)
                ],
                'credentials' => [
                    'enabled' => Input::post('credentials_enabled', 1),
                    'allow_registration' => Input::post('allow_registration', 1)
                ],
                'session' => [
                    'lifetime' => Input::post('session_lifetime', 7200)
                ],
                'admin_roles' => Input::post('admin_roles', ['admin'])
            ];
            
            if ($this->config_repository->save('auth', $config_data)) {
                Flash::success('Configuración de autenticación guardada');
                
                $this->audit_logger->log_config_change(
                    $this->auth_user['id'],
                    'auth',
                    'Configuración de autenticación actualizada'
                );
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
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
                'timezone' => Input::post('app_timezone', 'America/Lima'),
                'locale' => Input::post('app_locale', 'es_PE')
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
                    'driver' => Input::post('db_driver', 'mysql'),
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
     * Obtener roles disponibles
     */
    private function get_available_roles()
    {
        try {
            $db = Db::factory();
            $sql = "SELECT id, codigo, nombre FROM roles WHERE activo = 1 ORDER BY nivel DESC";
            $stmt = $db->query($sql);
            
            $roles = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $roles[] = $row;
            }
            
            return $roles;
        } catch (Exception $e) {
            return [
                ['id' => 1, 'codigo' => 'admin', 'nombre' => 'Administrador'],
                ['id' => 2, 'codigo' => 'usuario', 'nombre' => 'Usuario']
            ];
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
     * Helper para formatear bytes
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
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
}
