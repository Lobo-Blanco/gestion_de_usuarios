<?php
// app/models/logacceso.php

class LogAcceso extends ActiveRecord
{
    public function initialize()
    {
        $this->belongs_to('usuarios');
        
        // Validaciones
        $this->validates_presence_of('usuarios_id', 'accion', 'ip');
    }
    
    /**
     * Registrar un acceso exitoso
     */
    public static function registrarAcceso($usuarioId, $accion, $detalles = null)
    {
        $log = new LogAcceso();
        $log->usuarios_id = $usuarioId;
        $log->accion = $accion;
        $log->ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $log->detalles = $detalles ? json_encode($detalles) : null;
        $log->fecha = date('Y-m-d H:i:s');
        
        return $log->save();
    }
    
    /**
     * Registrar intento de acceso fallido
     */
    public static function registrarIntentoFallido($identificador, $tipo = 'login')
    {
        $log = new LogAcceso();
        $log->usuarios_id = null; // No hay usuario porque falló
        $log->accion = "intento_fallido_$tipo";
        $log->ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $log->detalles = json_encode([
            'identificador' => $identificador,
            'fecha' => date('Y-m-d H:i:s')
        ]);
        $log->fecha = date('Y-m-d H:i:s');
        
        return $log->save();
    }
    
    /**
     * Registrar acción administrativa
     */
    public static function registrarAccionAdmin($usuarioId, $accion, $objeto = null, $objetoId = null)
    {
        $detalles = [];
        if ($objeto) {
            $detalles['objeto'] = $objeto;
        }
        if ($objetoId) {
            $detalles['objeto_id'] = $objetoId;
        }
        
        return self::registrarAcceso($usuarioId, "admin_$accion", $detalles);
    }
    
    /**
     * Obtener logs por usuario
     */
    public static function getPorUsuario($usuarioId, $limit = 50)
    {
        return self::find([
            "conditions" => "usuarios_id = $usuarioId",
            "order" => "fecha DESC",
            "limit" => $limit
        ]);
    }
    
    /**
     * Obtener logs por IP
     */
    public static function getPorIp($ip, $limit = 50)
    {
        return self::find([
            "conditions" => "ip = '$ip'",
            "order" => "fecha DESC",
            "limit" => $limit
        ]);
    }
    
    /**
     * Obtener intentos fallidos recientes por IP
     */
    public static function getIntentosFallidosIp($ip, $minutos = 15)
    {
        $fecha_limite = date('Y-m-d H:i:s', strtotime("-$minutos minutes"));
        
        return self::count([
            "conditions" => "ip = '$ip' AND accion LIKE 'intento_fallido_%' AND fecha >= '$fecha_limite'"
        ]);
    }
    
    /**
     * Limpiar logs antiguos (más de 90 días)
     */
    public static function limpiarAntiguos($dias = 90)
    {
        $fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        return self::delete_all("fecha < '$fecha_limite'");
    }
    
    /**
     * Obtener estadísticas de acceso
     */
    public static function getEstadisticas($desde = null, $hasta = null)
    {
        $where = "1=1";
        
        if ($desde) {
            $where .= " AND fecha >= '$desde'";
        }
        if ($hasta) {
            $where .= " AND fecha <= '$hasta'";
        }
        
        // Total de accesos
        $total = self::count("conditions: $where");
        
        // Accesos exitosos vs fallidos
        $exitosos = self::count("conditions: $where AND usuarios_id IS NOT NULL");
        $fallidos = self::count("conditions: $where AND usuarios_id IS NULL");
        
        // Accesos por tipo de acción
        $acciones = self::find([
            "columns" => "accion, COUNT(*) as cantidad",
            "conditions" => $where,
            "group" => "accion",
            "order" => "cantidad DESC"
        ]);
        
        // Últimos 7 días
        $fecha_7dias = date('Y-m-d', strtotime('-7 days'));
        $accesos_7dias = self::count("conditions: fecha >= '$fecha_7dias'");
        
        return [
            'total' => $total,
            'exitosos' => $exitosos,
            'fallidos' => $fallidos,
            'acciones' => $acciones,
            'ultimos_7_dias' => $accesos_7dias
        ];
    }
    
    /**
     * Formatear detalles para mostrar
     */
    public function getDetallesFormateados()
    {
        if (!$this->detalles) {
            return '';
        }
        
        $detalles = json_decode($this->detalles, true);
        
        if (is_array($detalles)) {
            $html = '<ul>';
            foreach ($detalles as $key => $value) {
                $html .= '<li><strong>' . h($key) . ':</strong> ' . h($value) . '</li>';
            }
            $html .= '</ul>';
            return $html;
        }
        
        return h($this->detalles);
    }
}