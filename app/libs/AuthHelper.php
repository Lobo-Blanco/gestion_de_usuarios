<?php
// app/libs/AuthHelper.php

class AuthHelper
{
    /**
     * Detectar y manejar autenticación automáticamente
     */
    public static function handleAuthentication()
    {
        // Leo la configuracion
        $config = Config::get('auth');
        
        // Si ya está autenticado, retornar datos del usuario
        if (self::isAuthenticated()) {
            return self::getAuthUser();
        }
        
        // Intentar autenticación por REMOTE_USER si está habilitado
        if ($config['remote_user']['enabled']) {
            $remoteUser = self::getRemoteUser();
            if ($remoteUser) {
                $usuario = self::authenticateByRemoteUser($remoteUser);
                if ($usuario) {
                    return $usuario;
                }
            }
        }
        
        // No autenticado
        return false;
    }
    
    /**
     * Obtener usuario desde REMOTE_USER
     */
    private static function getRemoteUser()
    {
        $config = Config::get('auth');
        $field = $config['remote_user']['field_name'];
        
        if (isset($_SERVER[$field]) && !empty($_SERVER[$field])) {
            return $_SERVER[$field];
        }
        
        return false;
    }
    
    /**
     * Autenticar por REMOTE_USER
     */
    private static function authenticateByRemoteUser($remoteUser)
    {
        // Buscar usuario por código (REMOTE_USER)
        $usuario = (new Usuarios())->find_first("codigo = '$remoteUser'");
        
        // Si no existe y está configurado para crear automáticamente
        if (!$usuario && Config::get('auth.remote_user.auto_create_user')) {
            $usuario = new Usuario();
            $usuario->codigo = $remoteUser;
            $usuario->nombre = $remoteUser;
            $usuario->rol_id = Config::get('auth.remote_user.default_role', 5);
            $usuario->activo = 1;
            $usuario->auth_method = 'remote_user';
            
            if ($usuario->save()) {
                // Registrar creación de usuario
                LogAcceso::registrarAcceso($usuario->id, 'usuario_creado_remoto', [
                    'metodo' => 'remote_user',
                    'codigo' => $remoteUser
                ]);
                
                Event::trigger('auth.user_created_from_remote', [$usuario->id, $remoteUser]);
            }
        }
        
        if ($usuario && $usuario->activo) {
            self::startSession($usuario, 'remote_user');
            
            // Registrar acceso exitoso
            LogAcceso::registrarAcceso($usuario->id, 'login_remoto', [
                'metodo' => 'remote_user',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return $usuario;
        }
        
        // Registrar intento fallido
        LogAcceso::registrarIntentoFallido($remoteUser, 'remote_user');
        
        return false;
    }
    
    /**
     * Crear usuario desde REMOTE_USER
     */
    private static function createUserFromRemote($remoteUser)
    {
        $usuario = new Usuarios();
        $usuario->codigo = $remoteUser;
        $usuario->nombre = $remoteUser;
        $usuario->email = $remoteUser . '@dominio.com'; // Email por defecto
        $usuario->rol = Config::get('auth.remote_user.default_role', 'usuario');
        $usuario->activo = 1;
        $usuario->auth_method = 'remote_user';
        
        if ($usuario->save()) {
            Event::trigger('auth.user_created_from_remote', [
                $usuario->id, 
                $remoteUser
            ]);
            
            return $usuario;
        }
        
        return false;
    }
    
    /**
     * Autenticar con credenciales
     */
    public static function authenticateWithCredentials($codigo, $password)
    {
        // Configurar adaptador
        $auth = self::getAuthAdapter();
        
        if ($auth->identify($codigo, $password, 'auth')) {
            $usuario = (new Usuarios())->find_first("codigo = '$codigo'");
            
            // Registrar acceso exitoso
            LogAcceso::registrarAcceso($usuario->id, 'login_credenciales', [
                'metodo' => 'credentials',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            Event::trigger('auth.credentials_success', [
                $usuario->id, 
                $codigo,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return $usuario;
        }
        
        // Registrar intento fallido
        LogAcceso::registrarIntentoFallido($codigo, 'credentials');
        
        Event::trigger('auth.credentials_failed', [
            $codigo,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return false;
    }
    
    /**
     * Registrar nuevo usuario con email
     */
    public static function registerWithEmail($data)
    {
        if (!Config::get('auth.credentials.allow_registration')) {
            return false;
        }
        
        $usuario = new Usuarios();
        $usuario->codigo = $data['codigo'];
        $usuario->email = $data['email'];
        $usuario->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $usuario->nombre = $data['nombre'];
        $usuario->rol = 'usuario'; // Rol por defecto para nuevos registros
        $usuario->activo = Config::get('auth.credentials.auto_activate') ? 1 : 0;
        $usuario->auth_method = 'email';
        
        if ($usuario->save()) {
            Event::trigger('auth.registered_with_email', [
                $usuario->id, 
                $usuario->email,
                $usuario->nombre
            ]);
            
            // Autenticar automáticamente si está configurado
            if (Config::get('auth.credentials.auto_activate')) {
                self::startSession($usuario, 'email');
            }
            
            return $usuario;
        }
        
        return false;
    }
    
    /**
     * Iniciar sesión manualmente
     */
    private static function startSession($usuario, $authMethod)
    {
        Session::set('id', $usuario->id, 'app_auth');
        Session::set('codigo', $usuario->codigo, 'app_auth');
        Session::set('nombre', $usuario->nombre, 'app_auth');
        Session::set('email', $usuario->email, 'app_auth');
        Session::set('rol_id', $usuario->rol_id, 'app_auth');
        Session::set('auth_method', $authMethod, 'app_auth');
        Session::set('app_user_session', true);
        
        // Actualizar último acceso
        $usuario->ultimo_acceso_at = date('Y-m-d H:i:s');
        $usuario->update();
    }
    
    /**
     * Obtener adapter de Auth2
     */
    public static function getAuthAdapter()
    {
        $auth = Auth2::factory('model');
        $auth->setModel('Usuarios');
        $auth->setLogin('codigo');
        $auth->setPass('password');
        $auth->setAlgos(Config::get("auth.auth_algorithm"));
        $auth->setFields(['id', 'codigo', 'nombre', 'email', 'rol_id', 'created_at']);
        $auth->setSessionNamespace('app_auth');
        $auth->setKey('app_user_session');
        
        return $auth;
    }

    /**
     * Verificar si está autenticado
     */
    public static function isAuthenticated()
    {
        $auth = self::getAuthAdapter();
        return $auth->isValid();
    }
    
    /**
     * Obtener datos del usuario autenticado
     */
    public static function getAuthUser()
    {
        if (self::isAuthenticated()) {
            return [
                'id' => Session::get('id', 'app_auth'),
                'codigo' => Session::get('codigo', 'app_auth'),
                'nombre' => Session::get('nombre', 'app_auth'),
                'email' => Session::get('email', 'app_auth'),
                'rol_id' => Session::get('rol_id', 'app_auth'),
                'auth_method' => Session::get('auth_method', 'app_auth')
            ];
        }
        
        return false;
    }
    
    /**
     * Verificar si tiene acceso administrativo
     */
    public static function hasAdminAccess()
    {
        $user = self::getAuthUser();
        if (!$user) return false;
        
        $adminRoles = Config::get('auth.admin_roles', ['admin', 'superadmin']);
        return in_array($user['rol'], $adminRoles);
    }
    
    /**
     * Cerrar sesión
     */
    public static function logout()
    {
        $user = self::getAuthUser();
        
        if ($user) {
            // Registrar logout
            LogAcceso::registrarAcceso($user['id'], 'logout', [
                'metodo' => $user['auth_method'] ?? 'unknown'
            ]);
            
            Event::trigger('auth.logout', [
                $user['id'] ?? null,
                $user['auth_method'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        $auth = self::getAuthAdapter();
        $auth->logout();
        
        Session::delete('app_auth');
    }
}