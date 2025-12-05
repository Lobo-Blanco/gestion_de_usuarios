<?php
// app/controllers/PerfilController.php

class PerfilController extends AppController
{
    /**
     * Before filter - Requiere autenticación pero NO admin
     */
    protected function before_filter()
    {
        parent::before_filter();
        
        // Verificar que esté autenticado
        if (!AuthHelper::isAuthenticated()) {
            Flash::error('Debe iniciar sesión para ver su perfil');
            Redirect::to('index/login');
        }
        
        // Cargar usuario actual
        $this->auth_user = AuthHelper::getAuthUser();
        $this->usuario_actual = (new Usuarios())->find($this->auth_user['id']);
        
        // Título por defecto
        $this->title = 'Mi Perfil';
    }
    
    /**
     * Ver perfil del usuario actual
     */
    public function index()
    {
        // El usuario ya está cargado en before_filter
        $this->usuario = $this->usuario_actual;
        
        // Cargar datos adicionales usando los nuevos métodos
        $this->rol = $this->usuario->getRol();
        $this->ultimo_acceso = LogAcceso::ultimoAcceso($this->usuario->id);
        
        // Contar total de sesiones (accesos exitosos)
        $this->total_sesiones = (new LogAcceso)->count(
            "usuario_id = " . $this->usuario->id ." AND accion IN ('login', 'login_success')",
        );
    }    

    /**
     * Editar perfil del usuario actual
     */
    public function editar()
    {
        if (Input::hasPost('guardar')) {
            $this->procesar_edicion();
        }
        
        $this->usuario = $this->usuario_actual;
    }
    
    /**
     * Cambiar contraseña del usuario actual
     */
    public function cambiar_password()
    {
        if (Input::hasPost('guardar')) {
            $this->procesar_cambio_password();
        }
        
        $this->usuario = $this->usuario_actual;
    }
    
    /**
     * Ver actividad reciente
     */
    public function actividad()
    {
        $this->actividades = LogAcceso::find(
            "usuarios_id = {$this->auth_user['id']}",
            "order: fecha DESC",
            "limit: 20"
        );
    }
    
    /**
     * ============================================
     * MÉTODOS PRIVADOS
     * ============================================
     */
    
    private function procesar_edicion()
    {
        try {
            $usuario = $this->usuario_actual;
            
            // Solo campos que el usuario puede editar
            $usuario->nombre = Input::post('nombre');
            $usuario->email = Input::post('email');
            $usuario->telefono = Input::post('telefono');
            
            // Validar email único (excepto para sí mismo)
            if ($usuario->email != $this->usuario_actual->email) {
                $existente = (new Usuarios())->find_first("email = '{$usuario->email}'");
                if ($existente && $existente->id != $usuario->id) {
                    throw new Exception('El email ya está registrado');
                }
            }
            
            if ($usuario->update()) {
                Flash::success('Perfil actualizado correctamente');
                
                // Actualizar sesión
                Session::set('nombre', $usuario->nombre, 'app_auth');
                Session::set('email', $usuario->email, 'app_auth');
                
                // Registrar en log
                LogAcceso::registrarAcceso(
                    $usuario->id,
                    'perfil_actualizado',
                    ['campos' => ['nombre', 'email', 'telefono']]
                );
                
                Event::trigger('usuario.perfil_actualizado', [$usuario->id]);
                
                Redirect::to('perfil');
            } else {
                throw new Exception('Error al actualizar perfil');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    private function procesar_cambio_password()
    {
        try {
            $password_actual = Input::post('password_actual');
            $password_nueva = Input::post('password_nueva');
            $password_confirm = Input::post('password_confirm');
            
            // Validaciones
            if (empty($password_actual)) {
                throw new Exception('La contraseña actual es requerida');
            }
            
            if ($password_nueva !== $password_confirm) {
                throw new Exception('Las nuevas contraseñas no coinciden');
            }
            
            if (strlen($password_nueva) < 6) {
                throw new Exception('La nueva contraseña debe tener al menos 6 caracteres');
            }
            
            // Verificar contraseña actual
            $usuario = $this->usuario_actual;
            $hash_actual = hash(Config::get('auth.auth_algorithm'), $password_actual);
            
            if ($usuario->password != $hash_actual) {
                throw new Exception('La contraseña actual es incorrecta');
            }
            
            // Actualizar contraseña
            $usuario->password = hash(Config::get('auth.auth_algorithm'), $password_nueva);
            
            if ($usuario->update()) {
                Flash::success('Contraseña cambiada correctamente');
                
                // Registrar en log
                LogAcceso::registrarAcceso(
                    $usuario->id,
                    'password_cambiada',
                    []
                );
                
                Event::trigger('usuario.password_cambiada', [$usuario->id]);
                
                // Opcional: Cerrar sesión en otros dispositivos
                // Session::delete('app_user_session');
                
                Redirect::to('perfil');
            } else {
                throw new Exception('Error al cambiar contraseña');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
}