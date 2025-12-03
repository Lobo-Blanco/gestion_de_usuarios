<?php
// app/controllers/dashboard_controller.php

require APP_PATH . "controllers/admin_controller.php";
class DashboardController extends AdminController
{
    /**
     * Panel principal de administración
     */
    public function index()
    {
        $this->usuario = AuthHelper::getAuthUser();
        $this->title = 'Panel de Administración';
        
        // Estadísticas para el dashboard
        $this->total_usuarios = (new Usuarios())->count();
        $this->usuarios_activos = (new Usuarios())->count("activo = 1");
        $this->has_permission['dashboard'] = $this->has_permission('dashboard', 'access');
        $this->has_permission['usuarios'] = $this->has_permission('usuarios', 'access');
        $this->has_permission['roles'] = $this->has_permission('roles', 'access');
        $this->has_permission['configuracion'] = $this->has_permission('configuracion', 'access');
    }
}