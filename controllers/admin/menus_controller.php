<?php
// app/controllers/menus_controller.php

require APP_PATH . "controllers/admin_controller.php";
class MenusController extends AdminController
{
    /**
     * Lista de menús (vista de árbol)
     */
    public function index()
    {
        $this->menus = (new Menus)->getArbol(false); // Todos los menús sin filtrar
        $this->title = 'Gestión de Menús';
    }
    
    /**
     * Ver detalle de menú
     */
    public function ver($id)
    {
        $this->menu = (new Menus())->find_first($id);

        if (!$this->menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }

        // Obtener información del recurso asociado
        if ($this->menu->recursos_id) {
            $this->recurso = $this->menu->recursos;
        }
        $this->id = $this->menu->id;                
        // Obtener menú padre
        if ($this->menu->menus_id) {
            $this->padre = $this->menu->getPadre();
        }

        // Obtener hijos
        $this->hijos = $this->menu->getHijos();
        
        $this->title = 'Menú: ' . $this->menu->nombre;
    }
    
    /**
     * Formulario para crear menú
     */
    public function crear()
    {
        View::Select("_form");

        // Obtener menús para selector de padre
        $this->menus_disponibles = $this->getMenusParaSelector();
        
        // Obtener recursos para selector
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        
        $this->title = 'Crear Nuevo Menú';
    }
    
    /**
     * Guardar nuevo menú
     */
    public function guardar()
    {
        if (Input::hasPost('nombre', 'posicion')) {
            $menu = new Menus();
            $menu->nombre = Input::post('nombre');
            $menu->descripcion = Input::post('descripcion');
            $menu->menus_id = Input::post('menus_id') ?: null;
            $menu->recursos_id = Input::post('recursos_id') ?: null;
            $menu->url = Input::post('url');
            $menu->parameters = Input::post('parameters');
            $menu->icono = Input::post('icono');
            $menu->posicion = Input::post('posicion');
            $menu->activo = Input::post('activo', 0);
            $menu->visible = Input::post('visible', 1);
            
            // Validar tipo de enlace seleccionado
            $tipo_enlace = Input::post('tipo_enlace', 'contenedor');
            
            if ($tipo_enlace === 'recurso') {
                if (empty($menu->recursos_id)) {
                    Flash::error('Debe seleccionar un recurso cuando el tipo es "Recurso del Sistema"');
                    Redirect::toAction('crear');
                }
                $menu->url = null; // Limpiar URL si se seleccionó recurso
            } elseif ($tipo_enlace === 'url') {
                if (empty($menu->url)) {
                    Flash::error('Debe especificar una URL cuando el tipo es "URL Directa"');
                    Redirect::toAction('crear');
                }
                $menu->recursos_id = null; // Limpiar recurso si se seleccionó URL
            } else {
                // Es contenedor, limpiar ambos
                $menu->recursos_id = null;
                $menu->url = null;
                $menu->parameters = null;
            }
            
            if ($menu->save()) {
                Flash::success('Menú creado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    $this->getAuthUser()['id'],
                    'menu_creado',
                    'menu',
                    $menu->id
                );
                
                Event::trigger('admin.menu_creado', [
                    $menu->id,
                    $this->getAuthUser()['id']
                ]);
                
                // Limpiar cache del ACL
                $acl = Acl2::factory('database');
                $acl->clearAllCache();
                
                Redirect::toAction('ver/' . $menu->id);
            } else {
                //Flash::error('Error al crear menú');
            }
        } else {
            Flash::error('El nombre y posición son obligatorios');
        }
        
        // Mantener datos del formulario para rellenar
        $this->menus_disponibles = $this->getMenusParaSelector();
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        $this->menus = (new Menus(Input::post()));
        $this->crear();
    }
    
    /**
     * Formulario para editar menú
     */
    public function editar($id)
    {
        View::Select("_form");

        $this->menu = (new Menus())->find($id);
        
        if (!$this->menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }
        
        // Obtener menús para selector de padre (excluyendo este y sus hijos)
        $this->menus_disponibles = $this->getMenusParaSelector($id);
        
        // Obtener recursos para selector
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        
        // Determinar tipo de enlace
        if ($this->menu->recursos_id) {
            $this->tipo_enlace = 'recurso';
        } elseif ($this->menu->url) {
            $this->tipo_enlace = 'url';
        } else {
            $this->tipo_enlace = 'contenedor';
        }
        
        $this->title = 'Editar Menú: ' . $this->menu->nombre;
    }
    
