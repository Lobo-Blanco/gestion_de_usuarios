<?php
// config/resources.php

// Definición de recursos del sistema
return [
    'admin.usuarios.index' => [
        'nombre' => 'admin.usuarios.index',
        'modulo' => 'admin',
        'controlador' => 'usuarios',
        'accion' => 'index',
        'descripcion' => 'Lista de usuarios'
    ],
    'admin.usuarios.editar' => [
        'nombre' => 'admin.usuarios.editar',
        'modulo' => 'admin',
        'controlador' => 'usuarios',
        'accion' => 'editar',
        'descripcion' => 'Editar usuario'
    ],
    'admin.permisos.index' => [
        'nombre' => 'admin.permisos.index',
        'modulo' => 'admin',
        'controlador' => 'permisos',
        'accion' => 'index',
        'descripcion' => 'Gestión de permisos'
    ],
    // ... más recursos
];

// En bootstrap
/* $resources = Config::get('resources');
foreach ($resources as $resource) {
    // Crear o actualizar recursos en BD
    $recurso = (new Recursos())->find_first("nombre = '{$resource['nombre']}'");
    if (!$recurso) {
        $recurso = new Recursos();
    }
    
    $recurso->nombre = $resource['nombre'];
    $recurso->modulo = $resource['modulo'] ?? null;
    $recurso->controlador = $resource['controlador'];
    $recurso->accion = $resource['accion'];
    $recurso->descripcion = $resource['descripcion'];
    $recurso->save();
} */