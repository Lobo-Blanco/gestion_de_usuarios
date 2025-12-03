<?php
// app/controllers/admin_controller.php
require_once APP_PATH . "libs/AuthHelper.php";
require_once APP_PATH . "libs/ViewHelper.php";

/**
 * Controlador base para el área administrativa
 */
class AdminController extends AppController
{
    /**
     * @var array Usuario autenticado
     */
    protected $auth_user = [];
    
    /**
     * @var bool Si el usuario es administrador
     */
    protected $is_admin = false;
    
    /**
     * @var array Permisos del usuario
     */
    protected $user_permissions = [];
    
    /**
     * Before filter
     */
    protected function before_filter()
    {
        parent::before_filter();
        
        // Verificar autenticación
        $this->check_auth();
        
        // Cargar usuario y permisos
        $this->load_auth_user();
        $this->load_user_permissions();
        
        // Verificar si es administrador
        $this->check_admin_status();
    }
    
    /**
     * Verificar autenticación
     */
    protected function check_auth()
    {
        // Si no está autenticado, redirigir a login
        if (!AuthHelper::isAuthenticated()) {
            Flash::error('Debe iniciar sesión para acceder a esta sección');
            Redirect::to('index/login');
        }
    }
    
    /**
     * Cargar usuario autenticado
     */
    protected function load_auth_user()
    {
        $this->auth_user =  AuthHelper::getAuthUser();

/*         // Cargar desde sesión o base de datos
        if (isset($_SESSION['id'])) {
            try {
                $db = Db::factory();
                $sql = "SELECT u.*, r.codigo as rol_codigo 
                        FROM usuarios u 
                        LEFT JOIN roles r ON u.rol_id = r.id 
                        WHERE u.id = ? AND u.activo = 1 LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([$_SESSION['user_id']]);
                $this->auth_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                $this->auth_user = [];
            }
        }
 */        
        if (empty($this->auth_user)) {
            Redirect::to('index/login');
        }
    }
    
    /**
     * Cargar permisos del usuario
     */
    protected function load_user_permissions()
    {
        if (isset($this->auth_user['id'])) {
            try {
                $data = (new Permisos)->find_by_sql("
                        SELECT p.codigo 
                        FROM permisos p 
                        INNER JOIN rol_permisos rp ON p.id = rp.permisos_id 
                        WHERE rp.rol_id = " . ($this->auth_user['rol_id'] ?? 0) . " AND p.activo = 1
                ");
                
                $this->user_permissions = [];
                if (is_object($data)) {
                    foreach ($data as $permiso) {
                        $this->user_permissions[] = $permiso->codigo;
                    }
                }
            } catch (Exception $e) {
                $this->user_permissions = [];
            }
        }
    }
    
    /**
     * Verificar si el usuario es administrador
     */
    protected function check_admin_status()
    {
        $admin_roles = ['admin', 'superadmin', 'administrador'];
        $user_role = $this->auth_user['rol'] ?? '';
        
        $this->is_admin = in_array($user_role, $admin_roles) || 
                         in_array('admin.*', $this->user_permissions);
    }
    
    /**
     * VERIFICAR PERMISOS - MÉTODO CLAVE
     */
    protected function has_permission($resource, $action = 'access')
    {
        // Administradores tienen acceso completo
        if ($this->is_admin) {
            return true;
        }
        
        $permission_code = "{$resource}.{$action}";
        
        // Verificar permiso específico
        if (in_array($permission_code, $this->user_permissions)) {
            return true;
        }
        
        // Verificar permiso global para el recurso
        if (in_array("{$resource}.*", $this->user_permissions)) {
            return true;
        }
        
        // Verificar permiso de administración completa
        if (in_array('admin.*', $this->user_permissions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Manejar acceso no autorizado
     */
    protected function handle_unauthorized_access($permission_code)
    {
        Logger::warning("Acceso no autorizado: Usuario {$this->auth_user['id']} - {$permission_code}");
        
        Flash::error('No tiene permisos para acceder a esta sección');
        
        // Redirigir al dashboard o página principal
        if (isset($this->auth_user['id'])) {
            Redirect::to('dashboard');
        } else {
            Redirect::to('');
        }
        
        // Para asegurar que no se siga ejecutando
        exit;
    }
    
    /**
     * Obtener usuario autenticado
     */
    protected function get_auth_user()
    {
        return $this->auth_user;
    }
    
    /**
     * Verificar si es administrador
     */
    protected function is_user_admin()
    {
        return $this->is_admin;
    }
    
    /**
     * Obtener permisos del usuario
     */
    protected function get_user_permissions()
    {
        return $this->user_permissions;
    }
    public function prueba() {}
    /**
     * Verificar múltiples permisos
     */
    protected function has_all_permissions($permissions)
    {
        foreach ($permissions as $resource => $action) {
            if (is_numeric($resource)) {
                // Si es array simple: ['resource.action', 'resource2.action2']
                $parts = explode('.', $action);
                if (count($parts) === 2) {
                    if (!$this->has_permission($parts[0], $parts[1])) {
                        return false;
                    }
                }
            } else {
                // Si es array asociativo: ['resource' => 'action']
                if (!$this->has_permission($resource, $action)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Verificar al menos un permiso
     */
    protected function has_any_permission($permissions)
    {
        foreach ($permissions as $resource => $action) {
            if (is_numeric($resource)) {
                $parts = explode('.', $action);
                if (count($parts) === 2 && $this->has_permission($parts[0], $parts[1])) {
                    return true;
                }
            } else {
                if ($this->has_permission($resource, $action)) {
                    return true;
                }
            }
        }
        return false;
    }
}