    /**
     * Actualizar menú
     */
    public function actualizar($id)
    {
        $menu = (new Menus())->find($id);
        
        if (!$menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }
        
        if (Input::hasPost('nombre', 'posicion')) {
            $nombre_original = $menu->nombre;
            $menu->nombre = Input::post('nombre');
            $menu->descripcion = Input::post('descripcion');
            
            // Validar que no sea padre de sí mismo
            $nuevo_padre_id = Input::post('menus_id') ?: null;
            if ($nuevo_padre_id == $id) {
                Flash::error('Un menú no puede ser padre de sí mismo');
                Redirect::toAction('editar/' . $id);
            }
            
            // Validar que no sea ancestro de sí mismo
            if ($this->esAncestro($id, $nuevo_padre_id)) {
                Flash::error('No puede asignar un menú hijo como padre');
                Redirect::toAction('editar/' . $id);
            }
            
            $menu->menus_id = $nuevo_padre_id;
            $menu->recursos_id = Input::post('recursos_id') ?: null;
            $menu->url = Input::post('url');
            $menu->parameters = Input::post('parameters');
            $menu->icono = Input::post('icono');
            $menu->posicion = Input::post('posicion');
            $menu->activo = Input::post('activo', 0);
            $menu->visible = Input::post('visible', 1);
            
            // Validar tipo de enlace seleccionado
            $tipo_enlace = Input::post('tipo_enlace', 'contenedor');
            
            if ($tipo_enlace === 'recurso') {
                if (empty($menu->recursos_id)) {
                    Flash::error('Debe seleccionar un recurso cuando el tipo es "Recurso del Sistema"');
                    Redirect::toAction('editar/' . $id);
                }
                $menu->url = null; // Limpiar URL si se seleccionó recurso
            } elseif ($tipo_enlace === 'url') {
                if (empty($menu->url)) {
                    Flash::error('Debe especificar una URL cuando el tipo es "URL Directa"');
                    Redirect::toAction('editar/' . $id);
                }
                $menu->recursos_id = null; // Limpiar recurso si se seleccionó URL
            } else {
                // Es contenedor, limpiar ambos
                $menu->recursos_id = null;
                $menu->url = null;
                $menu->parameters = null;
            }
            
            if ($menu->update()) {
                Flash::success('Menú actualizado correctamente');
                
                // Registrar en log
                LogAcceso::registrarAccionAdmin(
                    $this->getAuthUser()['id'],
                    'menu_actualizado',
                    'menu',
                    $menu->id
                );
                
                Event::trigger('admin.menu_actualizado', [
                    $menu->id,
                    $this->getAuthUser()['id']
                ]);
                
                // Limpiar cache del ACL
                $acl = Acl2::factory('database');
                $acl->clearAllCache();
                
                Redirect::toAction('ver/' . $menu->id);
            } else {
                Flash::error('Error al actualizar menú: ' . implode(', ', $menu->get_errors()));
            }
        } else {
            Flash::error('El nombre y posición son obligatorios');
        }
        
        // Mantener datos del formulario
        $this->menu = (object)array_merge((array)$menu, Input::post());
        $this->menus_disponibles = $this->getMenusParaSelector($id);
        $this->recursos = (new Recursos())->find("order: modulo, controlador, accion");
        $this->tipo_enlace = Input::post('tipo_enlace', 'contenedor');
        $this->editar($id);
    }
    
    /**
     * Eliminar menú
     */
    public function eliminar($id)
    {
        $menu = (new Menus())->find($id);
        
        if (!$menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }
        
        // Verificar si tiene hijos
        $hijos = $menu->getHijos();
        if (count($hijos) > 0) {
            Flash::error('No se puede eliminar el menú porque tiene ' . count($hijos) . ' submenú(s). Elimine primero los submenús.');
            Redirect::toAction('index');
        }
        
        // Guardar información para el log
        $nombre_menu = $menu->nombre;
        
        if ($menu->delete()) {
            Flash::success('Menú eliminado correctamente');
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'menu_eliminado',
                'menu',
                $id
            );
            
            Event::trigger('admin.menu_eliminado', [
                $id,
                $nombre_menu,
                $this->getAuthUser()['id']
            ]);
            
            // Limpiar cache del ACL
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
        } else {
            Flash::error('Error al eliminar menú');
        }
        
