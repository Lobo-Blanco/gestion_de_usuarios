<?php
/**
 * Modelo para la gestión de permisos
 */
class Permisos extends ActiveRecord
{
    /**
     * @var string Nombre de la tabla
     */
    protected $_table = 'permisos';
    
    /**
     * @var string Clave primaria
     */
    protected $_primary_key = 'id';
    
    /**
     * @var array Validación de campos
     */
    protected $_validations = [
        'codigo' => 'required|alpha_dash|max:100|unique',
        'nombre' => 'required|max:150'
    ];
    
    /**
     * Obtener permisos agrupados por módulo y categoría
     */
    public function getAgrupados()
    {
        $sql = "SELECT * FROM {$this->_table} WHERE activo = 1 ORDER BY modulo, categoria, codigo";
        $permisos = $this->find_by_sql($sql);
        
        $agrupados = [];
        foreach ($permisos as $permiso) {
            $modulo = $permiso['modulo'] ?: 'general';
            $categoria = $permiso['categoria'] ?: 'otros';
            
            if (!isset($agrupados[$modulo])) {
                $agrupados[$modulo] = [];
            }
            
            if (!isset($agrupados[$modulo][$categoria])) {
                $agrupados[$modulo][$categoria] = [];
            }
            
            $agrupados[$modulo][$categoria][] = $permiso;
        }
        
        return $agrupados;
    }
    
    /**
     * Obtener permisos por módulo
     */
    public function getByModulo($modulo)
    {
        return (new $this->_table)->find("modulo = $modulo AND activo = 1", "order: codigo");
    }
    
    /**
     * Obtener permiso por código
     */
    public function getByCodigo($codigo)
    {
        return (new $this->_table)->find_first("codigo = $codigo");
    }
    
    /**
     * Obtener permisos disponibles (no asignados a un rol)
     */
    public function getDisponibles($rol_id)
    {
        $sql = "SELECT p.* 
                FROM permisos p
                WHERE p.activo = 1 
                AND p.id NOT IN (
                    SELECT permiso_id 
                    FROM rol_permisos 
                    WHERE rol_id = $rol_id
                )
                ORDER BY p.modulo, p.codigo";
        
        return $this->find_by_sql($sql);
    }
    
    /**
     * Buscar permisos
     */
    public function buscar($termino)
    {
        $sql = "SELECT * FROM {$this->_table} 
                WHERE (codigo LIKE %{$termino}% OR nombre LIKE %{$termino}% OR descripcion LIKE %{$termino}%) 
                AND activo = 1
                ORDER BY modulo, codigo";
        
        return $this->find_by_sql($sql);
    }
    
    /**
     * Obtener módulos únicos
     */
    public function getModulos()
    {
        $sql = "SELECT DISTINCT modulo FROM {$this->_table} WHERE modulo IS NOT NULL AND modulo != '' ORDER BY modulo";
        $resultados = $this->find_by_sql($sql);
        
        $modulos = [];
        foreach ($resultados as $row) {
            $modulos[] = $row->modulo;
        }
        
        return $modulos;
    }
    
    /**
     * Obtener categorías únicas por módulo
     */
    public function getCategorias($modulo = null)
    {
        if ($modulo) {
            $sql = "SELECT DISTINCT categoria FROM {$this->_table} 
                    WHERE modulo = ? AND categoria IS NOT NULL AND categoria != '' 
                    ORDER BY categoria";
            $resultados = $this->find_by_sql($sql, [$modulo]);
        } else {
            $sql = "SELECT DISTINCT categoria FROM {$this->_table} 
                    WHERE categoria IS NOT NULL AND categoria != '' 
                    ORDER BY categoria";
            $resultados = $this->find_by_sql($sql);
        }
        
        $categorias = [];
        foreach ($resultados as $row) {
            $categorias[] = $row->categoria;
        }
        
        return $categorias;
    }
    
    /**
     * Contar permisos por módulo
     */
    public function contarPorModulo()
    {
        $sql = "SELECT modulo, COUNT(*) as total 
                FROM permisos 
                WHERE activo = 1 
                GROUP BY modulo 
                ORDER BY modulo";
        
        return $this->find_by_sql($sql);
    }
}