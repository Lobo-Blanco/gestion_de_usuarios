<?php
/**
 * Controlador para gestión de roles
 */
require APP_PATH . "controllers/admin_controller.php";
class RolesController extends AdminController
{
    /**
     * @var Roles
     */
    protected $rol_model;
    
    /**
     * @var Permisos
     */
    protected $permiso_model;
    
    /**
     * Before filter
     */
    protected function before_filter()
    {
        parent::before_filter();
        
        // Verificar permisos
        if (!$this->has_permission('roles', 'access')) {
            $this->handle_unauthorized_access('roles.access');
        }
        
        // Cargar modelos
        $this->rol_model = new Roles();
        $this->permiso_model = new Permisos();
        
        // Configurar vista
        $this->title = 'Gestión de Roles';
    }
    
    /**
     * Listar todos los roles
     */
    public function index()
    {
        $this->roles = $this->rol_model->getWithPermissions();
        $this->total_roles = count($this->roles);
        
        // Estadísticas
        $this->estadisticas = $this->rol_model->contarUsuarios();
    }
    
    /**
     * Ver detalles de un rol
     */
    public function ver($id)
    {
        if (!$this->has_permission('roles', 'view')) {
            $this->handle_unauthorized_access('roles.view');
        }
        
        $rol = $this->rol_model->getWithPermissions($id);
        
        if (!$rol) {
            Flash::error('Rol no encontrado');
            $this->redirect('roles');
        }
        
        $this->rol = $rol;
        $this->permisos = $this->rol_model->getPermisos($id);
        $this->usuarios = $this->rol_model->getUsuarios($id);
        
        $this->title = 'Detalles del Rol: ' . $rol->nombre;
    }
    
    /**
     * Crear nuevo rol
     */
    public function crear()
    {
        View::select("_form");
        
        if (!$this->has_permission('roles', 'create')) {
            $this->handle_unauthorized_access('roles.create');
        }

        $this->title = 'Crear Nuevo Rol';
        
        // Configurar variables para la vista
        $this->action = 'crear';
        $this->rol = null; // No hay rol para crear
        $this->permisos_ids = [];
        // Cargar todos los permisos agrupados
        $this->permisos_agrupados = $this->permiso_model->getAgrupados();
        
        $this->title = 'Crear Nuevo Rol';
        
        if (Input::hasPost('guardar')) {
            $this->procesar_creacion();
        }
        
    }
    
    /**
     * Editar rol existente
     */
    public function editar($id)
    {
        View::select("_form");
        
        if (!$this->has_permission('roles', 'edit')) {
            $this->handle_unauthorized_access('roles.edit');
        }
        
        $rol = $this->rol_model->find($id);
        
        if (!$rol) {
            Flash::error('Rol no encontrado');
            $this->redirect('roles');
        }
        
        $this->title = 'Editar Rol: ' . $rol->nombre;
        
        $this->action = 'editar';
        $this->rol = $rol;
        $this->permisos_agrupados = $this->permiso_model->getAgrupados();

        $this->permisos_rol = $this->rol_model->getPermisos($id);
        
        // Crear array de IDs de permisos del rol para el formulario
        $this->permisos_ids = [];
        foreach ($this->permisos_rol as $permiso) {
            $this->permisos_ids[] = $permiso->id;
        }

        // Cargar estadísticas adicionales
        $this->total_usuarios = count($this->rol_model->getUsuarios($id));

        if (Input::hasPost('guardar')) {
            $this->procesar_edicion($id);
        }
        
    }
    
    /**
     * Eliminar rol
     */
    public function eliminar($id)
    {
        if (!$this->has_permission('roles', 'delete')) {
            $this->handle_unauthorized_access('roles.delete');
        }
        
        $rol = $this->rol_model->find($id);
        
        if (!$rol) {
            Flash::error('Rol no encontrado');
            $this->redirect('roles');
        }
        
        // Verificar si hay usuarios con este rol
        $usuarios = $this->rol_model->getUsuarios($id);
        
        if (count($usuarios) > 0) {
            Flash::error('No se puede eliminar el rol porque tiene usuarios asignados');
            $this->redirect('roles/ver/' . $id);
        }
        
        if (Input::hasPost('confirmar')) {
            try {
                // Eliminar permisos asignados
                $this->rol_model->eliminarPermisos($id);
                
                // Eliminar rol
                if ($this->rol_model->delete($id)) {
                    Flash::success('Rol eliminado correctamente');
                    Logger::info("Rol eliminado: {$rol['nombre']} por usuario {$this->auth_user['id']}");
                } else {
                    Flash::error('Error al eliminar el rol');
                }
                
                $this->redirect('roles');
                
            } catch (Exception $e) {
                Flash::error('Error: ' . $e->getMessage());
            }
        }
        
        $this->rol = $rol;
        $this->title = 'Eliminar Rol: ' . $rol['nombre'];
    }
    
