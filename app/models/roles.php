<?php
/**
 * Modelo para la gestión de roles
 */
class roles extends ActiveRecord
{
    /**
     * @var string Nombre de la tabla
     */
    protected $_table = 'roles';
    
    /**
     * @var string Clave primaria
     */
    protected $_primary_key = 'id';
    
    /**
     * @var array Validación de campos
     */
    protected $_validations = [
        'codigo' => 'required|alpha_dash|max:50|unique',
        'nombre' => 'required|max:100',
        'nivel' => 'numeric|min:0|max:100'
    ];
    
    /**
     * Obtener todos los roles activos
     */
    public function getActivos($columns = "")
    {
        return (new Roles)->find($columns, "activo = 1", "order: nivel DESC, nombre ASC");
    }
    
    /**
     * Obtener código de rol por id
     */
    public static function getById($rol_id)
    {
        return (new Roles)->find_first("id = {$rol_id}");
    }
    
    /**
     * Obtener roles con permisos
     */
    public function getWithPermissions($rol_id = null)
    {
        if ($rol_id) {
            $sql = "SELECT r.*, 
                    GROUP_CONCAT(p.codigo SEPARATOR ',') as permisos_codigos,
                    GROUP_CONCAT(p.nombre SEPARATOR ', ') as permisos_nombres
                    FROM roles r
                    LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
                    LEFT JOIN permisos p ON rp.permiso_id = p.id
                    WHERE r.id = ?
                    GROUP BY r.id";
            
            return $this->fetchOne($sql, [$rol_id]);
        } else {
            $sql = "SELECT r.*, 
                    COUNT(rp.permiso_id) as total_permisos
                    FROM roles r
                    LEFT JOIN rol_permisos rp ON r.id = rp.rol_id
                    GROUP BY r.id
                    ORDER BY r.nivel DESC, r.nombre ASC";
            
            return $this->fetchAll($sql);
        }
    }
    
    /**
     * Asignar permisos a un rol
     */
    public function asignarPermisos($rol_id, $permisos_ids)
    {
        // Eliminar permisos actuales
        $this->eliminarPermisos($rol_id);
        
        // Insertar nuevos permisos
        if (!empty($permisos_ids)) {
            $values = [];
            $params = [];
            
            foreach ($permisos_ids as $permiso_id) {
                $values[] = '(?, ?)';
                $params[] = $rol_id;
                $params[] = $permiso_id;
            }
            
            $sql = "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES " . implode(', ', $values);
            return $this->execute($sql, $params);
        }
        
        return true;
    }
    
    /**
     * Eliminar permisos de un rol
     */
    public function eliminarPermisos($rol_id)
    {
        $sql = "DELETE FROM rol_permisos WHERE rol_id = ?";
        return $this->execute($sql, [$rol_id]);
    }
    
    /**
     * Obtener permisos del rol
     */
    public function getPermisos($rol_id)
    {
        $sql = "SELECT p.* 
                FROM permisos p
                INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = ?
                ORDER BY p.modulo, p.categoria, p.codigo";
        
        return $this->fetchAll($sql, [$rol_id]);
    }
    
    /**
     * Verificar si un rol tiene un permiso específico
     */
    public function tienePermiso($rol_id, $permiso_codigo)
    {
        $sql = "SELECT COUNT(*) as total
                FROM rol_permisos rp
                INNER JOIN permisos p ON rp.permiso_id = p.id
                WHERE rp.rol_id = ? AND p.codigo = ?";
        
        $result = $this->fetchOne($sql, [$rol_id, $permiso_codigo]);
        return $result && $result['total'] > 0;
    }
    
    /**
     * Obtener usuarios con este rol
     */
    public function getUsuarios($rol_id)
    {
        $sql = "SELECT u.id, u.codigo, u.nombre, u.email, u.activo
                FROM usuarios u
                WHERE u.rol_id = ?
                ORDER BY u.nombre ASC";
        
        return $this->fetchAll($sql, [$rol_id]);
    }
    
    /**
     * Contar usuarios por rol
     */
    public function contarUsuarios()
    {
        $sql = "SELECT r.id, r.codigo, r.nombre, COUNT(u.id) as total_usuarios
                FROM roles r
                LEFT JOIN usuarios u ON r.id = u.rol_id
                GROUP BY r.id
                ORDER BY r.nivel DESC";
        
        return $this->fetchAll($sql);
    }
}