<?php
// app/models/permiso_recurso.php

class PermisosRecurso extends ActiveRecord
{
    public function initialize()
    {
        $this->belongs_to('permisos');
        $this->belongs_to('recursos');
    }
    
    /**
     * Verificar si un permiso tiene acceso a un recurso
     */
    public static function tieneAcceso($permisoId, $recursoId)
    {
        return (new PermisosRecurso)->count("permisos_id = $permisoId AND recursos_id = $recursoId") > 0;
    }
    
    /**
     * Obtener todos los recursos accesibles para un permiso
     */
    public static function getRecursosPermiso($permisoId)
    {
        $relaciones = (new PermisosRecurso)->find("permisos_id = $permisoId");
        $recursos = [];
        
        foreach ($relaciones as $rel) {
            $recursos[] = $rel->getRecurso();
        }
        
        return $recursos;
    }
}