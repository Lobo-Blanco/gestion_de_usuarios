<?php
// app/models/usuario_permiso.php

class UsuarioPermisos extends ActiveRecord
{
    public function initialize()
    {
        $this->belongs_to('usuarios');
        $this->belongs_to('permisos');
        
        // Validaciones
        $this->validates_presence_of('usuarios_id', 'permisos_id');
        $this->validates_uniqueness_of('usuarios_id', 'scope: permisos_id');
    }
    
    /**
     * Obtener todos los permisos de un usuario
     */
    public static function getPermisosUsuario($usuarioId)
    {
        $permisos = array();
        
        $usuarioPermisos = (new UsuarioPermisos)->find("conditions: usuarios_id = $usuarioId");
        foreach ($usuarioPermisos as $up) {
            $permiso = $up->getPermisos();
            if ($permiso) {
                $permisos[] = $permiso->id;
            }
        }
        
        return $permisos;
    }
    
    /**
     * Verificar si un usuario tiene un permiso específico
     */
    public static function tienePermiso($usuarioId, $permisoId)
    {
        return self::count("usuarios_id = $usuarioId AND permisos_id = $permisoId") > 0;
    }
    
    /**
     * Verificar si un usuario tiene un permiso por nombre
     */
    public static function tienePermisoPorNombre($usuarioId, $permisoNombre)
    {
        // Buscar el permiso por nombre
        $permiso = (new Permisos())->find_first("nombre = '$permisoNombre'");
        if (!$permiso) {
            return false;
        }
        
        return self::tienePermiso($usuarioId, $permiso->id);
    }
    
    /**
     * Asignar permiso a usuario
     */
    public static function asignarPermiso($usuarioId, $permisoId, $creadoPor = null)
    {
        // Verificar si ya existe la relación
        $existente = self::find_first("usuarios_id = $usuarioId AND permisos_id = $permisoId");
        if ($existente) {
            return true; // Ya existe
        }
        
        $usuarioPermiso = new UsuarioPermisos();
        $usuarioPermiso->usuarios_id = $usuarioId;
        $usuarioPermiso->permisos_id = $permisoId;
        $usuarioPermiso->creado_por = $creadoPor;
        
        return $usuarioPermiso->save();
    }
    
    /**
     * Remover permiso de usuario
     */
    public static function removerPermiso($usuarioId, $permisoId)
    {
        $usuarioPermiso = self::find_first("usuarios_id = $usuarioId AND permisos_id = $permisoId");
        
        if ($usuarioPermiso) {
            return $usuarioPermiso->delete();
        }
        
        return false;
    }
    
    /**
     * Remover todos los permisos de un usuario
     */
    public static function removerTodosPermisos($usuarioId)
    {
        return self::delete_all("usuarios_id = $usuarioId");
    }
    
    /**
     * Obtener usuarios que tienen un permiso específico
     */
    public static function getUsuariosConPermiso($permisoId)
    {
        $usuarios = array();
        
        $usuarioPermisos = self::find("conditions: permisos_id = $permisoId");
        foreach ($usuarioPermisos as $up) {
            $usuario = $up->getUsuario();
            if ($usuario) {
                $usuarios[] = $usuario;
            }
        }
        
        return $usuarios;
    }
    
    /**
     * Obtener permisos con información de usuario
     */
    public static function getConUsuarios($permisoId = null)
    {
        $sql = "SELECT up.*, u.codigo as usuario_codigo, u.nombre as usuario_nombre, 
                       p.nombre as permiso_nombre, p.descripcion as permiso_descripcion
                FROM usuario_permiso up
                INNER JOIN usuarios u ON up.usuarios_id = u.id
                INNER JOIN permisos p ON up.permisos_id = p.id";
        
        if ($permisoId) {
            $sql .= " WHERE up.permisos_id = $permisoId";
        }
        
        $sql .= " ORDER BY u.nombre ASC, p.nombre ASC";
        
        return self::find_by_sql($sql);
    }
    
    /**
     * Obtener estadísticas de asignación de permisos
     */
    public static function getEstadisticas()
    {
        $sql = "SELECT 
                    COUNT(DISTINCT usuarios_id) as total_usuarios,
                    COUNT(DISTINCT permisos_id) as total_permisos,
                    COUNT(*) as total_asignaciones,
                    AVG(permisos_por_usuario) as promedio_permisos_por_usuario
                FROM (
                    SELECT usuarios_id, COUNT(*) as permisos_por_usuario
                    FROM usuario_permiso
                    GROUP BY usuarios_id
                ) as subquery";
        
        $result = self::find_by_sql($sql);
        return $result ? $result[0] : null;
    }
    
    /**
     * Sincronizar permisos de usuario (elimina los antiguos y agrega los nuevos)
     */
    public static function sincronizarPermisos($usuarioId, $permisosIds, $creadoPor = null)
    {
        // Obtener permisos actuales
        $permisosActuales = self::getPermisosUsuario($usuarioId);
        
        // Determinar qué agregar y qué eliminar
        $agregar = array_diff($permisosIds, $permisosActuales);
        $eliminar = array_diff($permisosActuales, $permisosIds);
        
        $resultados = array(
            'agregados' => 0,
            'eliminados' => 0,
            'sin_cambios' => count(array_intersect($permisosActuales, $permisosIds))
        );
        
        // Eliminar permisos que ya no están
        foreach ($eliminar as $permisoId) {
            if (self::removerPermiso($usuarioId, $permisoId)) {
                $resultados['eliminados']++;
            }
        }
        
        // Agregar nuevos permisos
        foreach ($agregar as $permisoId) {
            if (self::asignarPermiso($usuarioId, $permisoId, $creadoPor)) {
                $resultados['agregados']++;
            }
        }
        
        return $resultados;
    }
    
    /**
     * Copiar permisos de un usuario a otro
     */
    public static function copiarPermisos($usuarioOrigenId, $usuarioDestinoId, $creadoPor = null)
    {
        $permisosOrigen = self::getPermisosUsuario($usuarioOrigenId);
        
        if (empty($permisosOrigen)) {
            return array(
                'copiados' => 0,
                'mensaje' => 'El usuario origen no tiene permisos asignados'
            );
        }
        
        $copiados = 0;
        foreach ($permisosOrigen as $permisoId) {
            if (self::asignarPermiso($usuarioDestinoId, $permisoId, $creadoPor)) {
                $copiados++;
            }
        }
        
        return array(
            'copiados' => $copiados,
            'total' => count($permisosOrigen),
            'mensaje' => "Se copiaron $copiados de " . count($permisosOrigen) . " permisos"
        );
    }
    
    /**
     * Buscar asignaciones por término
     */
    public static function buscar($termino)
    {
        $sql = "SELECT up.*, u.codigo, u.nombre as usuario_nombre, p.nombre as permiso_nombre
                FROM usuario_permiso up
                INNER JOIN usuarios u ON up.usuarios_id = u.id
                INNER JOIN permisos p ON up.permisos_id = p.id
                WHERE u.codigo LIKE '%$termino%' 
                   OR u.nombre LIKE '%$termino%' 
                   OR u.email LIKE '%$termino%'
                   OR p.nombre LIKE '%$termino%'
                   OR p.descripcion LIKE '%$termino%'
                ORDER BY u.nombre ASC, p.nombre ASC";
        
        return self::find_by_sql($sql);
    }
    
    /**
     * Obtener asignaciones recientes
     */
    public static function getRecientes($limit = 10)
    {
        return self::find([
            "order" => "created_at DESC",
            "limit" => $limit,
            "include" => ["usuario", "permiso"]
        ]);
    }
}