    /**
     * Asignar permisos a rol
     */
    public function permisos($id)
    {
        if (!$this->has_permission('roles', 'assign_permissions')) {
            $this->handle_unauthorized_access('roles.assign_permissions');
        }
        
        $rol = $this->rol_model->find($id);
        
        if (!$rol) {
            Flash::error('Rol no encontrado');
            $this->redirect('roles');
        }
        
        $this->title = 'Asignar Permisos: ' . $rol->nombre;
        
        if (Input::hasPost('asignar')) {
            $this->procesar_asignacion_permisos($id);
        }
        
        $this->rol = $rol;
        $this->permisos_agrupados = $this->permiso_model->getAgrupados();
        $this->permisos_rol = $this->rol_model->getPermisos($id);
        
        // Crear array de IDs de permisos del rol
        $this->permisos_ids = [];
        foreach ($this->permisos_rol as $permiso) {
            $this->permisos_ids[] = $permiso->id;
        }
    }
    
    /**
     * ============================================
     * MÉTODOS PRIVADOS
     * ============================================
     */
    
    /**
     * Procesar creación de rol
     */
    private function procesar_creacion()
    {
        try {
            $data = [
                'codigo' => Input::post('codigo'),
                'nombre' => Input::post('nombre'),
                'descripcion' => Input::post('descripcion'),
                'nivel' => Input::post('nivel', 0),
                'activo' => Input::post('activo', 1)
            ];
            
            // Validar datos
            if (empty($data['codigo']) || empty($data['nombre'])) {
                throw new Exception('Código y nombre son requeridos');
            }
            
            // Verificar si ya existe el código
            $existente = $this->rol_model->getByCodigo($data['codigo']);
            if ($existente) {
                throw new Exception('Ya existe un rol con ese código');
            }
            
            // Crear rol
            $rol_id = $this->rol_model->create($data);
            
            if ($rol_id) {
                // Asignar permisos si se seleccionaron
                $permisos_ids = Input::post('permisos', []);
                if (!empty($permisos_ids)) {
                    $this->rol_model->asignarPermisos($rol_id, $permisos_ids);
                }
                
                Flash::success('Rol creado correctamente');
                Logger::info("Rol creado: {$data['nombre']} por usuario {$this->auth_user['id']}");
                
                $this->redirect('roles/ver/' . $rol_id);
            } else {
                throw new Exception('Error al crear el rol');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar edición de rol
     */
    private function procesar_edicion($id)
    {
        try {
            $data = [
                'nombre' => Input::post('nombre'),
                'descripcion' => Input::post('descripcion'),
                'nivel' => Input::post('nivel', 0),
                'activo' => Input::post('activo', 1),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Actualizar rol
            if ($this->rol_model->update($id, $data)) {
                // Actualizar permisos
                $permisos_ids = Input::post('permisos', []);
                $this->rol_model->asignarPermisos($id, $permisos_ids);
                
                Flash::success('Rol actualizado correctamente');
                Logger::info("Rol actualizado ID: {$id} por usuario {$this->auth_user['id']}");
                
                $this->redirect('roles/ver/' . $id);
            } else {
                throw new Exception('Error al actualizar el rol');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar asignación de permisos
     */
    private function procesar_asignacion_permisos($rol_id)
    {
        try {
            $permisos_ids = Input::post('permisos', []);
            
            // Asignar permisos
            if ($this->rol_model->asignarPermisos($rol_id, $permisos_ids)) {
                Flash::success('Permisos asignados correctamente');
                Logger::info("Permisos actualizados para rol ID: {$rol_id} por usuario {$this->auth_user['id']}");
                
                $this->redirect('roles/ver/' . $rol_id);
            } else {
                throw new Exception('Error al asignar permisos');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
}