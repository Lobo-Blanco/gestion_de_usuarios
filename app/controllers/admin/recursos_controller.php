<?php

require APP_PATH . "controllers/admin_controller.php";
class RecursosController extends AdminController
{
    /**
     * Lista de recursos
     */
    public function index()
    {
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion ASC");
        $this->title = 'Gestión de Recursos';
    }
    
    /**
     * Ver detalle de recurso
     */
    public function ver($id)
    {
         $this->recurso = (new Recursos())->find($id);
        
        if (!$this->recurso) {
            Flash::error('Recurso no encontrado');
            Redirect::toAction('index');
        }

        // Obtener permisos que tienen acceso a este recurso
        $permisoRecursos = (new PermisosRecurso())->find("recursos_id = {$this->recurso->id}");
        $this->permisos = array();
        
        foreach ($permisoRecursos as $pr) {
            $permiso = $pr->getPermiso();
            if ($permiso) {
                $this->permisos[] = $permiso;
            }
        }

        // Obtener menús que usan este recurso
        $this->menus = (new Menus())->find("recursos_id = {$this->recurso->id}");
        
        $this->title = 'Recurso: ' . $this->recurso->nombre;
    }
    
    /**
     * Formulario para crear recurso
     */
    public function crear()
    {
        View::select("_form");

        $this->title = 'Crear Nuevo Recurso';
    }
    
    /**
     * Guardar nuevo recurso
     */
    public function guardar()
    {
        if (Input::hasPost('nombre', 'controlador', 'accion')) {
            $recurso = new Recursos();
            $recurso->nombre = Input::post('nombre');
            $recurso->modulo = Input::post('modulo');
            $recurso->controlador = Input::post('controlador');
            $recurso->accion = Input::post('accion');
            $recurso->descripcion = Input::post('descripcion');
            $recurso->activo = Input::post('activo', 1);
            
            // Validar que el nombre no exista
            $existente = (new Recursos())->find_first("nombre = '{$recurso->nombre}'");
            if ($existente) {
                Flash::error('Ya existe un recurso con ese nombre');
                Redirect::toAction('crear');
            }
            
            if ($recurso->save()) {
                Flash::success('Recurso creado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    $this->getAuthUser()['id'],
                    'recurso_creado',
                    'recurso',
                    $recurso->id
                );
                
                Event::trigger('admin.recurso_creado', [
                    $recurso->id,
                    $this->getAuthUser()['id']
                ]);
                
                Redirect::toAction('ver/' . $recurso->id);
            } else {
                Flash::error('Error al crear recurso');
            }
        } else {
            Flash::error('El nombre, controlador y acción son obligatorios');
        }
        
        // Mantener los datos del formulario
        $this->recurso = (new Recursos(Input::post()));
        $this->crear();
    }
    
    /**
     * Formulario para editar recurso
     */
    public function editar($id)
    {
        View::select("_form");
        
        $this->recurso = (new Recursos())->find($id);
        
        if (!$this->recurso) {
            Flash::error('Recurso no encontrado');
            Redirect::toAction('index');
        }
        
        $this->title = 'Editar Recurso: ' . $this->recurso->nombre;
    }
    
    /**
     * Actualizar recurso
     */
    public function actualizar($id)
    {
        $recurso = (new Recursos())->find($id);
        
        if (!$recurso) {
            Flash::error('Recurso no encontrado');
            Redirect::toAction('index');
        }
        
        if (Input::hasPost('nombre', 'controlador', 'accion')) {
            $nombre_original = $recurso->nombre;
            $recurso->nombre = Input::post('nombre');
            $recurso->modulo = Input::post('modulo');
            $recurso->controlador = Input::post('controlador');
            $recurso->accion = Input::post('accion');
            $recurso->descripcion = Input::post('descripcion');
            $recurso->activo = Input::post('activo', 1);
            
            // Validar que el nuevo nombre no exista (excepto para sí mismo)
            if ($recurso->nombre != $nombre_original) {
                $existente = (new Recursos())->find_first("nombre = '{$recurso->nombre}' AND id != $id");
                if ($existente) {
                    Flash::error('Ya existe otro recurso con ese nombre');
                    Redirect::toAction('editar' . $id);
                }
            }
            
            if ($recurso->update()) {
                Flash::success('Recurso actualizado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    AuthHelper::getAuthUser()['id'],
                    'recurso_actualizado',
                    'recurso',
                    $recurso->id
                );
                
                Event::trigger('admin.recurso_actualizado', [
                    $recurso->id,
                    AuthHelper::getAuthUser()['id']
                ]);
                
                // Limpiar cache del ACL
                $acl = Acl2::factory('database');
                $acl->clearAllCache();
                
                Redirect::toAction('ver/' . $recurso->id);
            } else {
                Flash::error('Error al actualizar recurso');
            }
        } else {
            Flash::error('El nombre, controlador y acción son obligatorios');
        }
        
        // Mantener los datos del formulario
        $this->recurso = (new Recursos(Input::post()));
        $this->editar($id);
    }
    
