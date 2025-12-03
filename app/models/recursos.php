<?php
// app/models/recurso.php

class Recursos extends ActiveRecord
{
    public function initialize()
    {
        $this->has_many('permisos_recurso');
        $this->has_many('menus');
    }
    
    /**
     * Generar URL desde módulo/controlador/acción
     */
    public function getUrl()
    {
        $urlParts = [];
        
        if (!empty($this->modulo)) {
            $urlParts[] = $this->modulo;
        }
        
        $urlParts[] = $this->controlador;
        $urlParts[] = $this->accion;
        
        return '/' . implode('/', $urlParts);
    }
    
    /**
     * Obtener todos los permisos que tienen acceso a este recurso
     */
    public function getPermisos()
    {
        $relaciones = PermisoRecurso::find("recurso_id = {$this->id}");
        $permisos = [];
        
        foreach ($relaciones as $rel) {
            $permiso = $rel->getPermiso();
            if ($permiso) {
                $permisos[] = $permiso;
            }
        }
        
        return $permisos;
    }
}