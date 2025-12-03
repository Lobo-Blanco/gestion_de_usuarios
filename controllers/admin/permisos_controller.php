<?php
/**
 * Controlador para gestión de permisos
 */
require APP_PATH . "controllers/admin_controller.php";
class PermisosController extends AdminController
{
    /**
     * @var Permisos
     */
    protected $permisos_model;
    
    /**
     * Before filter
     */
    protected function before_filter()
    {
        parent::before_filter();
        
        // Cargar modelo
        $this->permisos_model = new Permisos;
        
        // Configurar vista
        $this->title = 'Gestión de Permisos';
    }
    
    /**
     * Listar todos los permisos
     */
    public function index()
    {
        // Solo administradores pueden gestionar permisos
        if (!$this->is_admin) {
            $this->handle_unauthorized_access('permisos.access');
        }

        $modulo = Input::get('modulo');
        $categoria = Input::get('categoria');
        
        if ($modulo) {
            $this->permisos = $this->permisos_model->getByModulo($modulo);
        } else {
            $this->permisos_agrupados = $this->permisos_model->getAgrupados();
        }
        
        $this->modulos = $this->permisos_model->getModulos();
        $this->categorias = $this->permisos_model->getCategorias($modulo);
        $this->modulo_actual = $modulo;
        $this->categoria_actual = $categoria;
        
        // Estadísticas
        $this->estadisticas = $this->permisos_model->contarPorModulo();
    }
    
    /**
     * Crear nuevo permiso
     */
    public function crear()
    {
        View::select("_form");

        $this->title = 'Crear Nuevo Permiso';
        
        if (Input::hasPost('guardar')) {
            $this->procesar_creacion();
        }
        
        $this->modulos = $this->getModulosDisponibles();
        $this->categorias = $this->getCategoriasDisponibles();
    }
    
    /**
     * Editar permiso existente
     */
    public function editar($id)
    {
        View::select("_form");

        $permiso = $this->permisos_model->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            $this->redirect('permisos');
        }
        
        $this->title = 'Editar Permiso: ' . $permiso['nombre'];
        
        if (Input::hasPost('guardar')) {
            $this->procesar_edicion($id);
        }
        
        $this->permiso = $permiso;
        $this->modulos = $this->getModulosDisponibles();
        $this->categorias = $this->getCategoriasDisponibles();
    }
    
    /**
     * Eliminar permiso
     */
    public function eliminar($id)
    {
        $permiso = $this->permisos_model->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            $this->redirect('permisos');
        }
        
        // Verificar si el permiso está asignado a algún rol
        $db = Db::factory();
        $sql = "SELECT COUNT(*) as total FROM rol_permisos WHERE permiso_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            Flash::error('No se puede eliminar el permiso porque está asignado a uno o más roles');
            $this->redirect('permisos');
        }
        
        if (Input::hasPost('confirmar')) {
            try {
                if ($this->permisos_model->delete($id)) {
                    Flash::success('Permiso eliminado correctamente');
                    Logger::info("Permiso eliminado: {$permiso['codigo']} por usuario {$this->auth_user['id']}");
                } else {
                    Flash::error('Error al eliminar el permiso');
                }
                
                $this->redirect('permisos');
                
            } catch (Exception $e) {
                Flash::error('Error: ' . $e->getMessage());
            }
        }
        
        $this->permiso = $permiso;
        $this->title = 'Eliminar Permiso: ' . $permiso['nombre'];
    }
    
    /**
     * Buscar permisos
     */
    public function buscar()
    {
        $termino = Input::get('q');
        
        if ($termino) {
            $this->resultados = $this->permisos_model->buscar($termino);
            $this->termino = $termino;
        } else {
            $this->redirect('permisos');
        }
        
        $this->title = 'Buscar Permisos: ' . $termino;
    }
    
    /**
     * ============================================
     * MÉTODOS PRIVADOS
     * ============================================
     */
    
    /**
     * Procesar creación de permiso
     */
    private function procesar_creacion()
    {
        try {
            $data = [
                'codigo' => Input::post('codigo'),
                'nombre' => Input::post('nombre'),
                'descripcion' => Input::post('descripcion'),
                'modulo' => Input::post('modulo'),
                'categoria' => Input::post('categoria'),
                'activo' => Input::post('activo', 1)
            ];
            
            // Validar datos
            if (empty($data['codigo']) || empty($data['nombre'])) {
                throw new Exception('Código y nombre son requeridos');
            }
            
            // Verificar si ya existe el código
            $existente = $this->permisos_model->getByCodigo($data['codigo']);
            if ($existente) {
                throw new Exception('Ya existe un permiso con ese código');
            }
            
            // Crear permiso
            if ($this->permisos_model->create($data)) {
                Flash::success('Permiso creado correctamente');
                Logger::info("Permiso creado: {$data['codigo']} por usuario {$this->auth_user['id']}");
                
                $this->redirect('permisos');
            } else {
                throw new Exception('Error al crear el permiso');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Procesar edición de permiso
     */
    private function procesar_edicion($id)
    {
        try {
            $data = [
                'nombre' => Input::post('nombre'),
                'descripcion' => Input::post('descripcion'),
                'modulo' => Input::post('modulo'),
                'categoria' => Input::post('categoria'),
                'activo' => Input::post('activo', 1)
            ];
            
            // Actualizar permiso
            if ($this->permisos_model->update($id, $data)) {
                Flash::success('Permiso actualizado correctamente');
                Logger::info("Permiso actualizado ID: {$id} por usuario {$this->auth_user['id']}");
                
                $this->redirect('permisos');
            } else {
                throw new Exception('Error al actualizar el permiso');
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener módulos disponibles
     */
    private function getModulosDisponibles()
    {
        $modulos = $this->permisos_model->getModulos();
        
        // Agregar módulos comunes
        $modulos_comunes = [
            'usuarios' => 'Usuarios',
            'roles' => 'Roles',
            'permisos' => 'Permisos',
            'configuracion' => 'Configuración',
            'dashboard' => 'Dashboard',
            'reportes' => 'Reportes',
            'sistema' => 'Sistema'
        ];
        
        foreach ($modulos_comunes as $codigo => $nombre) {
            if (!in_array($codigo, $modulos)) {
                $modulos[] = $codigo;
            }
        }
        
        sort($modulos);
        return $modulos;
    }
    
    /**
     * Obtener categorías disponibles
     */
    private function getCategoriasDisponibles()
    {
        $categorias = $this->permisos_model->getCategorias();
        
        // Agregar categorías comunes
        $categorias_comunes = [
            'administracion',
            'gestion',
            'configuracion',
            'visualizacion',
            'creacion',
            'edicion',
            'eliminacion',
            'importacion',
            'exportacion'
        ];
        
        foreach ($categorias_comunes as $categoria) {
            if (!in_array($categoria, $categorias)) {
                $categorias[] = $categoria;
            }
        }
        
        sort($categorias);
        return $categorias;
    }
}