<?php
// app/models/menu.php

class Menus extends ActiveRecord
{
    public function initialize()
    {
        $this->belongs_to('Recursos');
        $this->belongs_to('padre', 'model: Menus');
        $this->has_many('hijos', 'Model: Menus');
    }
    
    /**
     * Obtener hijos del menú
     */
    public function getHijos()
    {
        return (new Menus)->find("menus_id = {$this->id}", "order: posicion ASC");
    }
    
    /**
     * Obtener padre del menú
     */
    public function getPadre()
    {
        return (new Menus)->find_first("id = {$this->menus_id}", "order: posicion ASC");
    }
    
    /**
     * Obtener padre del menú
     */
    public function getRecurso()
    {
        return (new Menus)->find_first("id = {$this->menus_id}", "order: posicion ASC");
    }
    
    /**
     * Obtener menús raíz
     */
    public function getRaices()
    {
        return (new Menus)->find("menus_id IS NULL", "order: posicion ASC");
    }
    
    /**
     * Construir árbol completo
     */
    public function getArbol($filtroPermisos = true)
    {
        $menus = (new Menus)->find("conditions: activo = 1", "order: posicion ASC");
        
        if ($filtroPermisos) {
            $menus = self::filtrarPorPermisos($menus);
        }
        
        return self::construirArbol($menus);
    }

    /**
     * Filtrar menús por permisos del usuario actual
     */
    private static function filtrarPorPermisos($menus)
    {
        $usuario = AuthHelper::getAuthUser();
        if (!$usuario) {
            return []; // Usuario no autenticado
        }
        
        $menusAccesibles = [];
        foreach ($menus as $menu) {
            if (self::esAccesible($menu, $usuario)) {
                $menusAccesibles[] = $menu;
            }
        }
        
        return $menusAccesibles;
    }
    
    /**
     * Verificar si un menú es accesible
     */
    private static function esAccesible($menu, $usuario)
    {
        // Caso 1: Menú contenedor sin recurso
        if (!$menu->recursos_id && !$menu->url) {
            // Es accesible si tiene al menos un hijo accesible
            return self::tieneHijosAccesibles($menu->id, $usuario);
        }
        
        // Caso 2: Menú con URL directa (sin recurso)
        if ($menu->url && !$menu->recursos_id) {
            return true; // URL directa = accesible para todos autenticados
        }
        
        // Caso 3: Menú con recurso asociado
        if ($menu->recursos_id) {
            return self::tieneAccesoAlRecurso($usuario, $menu->recursos_id);
        }
        
        return false;
    }
    
    /**
     * Verificar si usuario tiene acceso a un recurso
     */
    private static function tieneAccesoAlRecurso($usuario, $recursoId)
    {
        // Si el usuario no está autenticado
        if (!$usuario) return false;
        
        // Obtener permisos del usuario
        $permisosUsuario = UsuarioPermisos::getPermisosUsuario($usuario['id']);
        
        // Verificar si algún permiso del usuario tiene acceso al recurso
        foreach ($permisosUsuario as $permisoId) {
            if (PermisosRecurso::tieneAcceso($permisoId, $recursoId)) {
                return true;
            }
        }
        
        // Verificar por rol
        if (!empty($usuario['rol'])) {
            $permisoRol = (new Permisos())->find_first("nombre = '{$usuario['rol']}'");
            if ($permisoRol && PermisosRecurso::tieneAcceso($permisoRol->id, $recursoId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si un menú tiene hijos accesibles
     */
    private static function tieneHijosAccesibles($menuId, $usuario)
    {
        $hijos = (new Menus)->find("conditions: menus_id = $menuId AND activo = 1");
        
        foreach ($hijos as $hijo) {
            if (self::esAccesible($hijo, $usuario)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Construir árbol recursivamente
     */
    private static function construirArbol($items, $parentId = null)
    {
        $arbol = array();
        
        foreach ($items as $item) {
            if ($item->menus_id == $parentId) {
                $hijos = self::construirArbol($items, $item->id);
                if ($hijos) {
                    $item->hijos = $hijos;
                }
                $arbol[] = $item;
            }
        }
        
        return $arbol;
    }

    /**
     * Obtener ruta completa del menú (para breadcrumbs)
     */
    public function getRuta()
    {
        $ruta = [];
        $menu = $this;
        
        while ($menu) {
            $ruta[] = $menu;
            $menu = $menu->getPadre();
        }
        
        return array_reverse($ruta);
    }
    
    /**
     * Obtener URL completa con parámetros
     */
    public function getUrlCompleta()
    {
        // 1. Si tiene URL directa
        if (!empty($this->url)) {
            return $this->url;
        }
        
        // 2. Si tiene recurso asociado
        if ($this->recursos_id) {
            $recurso = $this->recursos;
            if ($recurso) {
                $url = $recurso->getUrl();
                
                // Agregar parámetros del menú
                if (!empty($this->parameters)) {
                    $url .= '/' . $this->parameters;
                }
                
                return $url;
            }
        }
        
        return '#';
    }
}