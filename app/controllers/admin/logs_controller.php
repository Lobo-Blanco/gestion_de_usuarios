<?php
// app/controllers/logs_controller.php
require_once APP_PATH . "libs/ViewHelper.php";
class LogsController extends AdminController
{
    /**
     * Lista de logs
     */
    public function index($pagina = 1)
    {
        //$pagina = Input::get('pagina');
        $por_pagina = 50;
        
        $this->logs = (new LogAcceso)->paginate(
            "order: fecha DESC",
            "page: $pagina",
            "per_page: $por_pagina"
        );
        
        $this->total_logs = (new LogAcceso)->count();
        $this->pagina_actual = $pagina;
        $this->por_pagina = $por_pagina;
        
        $this->title = 'Registro de Accesos';
    }
    
    /**
     * Ver detalle de log
     */
    public function ver($id)
    {
        $this->log = (new LogAcceso())->find($id);
        
        if (!$this->log) {
            Flash::error('Registro no encontrado');
            Redirect::to('logs/index');
        }
        
        if ($this->log->usuario_id) {
            $this->usuario = $this->log->getUsuario() ?? null;
        }
        
        $this->title = 'Detalle de Registro';
    }
    
    /**
     * Buscar logs
     */
    public function buscar($pagina = 1)
    {
        $termino = Input::get('q');
        $tipo = Input::get('tipo', 'todos');
        
        $condiciones = [];
        
        if ($termino) {
            $condiciones[] = "(accion LIKE '%$termino%' OR ip LIKE '%$termino%' OR detalles LIKE '%$termino%')";
        }
        
        if ($tipo == 'exitosos') {
            $condiciones[] = "usuario_id IS NOT NULL";
        } elseif ($tipo == 'fallidos') {
            $condiciones[] = "usuario_id IS NULL";
        }
        
        $where = !empty($condiciones) ? implode(' AND ', $condiciones) : '1=1';
        
        $por_pagina = 50;
        
        $this->logs = (new LogAcceso)->paginate(
            "conditions: $where",
            "order: fecha DESC",
            "page: $pagina",
            "per_page: $por_pagina"
        );
        
        $this->termino_busqueda = $termino;
        $this->tipo_busqueda = $tipo;
        $this->total_resultados = (new LogAcceso)->count("conditions: $where");
        $this->pagina_actual = $pagina;
        $this->por_pagina = $por_pagina;
        
        $this->title = 'Búsqueda de Logs';
        
        View::select('index'); // Reutiliza la vista index
    }
    
    /**
     * Estadísticas de logs
     */
    public function estadisticas()
    {
        $desde = Input::get('desde', date('Y-m-d', strtotime('-30 days')));
        $hasta = Input::get('hasta', date('Y-m-d'));
        
        // Usar el método estático del modelo
        $this->estadisticas = (new LogAcceso)->getEstadisticas($desde, $hasta);
        $this->desde = $desde;
        $this->hasta = $hasta;
        
        $this->title = 'Estadísticas de Acceso';
    }
    
    /**
     * Limpiar logs antiguos - CON FUNCIONALIDAD COMPLETA
     */
    public function limpiar()
    {
        // Solo administradores pueden limpiar logs
        if (!$this->is_admin) {
            Flash::error('No tiene permisos para esta acción');
            Redirect::to('admin/logs/index');
        }
        $this->title = 'Limpiar Logs Antiguos';
        
        // Si se envió el formulario de confirmación
        if (isset($_POST['confirmar'])) {
            $this->procesar_limpieza();
        }
        
        // Si se envió el formulario de vista previa
        if (isset($_POST['vista_previa'])) {
            $this->generar_vista_previa();
        }
        
        // Obtener estadísticas para mostrar
        $this->cargar_estadisticas_limpieza();
    }

