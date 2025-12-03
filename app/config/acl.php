<?php
return [
    'default_role' => 'usuario',
    'cache_enabled' => true,
    'cache_lifetime' => 3600,
    'auto_scan' => true,
    'scan_paths' => [
        APP_PATH . 'controllers/',
        APP_PATH . 'models/'
    ],
    'excluded_paths' => [],
    'public_resources' => [
        'index/*',
        'auth/*',
        'usuario/login',
        'usuario/register',
        'password/*'
    ],
    'admin_resources' => [
        'admin/*',
        'admin/configuracion/*',
        'admin/usuarios/*',
        'admin/roles/*',
        'admin/permisos/*'
    ],
    'permission_denied_redirect' => 'admin/dashboard',
    'login_redirect' => 'admin/dashboard',
    'logout_redirect' => 'index/index'
];