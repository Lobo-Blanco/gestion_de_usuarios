<?php
// app/models/permiso.php

class Permisos extends ActiveRecord
{
    public function initialize()
    {
        $this->has_many('permisos_recurso');
        $this->has_many('usuario_permisos');
        
        // Validaciones
        $this->validates_presence_of('nombre');
        $this->validates_uniqueness_of('nombre');
    }
    
    /**
     * Obtener todos los recursos de este permiso
     */
    public function getRecursos()
    {
        $permisoRecursos = $this->getPermisoRecurso();
        $recursos = array();
        
        foreach ($permisoRecursos as $pr) {
            $recurso = $pr->getRecurso();
            if ($recurso) {
                $recursos[] = $recurso;
            }
        }
        
        return $recursos;
    }
    
    /**
     * Obtener todos los usuarios con este permiso
     */
    public function getUsuarios()
    {
        $usuarioPermisos = $this->getUsuarioPermiso();
        $usuarios = array();
        
        foreach ($usuarioPermisos as $up) {
            $usuario = $up->getUsuario();
            if ($usuario) {
                $usuarios[] = $usuario;
            }
        }
        
        return $usuarios;
    }
    
    /**
     * Verificar si el permiso tiene acceso a un recurso
     */
    public function tieneAccesoRecurso($recursoId)
    {
        return (new PermisoRecurso())->count(
            "permiso_id = {$this->id} AND recurso_id = $recursoId"
        ) > 0;
    }
    
    /**
     * Verificar si un usuario tiene este permiso
     */
    public function esAsignadoAUsuario($usuarioId)
    {
        return (new UsuarioPermiso())->count(
            "permiso_id = {$this->id} AND usuario_id = $usuarioId"
        ) > 0;
    }
    
    /**
     * Obtener permisos populares (más asignados)
     */
    public static function getPopulares($limit = 10)
    {
        return self::find_by_sql("
            SELECT p.*, COUNT(up.id) as total_usuarios
            FROM permisos p
            LEFT JOIN usuario_permiso up ON p.id = up.permiso_id
            GROUP BY p.id
            ORDER BY total_usuarios DESC, p.nombre ASC
            LIMIT $limit
        ");
    }
    
    /**
     * Buscar permisos por término
     */
    public static function buscar($termino)
    {
        return self::find(
            "nombre LIKE '%$termino%' OR descripcion LIKE '%$termino%'",
            "order: nombre ASC"
        );
    }
    
    /**
     * Crear permisos por defecto del sistema
     */
    public static function crearPermisosPorDefecto()
    {
        $permisos_defecto = array(
            array(
                'nombre' => 'ver_dashboard',
                'descripcion' => 'Ver el panel de control'
            ),
            array(
                'nombre' => 'ver_usuarios',
                'descripcion' => 'Ver lista de usuarios'
            ),
            array(
                'nombre' => 'editar_usuarios',
                'descripcion' => 'Editar información de usuarios'
            ),
            array(
                'nombre' => 'eliminar_usuarios',
                'descripcion' => 'Eliminar usuarios'
            ),
            array(
                'nombre' => 'ver_permisos',
                'descripcion' => 'Ver lista de permisos'
            ),
            array(
                'nombre' => 'editar_permisos',
                'descripcion' => 'Editar permisos'
            ),
            array(
                'nombre' => 'ver_logs',
                'descripcion' => 'Ver registros del sistema'
            ),
            array(
                'nombre' => 'ver_menus',
                'descripcion' => 'Ver menús del sistema'
            ),
            array(
                'nombre' => 'editar_menus',
                'descripcion' => 'Editar menús del sistema'
            ),
        );
        
        $creados = 0;
        foreach ($permisos_defecto as $permiso_data) {
            $existente = self::find_first("nombre = '{$permiso_data['nombre']}'");
            if (!$existente) {
                $permiso = new Permisos();
                $permiso->nombre = $permiso_data['nombre'];
                $permiso->descripcion = $permiso_data['descripcion'];
                if ($permiso->save()) {
                    $creados++;
                }
            }
        }
        
        return $creados;
    }
}