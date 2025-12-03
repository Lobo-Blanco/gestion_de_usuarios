<?php
// app/modules/auth/hooks/AuthHooks.php
require_once CORE_PATH . 'libs/event/hook.php';

class AuthHooks extends Hook
{
    /**
     * Cuando un usuario se autentica vía REMOTE_USER
     */
    public static function onRemoteAuth($usuarios_id, $remote_user)
    {
        Logger::info("Autenticación REMOTE_USER: $remote_user (ID: $usuarios_id)");
        
        // Actualizar último acceso
        $usuario = (new Usuarios())->find($usuarios_id);
        if ($usuario) {
            $usuario->ultimo_acceso_at = date('Y-m-d H:i:s');
            $usuario->update();
        }
    }

    /**
     * Cuando un usuario se registra con email
     */
    public static function onEmailRegister($usuarios_id, $email)
    {
        Logger::info("Nuevo registro con email: $email (ID: $usuarios_id)");
        
        // Enviar email de verificación si está configurado
        if (Config::get('auth.verify_email')) {
            self::sendVerificationEmail($usuarios_id, $email);
        }
    }

    /**
     * Cuando un usuario inicia sesión con credenciales
     */
    public static function onEmailLogin($usuarios_id, $email)
    {
        Logger::info("Login con credenciales: $email (ID: $usuarios_id)");
        
        // Actualizar último acceso
        $usuario = (new Usuarios())->find($usuarios_id);
        if ($usuario) {
            $usuario->ultimo_acceso_at = date('Y-m-d H:i:s');
            $usuario->update();
        }
    }

    /**
     * Cuando un usuario accede al área administrativa
     */
    public static function onAdminAccess($usuarios_id, $rol)
    {
        Logger::info("Acceso administrativo: Usuario $usuarios_id con rol $rol");
        
        // Registrar acceso administrativo
        $log = new AdminLog();
        $log->usuarios_id = $usuarios_id;
        $log->accion = 'admin_access';
        $log->save();
    }

    private static function sendVerificationEmail($usuarios_id, $email)
    {
        // Implementar envío de email de verificación
        $token = bin2hex(random_bytes(32));
        
        // Guardar token en base de datos
        $verification = new EmailVerification();
        $verification->usuarios_id = $usuarios_id;
        $verification->token = $token;
        $verification->save();
        
        // Enviar email (implementación simplificada)
        $enlace = Config::get('app.url') . "auth/verify/$token";
        mail($email, 'Verifica tu email', "Haz clic en: $enlace");
    }

    /**
     * Cuando se solicita restauración de contraseña
     */
    public static function onPasswordResetRequested($usuarios_id, $email)
    {
        Logger::info("Solicitud de restauración de contraseña para: $email (ID: $usuarios_id)");
        
        // Registrar en tabla de auditoría
        $audit = new AuditLog();
        $audit->usuarios_id = $usuarios_id;
        $audit->accion = 'password_reset_requested';
        $audit->ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $audit->save();
    }
    
    /**
     * Cuando se completa la restauración de contraseña
     */
    public static function onPasswordResetCompleted($usuarios_id, $email)
    {
        Logger::info("Contraseña restaurada exitosamente para: $email (ID: $usuarios_id)");
        
        // Registrar en tabla de auditoría
        $audit = new AuditLog();
        $audit->usuarios_id = $usuarios_id;
        $audit->accion = 'password_reset_completed';
        $audit->ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $audit->save();
        
        // Enviar notificación de cambio exitoso
        self::sendPasswordChangedNotification($email);
    }
    
    private static function sendPasswordChangedNotification($email)
    {
        $subject = 'Contraseña Actualizada - ' . Config::get('app.name');
        $message = "Tu contraseña ha sido actualizada exitosamente.\n";
        $message .= "Si no realizaste este cambio, por favor contacta inmediatamente con soporte.\n\n";
        $message .= "Saludos,\n";
        $message .= "El equipo de " . Config::get('app.name');
        
        mail($email, $subject, $message);
    }

    /**
     * Cuando se cambia la contraseña
     */
    public static function onPasswordChanged($usuarios_id, $email)
    {
        Logger::info("Contraseña cambiada para usuario: $email (ID: $usuarios_id)");
        
        // Aquí puedes añadir:
        // - Notificar al usuario por email
        // - Registrar en tabla de auditoría
        // - Cerrar sesiones activas del usuario
    }
}