    /**
     * Procesar la limpieza de logs
     */
    private function procesar_limpieza()
    {
        try {
            $dias = (int)Input::post('dias', 90);
            $confirm_backup = Input::post('confirm_backup', 0);
            $confirm_understanding = Input::post('confirm_understanding', 0);
            
            // Validaciones
            if ($dias < 1 || $dias > 3650) {
                Flash::error('El número de días debe estar entre 1 y 3650');
                return;
            }
            
            if (!$confirm_understanding) {
                Flash::error('Debe confirmar que comprende que esta acción no se puede deshacer');
                return;
            }
            
            if (!$confirm_backup) {
                Flash::warning('No confirmó haber hecho backup. Se recomienda encarecidamente.');
                // No impedimos, solo advertimos
            }
            
            // Obtener usuario actual
            $usuario_id = AuthHelper::getAuthUser()['id'];
            
            // Ejecutar limpieza
            $resultado = (new LogAcceso)->limpiarConConfirmacion($dias, $usuario_id);Logger::Log("limpiar", print_r($resultado, true));
            
            if (isset($resultado['success'])) {
                Flash::success($resultado['mensaje']);
                
                // Trigger event
                Event::trigger('admin.logs_limpiados', [
                    $resultado['eliminados'],
                    $usuario_id,
                    $dias,
                    $resultado['detalles']
                ]);
                
                // Redirigir a la lista de logs
                Redirect::toAction('index');
                
            } else {
                Flash::error($resultado['mensaje']);
            }
            
        } catch (Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
            Logger::error('Error en limpieza de logs: ' . $e->getMessage());
        }
    }

    /**
     * Generar vista previa de lo que se eliminará
     */
    private function generar_vista_previa()
    {
        $dias = (int)Input::post('dias', 90);
        
        $db = Db::factory();
            
        try {
            // Contar registros a eliminar
            $sql = "SELECT COUNT(*) as total,
                        MIN(fecha) as fecha_minima,
                        MAX(fecha) as fecha_maxima,
                        COUNT(DISTINCT usuario_id) as usuarios_unicos
                    FROM log_acceso 
                    WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)";
            
            $stmt = $db->query($sql);
            $vista_previa = $db->fetch_array($stmt);
            
            // Obtener algunos ejemplos
            $sql_ejemplos = "SELECT id, fecha, usuario_id, accion, ip
                            FROM log_acceso 
                            WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)
                            ORDER BY fecha ASC
                            LIMIT 5";
            
            $stmt = $db->query($sql_ejemplos);
            $ejemplos = $db->fetchAll($stmt);
            
            $this->vista_previa = $vista_previa;
            $this->ejemplos = $ejemplos;
            $this->dias_vista_previa = $dias;
            

            Flash::info("Vista previa generada: se eliminarían {$vista_previa['total']} registros");
            
        } catch (Exception $e) {
            Flash::error('Error generando vista previa: ' . $e->getMessage());
        }

        unset($db);
    }

    /**
     * Cargar estadísticas para la página de limpieza
     */
    private function cargar_estadisticas_limpieza()
    {
        $db = Db::factory();
 
        try {
            // Estadísticas generales
            $this->total_registros = (new LogAcceso)->count();
            
            
            // Registros por antigüedad
            $sql = "SELECT 
                    COUNT(*) as total,
                    CASE 
                        WHEN fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Última semana'
                        WHEN fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Último mes'
                        WHEN fecha >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'Últimos 3 meses'
                        WHEN fecha >= DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 'Último año'
                        ELSE 'Más de 1 año'
                    END as periodo
                    FROM log_acceso
                    GROUP BY periodo
                    ORDER BY 
                    CASE periodo
                        WHEN 'Última semana' THEN 1
                        WHEN 'Último mes' THEN 2
                        WHEN 'Últimos 3 meses' THEN 3
                        WHEN 'Último año' THEN 4
                        ELSE 5
                    END";
            
            //$stmt = $db->query($sql);
            $this->estadisticas_antiguedad = $db->fetch_all($sql);
            
            // Tamaño estimado de la tabla
            $sql_size = "SELECT 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE() 
                        AND table_name = 'log_acceso'";
            
            $stmt = $db->query($sql_size);
            $size = $db->fetch_array($stmt);
            $this->tamano_tabla = $size['size_mb'] ?? 0;
            
            // Fecha del registro más antiguo
            $sql_oldest = "SELECT MIN(fecha) as mas_antiguo FROM log_acceso";
            $stmt = $db->query($sql_oldest);
            $oldest = $db->fetch_Array($stmt);
            
            $this->fecha_mas_antigua = $oldest['mas_antiguo'] ?? null;
            
        } catch (Exception $e) {
            Logger::error('Error cargando estadísticas de logs: ' . $e->getMessage());
            $this->estadisticas_antiguedad = [];
            $this->tamano_tabla = 0;
        } finally {
            // Esto se ejecutará siempre, incluso si hay excepciones
            if (isset($db)) {
                unset($db);
            }
        }
    }
}