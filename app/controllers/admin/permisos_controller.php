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
        if (!AuthMiddleware::isAdmin()) {
            $this->handle_unauthorized_access('permisos.access');
        }

        $modulo = Input::get('modulo');
        $categoria = Input::get('categoria');
        
        if ($modulo) {
            $this->permisos = $this->permisos_model->getByModulo($modulo);
            $this->vista_agrupada = false;  // Flag para la vista
        } else {
            $this->permisos = $this->permisos_model->getAgrupados($categoria);
            $this->vista_agrupada = true;   // Flag para la vista
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
        
        $this->title = 'Editar Permiso: ' . $permiso->nombre;
        
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
        $sql = "SELECT COUNT(*) as total FROM rol_permisos WHERE permiso_id = $id";
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        unset($db);

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

    /**
     * Ver detalle de un permiso
     */
    public function ver($id)
    {
        // Solo administradores pueden ver detalles
         if (!AuthMiddleware::isAdmin()) {
            $this->handle_unauthorized_access('permisos.view');
        }
        
        $permiso = $this->permisos_model->find($id);
        
        if (!$permiso) {
            Flash::error('Permiso no encontrado');
            Redirect::to('permisos');
        }
        
        $this->title = 'Detalle del Permiso: ' . $permiso->nombre;
        
        // Cargar información del permiso
        $this->permiso = $permiso;
        
        // Cargar roles que tienen este permiso
        $this->roles_con_permiso = $this->get_roles_con_permiso($id);
        
        // Cargar recursos asociados (si hay)
        $this->recursos_asociados = $this->get_recursos_asociados($id);
        
        // Cargar estadísticas de uso
        $this->estadisticas = $this->get_estadisticas_permiso($id);
        
        // Cargar actividad reciente relacionada
        $this->actividad_reciente = $this->get_actividad_reciente($id);
    }

    /**
     * Obtener roles que tienen este permiso
     */
    private function get_roles_con_permiso($permiso_id)
    {
        try {
            $db = Db::factory();
            $sql = "SELECT r.*, COUNT(u.id) as total_usuarios
                    FROM roles r
                    INNER JOIN rol_permisos rp ON r.id = rp.rol_id
                    LEFT JOIN usuarios u ON u.rol_id = r.id
                    WHERE rp.permiso_id = $permiso_id
                    GROUP BY r.id
                    ORDER BY r.nivel DESC, r.nombre";
            
            $stmt = $db->in_query_assoc($sql);
            return $db->fetch_array($stmt);
        } catch (Exception $e) {
            Logger::error('Error obteniendo roles con permiso: ' . $e->getMessage());
            return [];
        } finally {
            // Esto se ejecutará siempre, incluso si hay excepciones
            if (isset($db)) {
                unset($db);
            }
        }
    }

    /**
     * Obtener recursos asociados a este permiso
     */
    private function get_recursos_asociados($permiso_id)
    {
        try {
            // Si tienes tabla permisos_recursos
            $db = Db::factory();
            $sql = "SELECT re.* 
                    FROM recursos re
                    INNER JOIN permisos_recursos pr ON re.id = pr.recurso_id
                    WHERE pr.permiso_id = $permiso_id
                    ORDER BY re.modulo, re.controlador, re.accion";
            
            $stmt = $db->in_query_assoc($sql);
            return $db->fetch_array(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Si no existe la tabla, retornar array vacío
            return [];
        } finally {
            // Esto se ejecutará siempre, incluso si hay excepciones
            if (isset($db)) {
                unset($db);
            }
        }
    }

    /**
     * Obtener estadísticas del permiso
     */
    private function get_estadisticas_permiso($permiso_id)
    {
        try {
            $db = Db::factory();
            
            // Total de roles con este permiso
            $sql_roles = "SELECT COUNT(DISTINCT rol_id) as total_roles 
                        FROM rol_permisos 
                        WHERE permiso_id = $permiso_id";
            $stmt = $db->in_query_assoc($sql_roles);
            $total_roles = $db->fetch_array($stmt);
            
            // Total de usuarios con roles que tienen este permiso
            $sql_usuarios = "SELECT COUNT(DISTINCT u.id) as total_usuarios
                            FROM usuarios u
                            INNER JOIN roles r ON u.rol_id = r.id
                            INNER JOIN rol_permisos rp ON r.id = rp.rol_id
                            WHERE rp.permiso_id = $permiso_id AND u.activo = 1";
            $stmt = $db->in_query_assoc($sql_usuarios);
            $total_usuarios = $stmt->fetch_array($stmt);
            
            return [
                'total_roles' => $total_roles,
                'total_usuarios' => $total_usuarios,
                'creado' => $this->permiso['created_at'] ?? null,
                'actualizado' => $this->permiso['updated_at'] ?? null
            ];
        } catch (Exception $e) {
            Logger::error('Error obteniendo estadísticas: ' . $e->getMessage());
            return ['total_roles' => 0, 'total_usuarios' => 0];
        } finally {
            // Esto se ejecutará siempre, incluso si hay excepciones
            if (isset($db)) {
                unset($db);
            }
        }
    }

    /**
     * Obtener actividad reciente relacionada con el permiso
     */
    private function get_actividad_reciente($permiso_id)
    {
        try {
            return (new LogAcceso)->find(
                "conditions: accion LIKE '%permiso%' AND detalles LIKE '%\"permiso_id\":\"$permiso_id\"%'",
                "order: fecha DESC",
                "limit: 10"
            );
        } catch (Exception $e) {
            return [];
        }

    }
}