        Redirect::toAction('index');
    }
    
    /**
     * Cambiar orden de menús (para drag & drop)
     */
    public function ordenar()
    {
        if (Input::isAjax() && Input::hasPost('menus')) {
            $menus = Input::post('menus');
            $actualizados = 0;
            
            foreach ($menus as $menuData) {
                $menu = (new Menus())->find($menuData['id']);
                if ($menu) {
                    $menu->posicion = $menuData['orden'];
                    $menu->menus_id = $menuData['padre_id'] ?: null;
                    
                    if ($menu->update()) {
                        $actualizados++;
                    }
                }
            }
            
            // Limpiar cache del ACL
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
            
            echo json_encode([
                'success' => true,
                'actualizados' => $actualizados,
                'mensaje' => "Se actualizó el orden de $actualizados menús"
            ]);
            
            return false;
        }
        
        echo json_encode([
            'success' => false,
            'mensaje' => 'Solicitud inválida'
        ]);
        
        return false;
    }
    
    /**
     * Activar/desactivar menú
     */
    public function toggle_activo($id)
    {
        $menu = (new Menus())->find($id);
        
        if (!$menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }
        
        $menu->activo = $menu->activo ? 0 : 1;
        
        if ($menu->update()) {
            $estado = $menu->activo ? 'activado' : 'desactivado';
            Flash::success("Menú $estado correctamente");
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'menu_cambio_estado',
                'menu',
                $menu->id
            );
            
            Event::trigger('admin.menu_cambio_estado', [
                $menu->id,
                $menu->activo,
                $this->getAuthUser()['id']
            ]);
            
            // Limpiar cache del ACL
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
        } else {
            Flash::error('Error al cambiar estado del menú');
        }
        
        Redirect::toAction('index');
    }
    
    /**
     * Mostrar/ocultar menú
     */
    public function toggle_visible($id)
    {
        $menu = (new Menus())->find($id);
        
        if (!$menu) {
            Flash::error('Menú no encontrado');
            Redirect::toAction('index');
        }
        
        $menu->visible = $menu->visible ? 0 : 1;
        
        if ($menu->update()) {
            $estado = $menu->visible ? 'visible' : 'oculto';
            Flash::success("Menú marcado como $estado");
            
            // Registrar en log
            LogAcceso::registrarAccionAdmin(
                $this->getAuthUser()['id'],
                'menu_cambio_visible',
                'menu',
                $menu->id
            );
            
            // Limpiar cache del ACL
            $acl = Acl2::factory('database');
            $acl->clearAllCache();
        } else {
            Flash::error('Error al cambiar visibilidad del menú');
        }
        
        Redirect::toAction('index');
    }
    
    /**
     * Obtener menús para selector (excluyendo un menú y sus descendientes)
     */
    private function getMenusParaSelector($excluirId = null)
    {
        $menus = (new Menus())->find("order: posicion ASC");
        $result = array();
        
        foreach ($menus as $menu) {
            // Excluir el menú especificado y sus descendientes
            if ($excluirId && ($menu->id == $excluirId || $this->esDescendiente($menu->id, $excluirId, $menus))) {
                continue;
            }
            
            $menu->nivel = $this->calcularNivel($menu->id, $menus);
            $result[] = $menu;
        }
        
        return $result;
    }
    
    /**
     * Calcular nivel de un menú en la jerarquía
     */
    private function calcularNivel($menuId, $menus, $nivel = 0)
    {
        foreach ($menus as $menu) {
            if ($menu->id == $menuId) {
                if ($menu->menus_id) {
                    return $this->calcularNivel($menu->menus_id, $menus, $nivel + 1);
                }
                return $nivel;
            }
        }
        return $nivel;
    }
    
    /**
     * Verificar si un menú es descendiente de otro
     */
    private function esDescendiente($posibleDescendienteId, $ancestroId, $menus)
    {
        foreach ($menus as $menu) {
            if ($menu->id == $posibleDescendienteId) {
                if ($menu->menus_id == $ancestroId) {
                    return true;
                } elseif ($menu->menus_id) {
                    return $this->esDescendiente($menu->menus_id, $ancestroId, $menus);
                }
            }
        }
        return false;
    }
    
    /**
     * Verificar si un menú es ancestro de otro
     */
    private function esAncestro($menuId, $posibleAncestroId)
    {
        if (!$posibleAncestroId) {
            return false;
        }
        
        $menus = (new Menus())->find();
        return $this->esDescendiente($posibleAncestroId, $menuId, $menus);
    }
    
    /**
     * Método auxiliar para obtener usuario autenticado
     */
    private function getAuthUser()
    {
        return [
            'id' => Session::get('id', 'app_auth'),
            'codigo' => Session::get('codigo', 'app_auth'),
            'nombre' => Session::get('nombre', 'app_auth')
        ];
    }
}