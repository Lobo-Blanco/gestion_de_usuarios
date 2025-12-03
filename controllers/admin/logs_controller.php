<?php
// app/controllers/logs_controller.php

class LogsController extends AdminController
{
    /**
     * Lista de logs
     */
    public function index()
    {
        $pagina = Input::get('pagina', 1);
        $por_pagina = 50;
        
        $this->logs = LogAcceso::paginate([
            "order" => "fecha DESC",
            "page" => $pagina,
            "per_page" => $por_pagina
        ]);
        
        $this->total_logs = LogAcceso::count();
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
            return Redirect::to('logs/index');
        }
        
        if ($this->log->usuarios_id) {
            $this->usuario = $this->log->getUsuario();
        }
        
        $this->title = 'Detalle de Registro';
    }
    
    /**
     * Buscar logs
     */
    public function buscar()
    {
        $termino = Input::get('q');
        $tipo = Input::get('tipo', 'todos');
        
        $condiciones = [];
        
        if ($termino) {
            $condiciones[] = "(accion LIKE '%$termino%' OR ip LIKE '%$termino%' OR detalles LIKE '%$termino%')";
        }
        
        if ($tipo == 'exitosos') {
            $condiciones[] = "usuarios_id IS NOT NULL";
        } elseif ($tipo == 'fallidos') {
            $condiciones[] = "usuarios_id IS NULL";
        }
        
        $where = !empty($condiciones) ? implode(' AND ', $condiciones) : '1=1';
        
        $pagina = Input::get('pagina', 1);
        $por_pagina = 50;
        
        $this->logs = LogAcceso::paginate([
            "conditions" => $where,
            "order" => "fecha DESC",
            "page" => $pagina,
            "per_page" => $por_pagina
        ]);
        
        $this->termino_busqueda = $termino;
        $this->tipo_busqueda = $tipo;
        $this->total_resultados = LogAcceso::count("conditions: $where");
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
        
        $this->estadisticas = LogAcceso::getEstadisticas($desde, $hasta);
        $this->desde = $desde;
        $this->hasta = $hasta;
        
        $this->title = 'Estadísticas de Acceso';
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function limpiar()
    {
        if (Input::hasPost('confirmar')) {
            $dias = Input::post('dias', 90);
            $eliminados = LogAcceso::limpiarAntiguos($dias);
            
            Flash::success("Se eliminaron $eliminados registros antiguos (más de $dias días)");
            
            Event::trigger('admin.logs_limpiados', [
                $eliminados,
                $this->getAuthUser()['id']
            ]);
            
            return Redirect::to('logs/index');
        }
        
        $this->total_antiguos = LogAcceso::count("fecha < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $this->title = 'Limpiar Logs Antiguos';
    }
}