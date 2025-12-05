<?php
// app/controllers/admin_controller.php
require_once APP_PATH . "libs/ViewHelper.php";
require_once APP_PATH . "libs/AuthMiddleware.php";

/**
 * Controlador base para el área administrativa
 */
class AdminController extends Controller
{
    /**
     * Before filter simplificado
     */
    protected function before_filter()
    {
        parent::before_filter();
        
        // Inicializar middleware de autenticación
        AuthMiddleware::init();
        
        // Verificar que sea administrador
        if (!AuthMiddleware::isAdmin()) {
            AuthMiddleware::handleUnauthorizedAccess('admin.access');
        }
    }
    
    /**
     * Métodos de conveniencia (wrappers para AuthMiddleware)
     */
    protected function has_permission($resource, $action = 'access')
    {
        return AuthMiddleware::hasPermission($resource, $action);
    }
    
    protected function handle_unauthorized_access($permission_code)
    {
        AuthMiddleware::handleUnauthorizedAccess($permission_code);
    }
    
    protected function get_auth_user()
    {
        return AuthMiddleware::getAuthUser();
    }
    
    protected function is_user_admin()
    {
        return AuthMiddleware::isAdmin();
    }
    
    protected function get_user_permissions()
    {
        return AuthMiddleware::getUserPermissions();
    }
    
    protected function has_all_permissions($permissions)
    {
        return AuthMiddleware::hasAllPermissions($permissions);
    }
    
    protected function has_any_permission($permissions)
    {
        return AuthMiddleware::hasAnyPermission($permissions);
    }
}