    /**
     * Eliminar recurso
     */
    public function eliminar($id)
    {
        $recurso = (new Recursos())->find($id);
        
        if (!$recurso) {
            Flash::error('Recurso no encontrado');
            Redirect::toAction('index');
        }
        
        // Verificar si hay permisos asignados a este recurso
        $permisosAsignados = (new PermisoRecursos())->count("recurso_id = $id");
        if ($permisosAsignados > 0) {
            Flash::error("No se puede eliminar el recurso porque $permisosAsignados permiso(s) lo tienen asignado");
            Redirect::toAction('index');
        }
        
        // Verificar si hay menús que usan este recurso
        $menusQueLoUsan = (new Menu())->count("recursos_id = $id");
        if ($menusQueLoUsan > 0) {
            Flash::error("No se puede eliminar el recurso porque $menusQueLoUsan menú(s) lo usan");
            Redirect::toAction('index');
        }
        
        if ($recurso->delete()) {
            Flash::success('Recurso eliminado correctamente');
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'recurso_eliminado',
                'recurso',
                $id
            );
            
            Event::trigger('admin.recurso_eliminado', [
                $id,
                $recurso->nombre,
                $this->getAuthUser()['id']
            ]);
        } else {
            Flash::error('Error al eliminar recurso');
        }
        
        Redirect::toAction('index');
    }
    
    /**
     * Activar/desactivar recurso
     */
    public function toggle_activo($id)
    {
        $recurso = (new Recursos())->find($id);
        
        if (!$recurso) {
            Flash::error('Recurso no encontrado');
            Redirect::toAction('index');
        }
        
        $recurso->activo = $recurso->activo ? 0 : 1;
        
        if ($recurso->update()) {
            $estado = $recurso->activo ? 'activado' : 'desactivado';
            Flash::success("Recurso $estado correctamente");
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'recurso_cambio_estado',
                'recurso',
                $recurso->id
            );
            
            Event::trigger('admin.recurso_cambio_estado', [
                $recurso->id,
                $recurso->activo,
                $this->getAuthUser()['id']
            ]);
            
            // Limpiar cache del ACL
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
        } else {
            Flash::error('Error al cambiar estado del recurso');
        }
        
        Redirect::toAction('index');
    }
    
    /**
     * Sincronizar recursos desde archivo de configuración
     */
    public function sincronizar()
    {
        if (Input::hasPost('confirmar')) {
            $recursosConfig = Config::get('resources', []);
            $creados = 0;
            $actualizados = 0;
            
            foreach ($recursosConfig as $nombre => $config) {
                $recurso = (new Recursos())->find_first("nombre = '$nombre'");
                
                if (!$recurso) {
                    $recurso = new Recursos();
                    $recurso->nombre = $nombre;
                    $creados++;
                } else {
                    $actualizados++;
                }
                
                $recurso->modulo = $config['modulo'] ?? null;
                $recurso->controlador = $config['controlador'];
                $recurso->accion = $config['accion'];
                $recurso->descripcion = $config['descripcion'] ?? '';
                $recurso->activo = 1;
                
                $recurso->save();
            }
            
            Flash::success("Sincronización completada: $creados creados, $actualizados actualizados");
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                AuthHelper::getAuthUser()['id'],
                'recursos_sincronizados',
                'recurso',
                null,
                ['creados' => $creados, 'actualizados' => $actualizados]
            );
            
            Redirect::toAction('index');
        }
        
        $this->recursosConfig = Config::get('resources');
        $this->totalConfig = count($this->recursosConfig);
        
        $this->recursosBD = (new Recursos())->find();
        $this->totalBD = count($this->recursosBD);
        
        $this->title = 'Sincronizar Recursos';
    }
    
    /**
     * Buscar recursos
     */
    public function buscar()
    {
        $termino = Input::get('q');
        
        if ($termino) {
            $this->recursos = (new Recursos())->find(
                "nombre LIKE '%$termino%' OR 
                 modulo LIKE '%$termino%' OR 
                 controlador LIKE '%$termino%' OR 
                 accion LIKE '%$termino%' OR 
                 descripcion LIKE '%$termino%'",
                "order: modulo, controlador, accion ASC"
            );
            $this->termino_busqueda = $termino;
        } else {
            $this->recursos = (new Recursos())->find("order: modulo, controlador, accion ASC");
        }
        
        $this->title = 'Búsqueda de Recursos';
        
        View::select('index'); // Reutiliza la vista index
    }
    }