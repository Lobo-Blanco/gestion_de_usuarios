<?php
// config/auth.php

return [
    // Modos de autenticación permitidos
    'authentication_modes' => ['remote_user', 'credentials', 'both'],
    'auth_algorithm' => "md5",
    
    // Configuración para REMOTE_USER
    'remote_user' => [
        'enabled' => true,
        'field_name' => 'REMOTE_USER', // Puede ser 'PHP_AUTH_USER' u otro
        'auto_create_user' => true,    // Crear usuario automáticamente si no existe
        'default_role' => 'usuario',   // Rol por defecto para usuarios creados automáticamente
    ],
    
    // Configuración para autenticación con credenciales
    'credentials' => [
        'enabled' => true,
        'allow_registration' => false,              // Permitir registro de nuevos usuarios
        'verify_email' => false,                    // Verificar email antes de activar cuenta
        'auto_activate' => false,                   // Activar cuenta automáticamente
        'allow_restore_password_by_email' => true,  // Permitir restaurar contraseña por email
    ],
    
    // Configuración de sesión
    'session' => [
        'namespace' => 'app_auth',
        'key' => 'app_user_session',
        'lifetime' => 7200, // 2 horas en segundos
    ],
    
    // Roles administrativos
    'admin_roles' => ['admin', 'superadmin'],
    
    // Configuración de email
    'email' => [
        'from_email' => 'noreply@tudominio.com',
        'from_name' => 'Sistema de Autenticación',
        'smtp_enabled' => true,
    ]
];