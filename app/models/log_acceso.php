<?php
// app/models/logacceso.php

class LogAcceso extends ActiveRecord
{
    public function initialize()
    {
        $this->belongs_to('usuarios');
        
        // Validaciones
        $this->validates_presence_of('usuario_id', 'accion', 'ip');
    }
    
    /**
     * Registrar un acceso exitoso
     */
    public static function registrarAcceso($usuarioId, $accion, $detalles = [])
    {
        $log = new LogAcceso();
        $log->usuario_id = $usuarioId;
        $log->accion = $accion;
        $log->fecha = date('Y-m-d H:i:s');
        $log->ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        //$log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $log->detalles = $detalles ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : [];
        
        return $log->save();
    }

    /**
     * Registrar intento de acceso fallido
     */
    public static function registrarIntentoFallido($identificador, $tipo = 'login')
    {
        $log = new LogAcceso();
        $log->usuario_id = null; // No hay usuario porque falló
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
     * 
     * @param int $admin_id ID del administrador
     * @param string $accion Acción realizada
     * @param string $tabla Tabla afectada
     * @param int $registro_id ID del registro afectado
     * @param array $detalles Detalles adicionales
     * @return bool
     */
    public static function registrarAccionAdmin($admin_id, $accion, $tabla, $registro_id = null, $detalles = [])
    {
        $detalles_completos = array_merge($detalles, [
            'tabla' => $tabla,
            'registro_id' => $registro_id
        ]);
        
        return self::registrarAcceso($admin_id, $accion, $detalles_completos);
    }

    /**
     * Obtener usuario relacionado
     * 
     * @return Usuarios|null
     */
    public function getUsuario()
    {
        if ($this->usuario_id) {
            return (new Usuarios())->find($this->usuario_id);
        }
        return null;
    }
   
    /**
     * Obtener logs por usuario
     */
    public static function getPorUsuario($usuarioId, $limit = 50)
    {
        return (new LogAcceso)->find(
            "conditions: usuario_id = $usuarioId",
            "order: fecha DESC",
            "limit: $limit"
        );
    }
    
    /**
     * Obtener logs por IP
     */
    public static function getPorIp($ip, $limit = 50)
    {
        return (new LogAcceso)->find(
            "conditions: ip = '$ip'",
            "order: fecha DESC",
            "limit: $limit"
        );
    }
    
    /**
     * Obtener intentos fallidos recientes por IP
     */
    public static function getIntentosFallidosIp($ip, $minutos = 15)
    {
        $fecha_limite = date('Y-m-d H:i:s', strtotime("-$minutos minutes"));
        
        return (new LogAcceso)->count([
            "conditions: ip = '$ip' AND accion LIKE 'intento_fallido_%' AND fecha >= '$fecha_limite'"
        ]);
    }
    
    /**
     * Limpiar logs antiguos (más de 90 días)
     */
    public static function limpiarAntiguos($dias = 90)
    {
        //$fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        return (new LogAcceso)->delete_all("fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)");
    }

    /**
     * Limpiar logs antiguos con confirmación y logging
     * 
     * @param int $dias Días a conservar
     * @param int $usuario_id ID del usuario que realiza la limpieza
     * @return array Resultado de la operación
     */
    public static function limpiarConConfirmacion($dias = 90, $usuario_id = null)
    {
        $resultado = [
            'success' => false,
            'eliminados' => 0,
            'mensaje' => '',
            'fecha' => date('Y-m-d H:i:s'),
            'detalles' => ''
        ];
        
        $db = Db::factory();

        try {
            
            // 1. Contar cuántos registros se van a eliminar
            $sql_count = "SELECT COUNT(*) as total 
                        FROM log_acceso 
                        WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)";
            $stmt = $db->query($sql_count);
            $total_a_eliminar = $db->fetch_array($stmt)['total'];
            
            if ($total_a_eliminar == 0) {
                $resultado['mensaje'] = 'No hay registros antiguos para eliminar';
                return $resultado;
            }
            
            // 2. Obtener información de los registros a eliminar (para log)
            $sql_info = "SELECT MIN(fecha) as fecha_mas_antigua, 
                                MAX(fecha) as fecha_mas_reciente,
                                COUNT(DISTINCT usuario_id) as usuarios_unicos
                        FROM log_acceso 
                        WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)";
            $stmt = $db->query($sql_info);
            $info = $db->fetch_array($stmt);
            
            // 3. Eliminar los registros
            $sql_count = "SELECT COUNT(*) as total 
                        FROM log_acceso 
                        WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)";
            $stmt = $db->query($sql_count);
            $eliminados = $db->fetch_array($stmt)['total'];

            $sql_delete = "DELETE FROM log_acceso 
                        WHERE fecha < DATE_SUB(NOW(), INTERVAL $dias DAY)";
            $stmt = $db->query($sql_delete);
            
            // 4. Registrar la acción en el log (si hay usuario)
            if ($usuario_id) {
                $detalles = [
                    'dias_conservados' => $dias,
                    'registros_eliminados' => $eliminados,
                    'fecha_mas_antigua' => $info['fecha_mas_antigua'],
                    'fecha_mas_reciente' => $info['fecha_mas_reciente'],
                    'usuarios_afectados' => $info['usuarios_unicos']
                ];
                
                self::registrarAccionAdmin(
                    $usuario_id,
                    'logs_limpiados',
                    'log_acceso',
                    null,
                    $detalles
                );
            }
            
            // 5. Optimizar tabla después de eliminar muchos registros
            if ($eliminados > 1000) {
                $db->query("OPTIMIZE TABLE log_acceso");
            }
            
            $resultado['success'] = true;
            $resultado['eliminados'] = $eliminados;
            $resultado['mensaje'] = "Se eliminaron $eliminados registros (más de $dias días)";
            $resultado['detalles'] = $info;
            
        } catch (Exception $e) {
            $resultado['mensaje'] = 'Error al limpiar logs';
            Logger::error('Error limpiando logs');
        }
        
        unset($db);
        
        return $resultado;
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
        $total = (new LogAcceso)->count("conditions: $where");
        
        // Accesos exitosos vs fallidos
        $exitosos = (new LogAcceso)->count("conditions: $where AND usuario_id IS NOT NULL");
        $fallidos = (new LogAcceso)->count("conditions: $where AND usuario_id IS NULL");
        
        // Accesos por tipo de acción
        $acciones = (new LogAcceso)->find(
            "columns: accion, COUNT(*) as cantidad",
            "conditions: $where",
            "group: accion",
            "order: cantidad DESC"
        );
        
        // Últimos 7 días
        $fecha_7dias = date('Y-m-d', strtotime('-7 days'));
        $accesos_7dias = (new LogAcceso)->count("conditions: fecha >= '$fecha_7dias'");
        
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

   /**
     * Obtener último acceso de un usuario
     * 
     * @param int $usuario_id ID del usuario
     * @return LogAcceso|null
     */
    public static function ultimoAcceso($usuario_id)
    {
        return (new LogAcceso)->find_first(
            "conditions: usuario_id = $usuario_id AND accion IN ('login', 'login_success')",
            "order: fecha DESC",
            "limit: 1"
        );
    }
    
    /**
     * Obtener últimos N accesos de un usuario
     * 
     * @param int $usuario_id ID del usuario
     * @param int $limit Número de registros
     * @return array
     */
    public static function ultimosAccesos($usuario_id, $limit = 10)
    {
        return (new LogAcceso)->find(
            "conditions: usuario_id = $usuario_id",
            "order: fecha DESC",
            "limit: $limit"
        );
    }
}