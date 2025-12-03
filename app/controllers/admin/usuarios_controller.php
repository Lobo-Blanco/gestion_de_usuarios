<?php
// app/controllers/usuarios_controller.php

require APP_PATH . "controllers/admin_controller.php";
class UsuariosController extends AdminController
{
    /**
     * Lista de usuarios
     */
    public function index()
    {
        $this->usuarios = (new Usuarios())->find("order: nombre ASC");
        $this->current_user = AuthHelper::getAuthUser();

        $this->title = 'Gestión de Usuarios';
    }
    
    /**
     * Ver detalle de usuario
     */
    public function ver($id)
    {
        $this->usuario = (new Usuarios())->find($id);
        $this->current_user = AuthHelper::getAuthUser();

        if (!$this->usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        // Obtener permisos del usuario
        $usuarioPermisos = (new UsuarioPermisos())->find("usuarios_id = {$this->usuario->id}");
        $this->permisos_usuario = array();
        foreach ($usuarioPermisos as $up) {
            $permiso = $up->permisos;
            if ($permiso) {
                $this->permisos_usuario[] = $permiso;
            }
        }
        
        // Obtener logs de acceso recientes
        $this->logs_acceso = $this->getLogsAcceso($this->usuario->id);
        
        $this->title = 'Detalle de Usuario: ' . $this->usuario->nombre;
    }
    
    /**
     * Formulario para crear usuario
     */
    public function crear()
    {
        View::select("_form");
        $this->title = 'Crear Nuevo Usuario';
    }
    
    /**
     * Guardar nuevo usuario
     */
    public function guardar()
    {
        if (Input::hasPost('codigo', 'nombre', 'rol')) {
            $usuario = new Usuarios();
            $usuario->codigo = Input::post('codigo');
            $usuario->nombre = Input::post('nombre');
            $usuario->email = Input::post('email');
            $usuario->rol = Input::post('rol_id');
            $usuario->activo = Input::post('activo', 0);
            
            // Si se proporciona password, encriptarlo
            if (Input::post('password')) {
                if (Input::post('password') !== Input::post('password_confirm')) {
                    Flash::error('Las contraseñas no coinciden');
                    Redirect::to('/admin/usuarios/crear');
                }
                $usuario->password = password_hash(Input::post('password'), PASSWORD_DEFAULT);
                $usuario->auth_method = 'email';
            } else {
                $usuario->auth_method = 'remote_user';
            }
            
            if ($usuario->save()) {
                Flash::success('Usuario creado correctamente');
                
                Event::trigger('admin.usuario_creado', [
                    $usuario->id,
                    AuthHelper::getAuthUser()['id']
                ]);
                
                Redirect::to('/admin/usuarios/ver/' . $usuario->id);
            } else {
                Flash::error('Error al crear usuario: ' . implode(', ', $usuario->get_errors()));
            }
        }
        
        Redirect::to('/admin/usuarios/crear');
    }
    
    /**
     * Formulario para editar usuario
     */
    public function editar($id)
    {
        View::select("_form");

        $this->usuario = (new Usuarios())->find($id);
        
        if (!$this->usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        $this->title = 'Editar Usuario: ' . $this->usuario->nombre;
    }
    
    /**
     * Actualizar usuario
     */
    public function actualizar($id)
    {
        $usuario = (new Usuarios())->find($id);
        
        if (!$usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        if (Input::hasPost('codigo', 'nombre', 'rol')) {
            $usuario->codigo = Input::post('codigo');
            $usuario->nombre = Input::post('nombre');
            $usuario->email = Input::post('email');
            $usuario->rol = Input::post('rol_id');
            $usuario->activo = Input::post('activo', 0);
            
            // Actualizar password si se proporciona
            if (Input::post('password')) {
                if (Input::post('password') !== Input::post('password_confirm')) {
                    Flash::error('Las contraseñas no coinciden');
                    Redirect::to('/admin/usuarios/editar/' . $id);
                }
                $usuario->password = hash(Config::get("auth.auth_algorithm"), Input::post('password'));
                $usuario->auth_method = 'credentials';
                
                Event::trigger('auth.password_changed', [
                    $usuario->id,
                    $usuario->email
                ]);
            }
            
            if ($usuario->update()) {
                Flash::success('Usuario actualizado correctamente');
                
                Event::trigger('admin.usuario_actualizado', [
                    $usuario->id,
                    AuthHelper::getAuthUser()['id']
                ]);
                
                Redirect::to('/admin/usuarios/ver/' . $usuario->id);
            } else {
                Flash::error('Error al actualizar usuario');
            }
        }
        
        Redirect::to('/admin/usuarios/editar/' . $id);
    }
    
    /**
     * Asignar permisos a usuario
     */
    public function permisos($id)
    {
        $this->usuario = (new Usuarios())->find($id);
        
        if (!$this->usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        // Obtener todos los permisos
        $this->permisos = (new Permisos())->find("order: nombre ASC");
        
        // Obtener permisos del usuario
        $usuarioPermisos = (new UsuarioPermisos())->find("usuarios_id = $id");
        $this->permisos_asignados = array();
        foreach ($usuarioPermisos as $up) {
            $this->permisos_asignados[] = $up->permisos_id;
        }
        
        // Obtener recursos para mostrar qué accesos da cada permiso
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        
        $this->title = 'Asignar Permisos: ' . $this->usuario->nombre;
    }
    
    /**
     * Guardar permisos asignados
     */
    public function guardar_permisos($id)
    {
        $usuario = (new Usuarios())->find($id);
        
        if (!$usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        $permisosSeleccionados = Input::post('permisos') ?: array();
        $usuarioId = AuthHelper::getAuthUser()['id'];
        
        // Eliminar permisos actuales
        $permisosActuales = (new UsuarioPermisos())->find("usuarios_id = $id");
        foreach ($permisosActuales as $permiso) {
            $permiso->delete();
        }
        
        // Agregar nuevos permisos
        $permisosAgregados = array();
        foreach ($permisosSeleccionados as $permisoId) {
            $usuarioPermiso = new UsuarioPermisos();
            $usuarioPermiso->usuarios_id = $id;
            $usuarioPermiso->permisos_id = $permisoId;
            $usuarioPermiso->creado_por = $usuarioId;
            
            if ($usuarioPermiso->save()) {
                $permisosAgregados[] = $permisoId;
            }
        }
        
        // Limpiar cache del ACL
        $acl = Acl2::factory('database');
        $acl->clearUserCache($usuario->id);
        
        Flash::success('Permisos actualizados correctamente');
        
        Event::trigger('admin.permisos_usuario_actualizados', [
            $id,
            $usuarioId,
            $permisosAgregados
        ]);
        
        Redirect::to('/admin/usuarios/ver/' . $id);
    }
    
    /**
     * Eliminar usuario
     */
    public function eliminar($id)
    {
        // No permitir eliminarse a sí mismo
        if ($id == AuthHelper::getAuthUser()['id']) {
            Flash::error('No puede eliminarse a sí mismo');
            Redirect::to('/admin/usuarios/index');
        }
        
        $usuario = (new Usuarios())->find($id);
        
        if (!$usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        // Primero eliminar relaciones en usuario_permiso
        $usuarioPermisos = (new UsuarioPermisos())->find("usuarios_id = $id");
        foreach ($usuarioPermisos as $up) {
            $up->delete();
        }
        
        // Luego eliminar el usuario
        if ($usuario->delete()) {
            Flash::success('Usuario eliminado correctamente');
            
            Event::trigger('admin.usuario_eliminado', [
                $id,
                $usuario->codigo,
                AuthHelper::getAuthUser()['id']
            ]);
        } else {
            Flash::error('Error al eliminar usuario');
        }
        
        Redirect::to('/admin/usuarios/index');
    }
    
    /**
     * Activar/desactivar usuario
     */
    public function toggle_activo($id)
    {
        $usuario = (new Usuarios())->find($id);
        
        if (!$usuario) {
            Flash::error('Usuario no encontrado');
            Redirect::to('/admin/usuarios/index');
        }
        
        // No permitir desactivarse a sí mismo
        if ($id == AuthHelper::getAuthUser()['id']) {
            Flash::error('No puede desactivarse a sí mismo');
            Redirect::to('/admin/usuarios/index');
        }
        
        $usuario->activo = $usuario->activo ? 0 : 1;
        
        if ($usuario->update()) {
            $estado = $usuario->activo ? 'activado' : 'desactivado';
            Flash::success("Usuario $estado correctamente");
            
            Event::trigger('admin.usuario_cambio_estado', [
                $id,
                $usuario->activo,
                AuthHelper::getAuthUser()['id']
            ]);
        } else {
            Flash::error('Error al cambiar estado del usuario');
        }
        
        Redirect::to('/admin/usuarios/index');
    }
    
    /**
     * Obtener logs de acceso del usuario
     */
    private function getLogsAcceso($usuarioId, $limit = 10)
    {
        // Implementar según tu sistema de logs
        // Ejemplo básico:
        try {
            $logs = (new LogAcceso())->find("usuarios_id = $usuarioId", "order: fecha DESC", "limit: $limit");
            return $logs;
        } catch (Exception $e) {
            return array();
        }
    }
}