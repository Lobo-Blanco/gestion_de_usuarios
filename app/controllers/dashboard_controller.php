<?php
// app/controllers/dashboard_controller.php

class DashboardController extends AppController
{
    /**
     * Panel principal de administración
     */
    public function index()
    {
        $this->usuario = AuthMiddleware::getAuthUser();
        $this->title = 'Panel de Administración';
        
        // Estadísticas para el dashboard
        $this->total_usuarios = (new Usuarios())->count();
        $this->usuarios_activos = (new Usuarios())->count("activo = 1");
        $this->has_permission['dashboard'] = AuthMiddleware::hasPermission('dashboard', 'access');
        $this->has_permission['usuarios'] = AuthMiddleware::hasPermission('usuarios', 'access');
        $this->has_permission['roles'] = AuthMiddleware::hasPermission('roles', 'access');
        $this->has_permission['configuracion'] = AuthMiddleware::hasPermission('configuracion', 'access');
    }
}