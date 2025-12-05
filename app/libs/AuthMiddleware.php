<?php
// app/libs/AuthMiddleware.php
class AuthMiddleware
{
    private static $auth_user = null;
    private static $user_permissions = null;
    private static $is_admin = null;
    
    /**
     * Inicializar middleware (llamar en before_filter)
     */
    public static function init()
    {
        self::checkAuth();
        self::loadAuthUser();
        self::loadUserPermissions();
        self::checkAdminStatus();
    }
    
    /**
     * Verificar autenticación
     */
    public static function checkAuth()
    {
        if (!AuthHelper::isAuthenticated()) {
            Flash::error('Debe iniciar sesión para acceder a esta sección');
            Redirect::to('index/login');
        }
    }
    
    /**
     * Cargar usuario autenticado
     */
    public static function loadAuthUser()
    {
        self::$auth_user = AuthHelper::getAuthUser();
        
        if (empty(self::$auth_user)) {
            Redirect::to('index/login');
        }
    }
    
    /**
     * Cargar permisos del usuario
     */
    public static function loadUserPermissions()
    {
        if (!isset(self::$auth_user['id'])) {
            self::$user_permissions = [];
            return;
        }
        
        try {
            $data = (new Permisos)->find(
                "conditions: rp.rol_id = " . (self::$auth_user['rol_id'] ?? 0) . " AND activo = 1",
                "join: INNER JOIN rol_permisos rp ON id = rp.permisos_id",
            );
            self::$user_permissions = [];
            if ($data) {
                foreach ($data as $permiso) {
                    self::$user_permissions[] = $permiso->codigo;
                }
            }
        } catch (Exception $e) {
            self::$user_permissions = [];
        }
    }
    
    /**
     * Verificar si el usuario es administrador
     */
    public static function checkAdminStatus()
    {
        $admin_roles = ['admin', 'superadmin', 'administrador'];
        $user_role = Roles::getById(self::$auth_user['rol_id'])->codigo ?? '';
        
        self::$is_admin = in_array($user_role, $admin_roles) || 
                         in_array('admin.*', self::$user_permissions);
    }
    
    /**
     * Verificar permiso (MÉTODO CLAVE - MOVIDO DE admin_controller)
     */
    public static function hasPermission($resource, $action = 'access')
    {
        // Administradores tienen acceso completo
        if (self::$is_admin) {
            return true;
        }
        
        $permission_code = "{$resource}.{$action}";
        
        // Verificar permiso específico
        if (in_array($permission_code, self::$user_permissions)) {
            return true;
        }
        
        // Verificar permiso global para el recurso
        if (in_array("{$resource}.*", self::$user_permissions)) {
            return true;
        }
        
        // Verificar permiso de administración completa
        if (in_array('admin.*', self::$user_permissions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Manejar acceso no autorizado
     */
    public static function handleUnauthorizedAccess($permission_code)
    {
        Logger::warning("Acceso no autorizado: Usuario " . 
                       (self::$auth_user['id'] ?? 'unknown') . " - {$permission_code}");
        
        //Flash::error('No tiene permisos para acceder a esta sección');
        
        if (self::$auth_user) {
            Redirect::to('dashboard');
        } else {
            Redirect::to('');
        }
        
        exit;
    }
    
    /**
     * Verificar múltiples permisos
     */
    public static function hasAllPermissions($permissions)
    {
        foreach ($permissions as $resource => $action) {
            if (is_numeric($resource)) {
                $parts = explode('.', $action);
                if (count($parts) === 2 && !self::hasPermission($parts[0], $parts[1])) {
                    return false;
                }
            } else {
                if (!self::hasPermission($resource, $action)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Verificar al menos un permiso
     */
    public static function hasAnyPermission($permissions)
    {
        foreach ($permissions as $resource => $action) {
            if (is_numeric($resource)) {
                $parts = explode('.', $action);
                if (count($parts) === 2 && self::hasPermission($parts[0], $parts[1])) {
                    return true;
                }
            } else {
                if (self::hasPermission($resource, $action)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Getters
     */
    public static function getAuthUser()
    {
        return self::$auth_user;
    }
    
    public static function getUserPermissions()
    {
        return self::$user_permissions;
    }
    
    public static function isAdmin()
    {
        return self::$is_admin;
    }
}