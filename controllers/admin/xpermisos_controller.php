<?php
// app/controllers/permisos_controller.php

class PermisosController extends AdminController
{
    /**
     * Lista de permisos
     */
    public function index()
    {
        $this->permisos = (new Permisos())->find("order: nombre ASC");
        $this->title = 'Gestión de Permisos';
    }
    
    /**
     * Ver detalle de permiso
     */
    public function ver($id)
    {
        $this->permiso = (new Permisos())->find($id);
        
        if (!$this->permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        // Obtener recursos asociados
        $permisoRecursos = (new PermisosRecurso())->find("permisos_id = {$this->permiso->id}");
        $this->recursos = array();
        foreach ($permisoRecursos as $pr) {
            $recurso = $pr->getRecurso();
            if ($recurso) {
                $this->recursos[] = $recurso;
            }
        }
        
        // Obtener usuarios con este permiso
        $usuarioPermisos = (new UsuarioPermisos())->find("permisos_id = {$this->permiso->id}");
        $this->usuarios = array();
        foreach ($usuarioPermisos as $up) {
            $usuario = $up->getUsuario();
            if ($usuario) {
                $this->usuarios[] = $usuario;
            }
        }
        
        $this->title = 'Permiso: ' . $this->permiso->nombre;
    }
    
    /**
     * Formulario para crear permiso
     */
    public function crear()
    {
        View::select("_form");

        $this->title = 'Crear Nuevo Permiso';
    }
    
    /**
     * Guardar nuevo permiso
     */
    public function guardar()
    {
        if (Input::hasPost('nombre')) {
            $permiso = new Permisos();
            $permiso->nombre = Input::post('nombre');
            $permiso->descripcion = Input::post('descripcion');
            
            // Validar que el nombre no exista
            $existente = (new Permisos())->find_first("nombre = '{$permiso->nombre}'");
            if ($existente) {
                Flash::error('Ya existe un permiso con ese nombre');
                Redirect::toAdmin('crear');
            }
            
            if ($permiso->save()) {
                Flash::success('Permiso creado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    AuthHelper::getAuthUser()['id'],
                    'permiso_creado',
                    'permiso',
                    $permiso->id
                );
                
                Event::trigger('admin.permiso_creado', [
                    $permiso->id,
                    AuthHelper::getAuthUser()['id']
                ]);
                
                Redirect::toAdmin('ver/' . $permiso->id);
            } else {
                Flash::error('Error al crear permiso: ' . implode(', ', $permiso->get_errors()));
            }
        } else {
            Flash::error('El nombre del permiso es obligatorio');
        }
        
        // Mantener los datos del formulario
        $this->permiso = (new Permisos(Input::post()));
        View::select('crear');
    }
    
    /**
     * Formulario para editar permiso
     */
    public function editar($id)
    {
        View::select("_form");

        $this->permiso = (new Permisos())->find($id);
        
        if (!$this->permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        $this->title = 'Editar Permiso: ' . $this->permiso->nombre;
    }
    
    /**
     * Actualizar permiso
     */
    public function actualizar($id)
    {
        $permiso = (new Permisos())->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        if (Input::hasPost('nombre')) {
            $nombre_original = $permiso->nombre;
            $permiso->nombre = Input::post('nombre');
            $permiso->descripcion = Input::post('descripcion');
            
            // Validar que el nuevo nombre no exista (excepto para sí mismo)
            if ($permiso->nombre != $nombre_original) {
                $existente = (new Permisos())->find_first("nombre = '{$permiso->nombre}' AND id != $id");
                if ($existente) {
                    Flash::error('Ya existe otro permiso con ese nombre');
                    Redirect::toAdmin('editar/' . $id);
                }
            }
            
            if ($permiso->update()) {
                Flash::success('Permiso actualizado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    $this->getAuthUser()['id'],
                    'permiso_actualizado',
                    'permiso',
                    $permiso->id
                );
                
                Event::trigger('admin.permiso_actualizado', [
                    $permiso->id,
                    $this->getAuthUser()['id']
                ]);
                
                // Limpiar cache del ACL
                $acl = Acl2::factory('database');
                $acl->clearAllCache();
                
                Redirect::toAdmin('ver/' . $permiso->id);
            } else {
                Flash::error('Error al actualizar permiso: ' . implode(', ', $permiso->get_errors()));
            }
        } else {
            Flash::error('El nombre del permiso es obligatorio');
        }
        
        // Mantener los datos del formulario
        $this->permiso = (object)array_merge((array)$permiso, Input::post());
        View::select('editar');
    }
    
    /**
     * Asignar recursos a permiso
     */
    public function recursos($id)
    {
        $this->permiso = (new Permisos())->find($id);
        
        if (!$this->permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        
        // Obtener recursos ya asignados
        $permisoRecursos = (new PermisosRecurso())->find("permisos_id = $id");
        $this->recursos_asignados = array();
        foreach ($permisoRecursos as $pr) {
            $this->recursos_asignados[] = $pr->recurso_id;
        }
        
        $this->title = 'Recursos del Permiso: ' . $this->permiso->nombre;
    }
    
    /**
     * Guardar recursos asignados
     */
    public function guardar_recursos($id)
    {
        $permiso = (new Permisos())->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        $recursosSeleccionados = Input::post('recursos') ?: array();
        $usuarioId = $this->getAuthUser()['id'];
        
        // Obtener recursos actuales para comparar
        $permisoRecursosActuales = (new PermisosRecurso())->find("permisos_id = $id");
        $recursosActuales = array();
        foreach ($permisoRecursosActuales as $pra) {
            $recursosActuales[] = $pra->recurso_id;
        }
        
        // Determinar qué se agregó y qué se eliminó
        $agregados = array_diff($recursosSeleccionados, $recursosActuales);
        $eliminados = array_diff($recursosActuales, $recursosSeleccionados);
        
        // Eliminar relaciones que ya no están seleccionadas
        foreach ($eliminados as $recursoId) {
            $permisoRecurso = (new PermisosRecurso())->find_first(
                "permisos_id = $id AND recurso_id = $recursoId"
            );
            if ($permisoRecurso) {
                $permisoRecurso->delete();
            }
        }
        
        // Agregar nuevas relaciones
        $recursosAgregados = array();
        foreach ($agregados as $recursoId) {
            $permisoRecurso = new PermisosRecurso();
            $permisoRecurso->permisos_id = $id;
            $permisoRecurso->recurso_id = $recursoId;
            $permisoRecurso->creado_por = $usuarioId;
            
            if ($permisoRecurso->save()) {
                $recursosAgregados[] = $recursoId;
            }
        }
        
        // Limpiar cache del ACL
        $acl = Acl2::factory('database');
        $acl->clearAllCache();
        
        Flash::success('Recursos asignados correctamente');
        
        // Registrar en log
        LogAcceso::registrarAccionAdmin(
            $usuarioId,
            'permiso_recursos_actualizados',
            'permiso',
            $id
        );
        
        Event::trigger('admin.recursos_permiso_actualizados', [
            $id,
            $usuarioId,
            count($agregados),
            count($eliminados)
        ]);
        
        Redirect::toAdmin('ver/' . $id);
    }
    
    /**
     * Eliminar permiso
     */
    public function eliminar($id)
    {
        $permiso = (new Permisos())->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::toAdmin('index');
        }
        
        // Verificar si hay usuarios con este permiso
        $usuariosConPermiso = (new UsuarioPermisos())->count("permisos_id = $id");
        if ($usuariosConPermiso > 0) {
            Flash::error("No se puede eliminar el permiso porque $usuariosConPermiso usuario(s) lo tienen asignado");
            Redirect::toAdmin('index');
        }
        
        // Primero eliminar relaciones en permiso_recurso
        $permisoRecursos = (new PermisosRecurso())->find("permisos_id = $id");
        foreach ($permisoRecursos as $pr) {
            $pr->delete();
        }
        
        // Luego eliminar el permiso
        if ($permiso->delete()) {
            Flash::success('Permiso eliminado correctamente');
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'permiso_eliminado',
                'permiso',
                $id
            );
            
            Event::trigger('admin.permiso_eliminado', [
                $id,
                $permiso->nombre,
                $this->getAuthUser()['id']
            ]);
            
            // Limpiar cache
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
        } else {
            Flash::error('Error al eliminar permiso');
        }
        
        Redirect::toAdmin('index');
    }
    
    /**
     * Buscar permisos
     */
    public function buscar()
    {
        $termino = Input::get('q');
        
        if ($termino) {
            $this->permisos = (new Permisos())->find(
                "nombre LIKE '%$termino%' OR descripcion LIKE '%$termino%'",
                "order: nombre ASC"
            );
            $this->termino_busqueda = $termino;
        } else {
            $this->permisos = (new Permisos())->find("order: nombre ASC");
        }
        
        $this->title = 'Búsqueda de Permisos';
        
        View::select('index'); // Reutiliza la vista index
    }
}