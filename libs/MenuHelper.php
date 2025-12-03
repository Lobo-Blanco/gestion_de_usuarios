<?php
// app/helpers/menu_helper.php

class MenuHelper
{
    /**
     * Renderizar menú jerárquico
     */
    public static function renderMenu($menus, $level = 0)
    {
        if (empty($menus)) {
            return '';
        }

        $output = '';
        
        foreach ($menus as $menu) {
            $hasChildren = isset($menu->hijos) && !empty($menu->hijos);
            $isActive = self::isActiveMenu($menu->url);
            
            $liClass = $isActive ? 'active' : '';
            $aClass = 'nav-link' . ($hasChildren ? ' dropdown-toggle' : '');
            $aAttrs = $hasChildren ? ' data-bs-toggle="dropdown"' : '';
            
            if ($level === 0) {
                $output .= '<li class="nav-item ' . ($hasChildren ? 'dropdown' : '') . ' ' . $liClass . '">';
                $output .= '<a class="' . $aClass . '" href="' . ($menu->url ?: '#') . '"' . $aAttrs . '>';
                $output .= '<i class="' . ($menu->icono ?: 'bi-circle') . ' me-2"></i>';
                $output .= htmlspecialchars($menu->nombre);
                $output .= '</a>';
                
                if ($hasChildren) {
                    $output .= '<ul class="dropdown-menu">';
                    $output .= self::renderMenu($menu->hijos, $level + 1);
                    $output .= '</ul>';
                }
                
                $output .= '</li>';
            } else {
                // Submenús
                if ($hasChildren) {
                    $output .= '<li class="dropdown-submenu">';
                    $output .= '<a class="dropdown-item dropdown-toggle" href="#">';
                    $output .= '<i class="' . ($menu->icono ?: 'bi-circle') . ' me-2"></i>';
                    $output .= htmlspecialchars($menu->nombre);
                    $output .= '</a>';
                    $output .= '<ul class="dropdown-menu">';
                    $output .= self::renderMenu($menu->hijos, $level + 1);
                    $output .= '</ul>';
                    $output .= '</li>';
                } else {
                    $output .= '<li>';
                    $output .= '<a class="dropdown-item" href="' . $menu->url . '">';
                    $output .= '<i class="' . ($menu->icono ?: 'bi-circle') . ' me-2"></i>';
                    $output .= htmlspecialchars($menu->nombre);
                    $output .= '</a>';
                    $output .= '</li>';
                }
            }
        }
        
        return $output;
    }

    /**
     * Verificar si el menú está activo
     */
    private static function isActiveMenu($url)
    {
        if (!$url) {
            return false;
        }
        
        $currentUrl = $_SERVER['REQUEST_URI'];
        return strpos($currentUrl, $url) === 0;
    }

    /**
     * Obtener menús del usuario actual
     */
    public static function getUserMenu()
    {
        $user = Session::get('codigo', 'app_auth');
        if (!$user) {
            return array();
        }
        
        $acl = Acl2::factory('database');
        return $acl->getUserMenus($user);
    }

    /**
     * Verificar permiso para recurso
     */
    public static function can($resource)
    {
        $user = Session::get('codigo', 'app_auth');
        if (!$user) {
            return false;
        }
        
        $acl = Acl2::factory('database');
        return $acl->canAccess($user, $resource);
    }

    /**
     * Renderizar breadcrumb
     */
    public static function renderBreadcrumb($menus, $currentUrl = null)
    {
        if (!$currentUrl) {
            $currentUrl = $_SERVER['REQUEST_URI'];
        }
        
        $breadcrumb = array();
        self::findBreadcrumbPath($menus, $currentUrl, $breadcrumb);
        
        if (empty($breadcrumb)) {
            return '';
        }
        
        $output = '<nav aria-label="breadcrumb">';
        $output .= '<ol class="breadcrumb">';
        
        foreach ($breadcrumb as $index => $item) {
            $isLast = $index === count($breadcrumb) - 1;
            
            if ($isLast) {
                $output .= '<li class="breadcrumb-item active" aria-current="page">';
                $output .= htmlspecialchars($item['nombre']);
                $output .= '</li>';
            } else {
                $output .= '<li class="breadcrumb-item">';
                $output .= '<a href="' . $item['url'] . '">' . htmlspecialchars($item['nombre']) . '</a>';
                $output .= '</li>';
            }
        }
        
        $output .= '</ol>';
        $output .= '</nav>';
        
        return $output;
    }

    /**
     * Encontrar ruta para breadcrumb
     */
    private static function findBreadcrumbPath($menus, $targetUrl, &$path, $currentPath = array())
    {
        foreach ($menus as $menu) {
            $newPath = $currentPath;
            $newPath[] = array(
                'nombre' => $menu->nombre,
                'url' => $menu->url ?: '#'
            );
            
            if ($menu->url && strpos($targetUrl, $menu->url) === 0) {
                $path = $newPath;
                return true;
            }
            
            if (isset($menu->hijos)) {
                if (self::findBreadcrumbPath($menu->hijos, $targetUrl, $path, $newPath)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Obtener árbol completo de menús
     */
    public static function getMenuTree()
    {
        return (new Menus)->getArbol();
    }
}