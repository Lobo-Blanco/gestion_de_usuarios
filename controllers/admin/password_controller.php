<?php
// app/controllers/password_controller.php

require APP_PATH . "controllers/admin_controller.php";
class PasswordController extends AdminController
{
    /**
     * Mostrar formulario para solicitar restauración (por EMAIL)
     */
    public function forgot()
    {
        if (!Config::get('auth.credentials.allow_restore_password_by_email', false)) {
            Flash::error('La restauración de contraseña por email no está disponible');
            Redirect::to('index/index');
        }

        $this->title = 'Restaurar Contraseña';
    }

    /**
     * Procesar solicitud de restauración (buscar por EMAIL)
     */
    public function request_reset()
    {
        if (!Config::get('auth.credentials.allow_restore_password_by_email', false)) {
            Flash::error('La restauración de contraseña por email no está disponible');
            Redirect::to('index/index');
        }

        if (Input::hasPost('email')) {
            $email = Input::post('email');
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Flash::error('El email no tiene un formato válido');
                Redirect::toAction('forgot');
            }

            // Buscar por EMAIL (no por código)
            $usuario = Usuarios::findByEmail($email);

            if ($usuario && $usuario->activo) {
                // Verificar que tenga email (por si acaso)
                if (empty($usuario->email)) {
                    Flash::error('Este usuario no tiene email registrado');
                    Redirect::toAction('forgot');
                }

                if ($this->checkResetAttempts($email)) {
                    Flash::error('Demasiados intentos. Por favor, espera unos minutos');
                    Redirect::toAction('forgot');
                }

                $token = $usuario->generateResetToken();
                
                if ($token) {
                    $sent = $this->sendResetEmail($usuario, $token);
                    
                    if ($sent) {
                        $this->logResetAttempt($email, true);
                        
                        // Mensaje genérico por seguridad
                        Flash::success('Si el email está registrado, recibirás instrucciones para restablecer tu contraseña');
                        Event::trigger('auth.password_reset_requested', [$usuario->id, $email]);
                    } else {
                        Flash::error('Error al enviar el email. Por favor, contacta con soporte');
                    }
                } else {
                    Flash::error('Error al generar token de restauración');
                }
            } else {
                Flash::success('Si el email está registrado, recibirás instrucciones para restablecer tu contraseña');
                $this->logResetAttempt($email, false);
            }
        }

        Redirect::toAction('forgot');
    }

    /**
     * Enviar email de restauración
     */
    private function sendResetEmail($usuario, $token)
    {
        $reset_url = $this->controller . "password/reset/$token";
        
        $subject = 'Restablecimiento de Contraseña - ' . Config::get('app.name');
        $message = "Hola {$usuario->nombre},\n\n";
        $message .= "Has solicitado restablecer tu contraseña.\n";
        $message .= "Tu código de usuario es: {$usuario->codigo}\n\n";
        $message .= "Para crear una nueva contraseña, haz clic en el siguiente enlace:\n";
        $message .= "$reset_url\n\n";
        $message .= "Este enlace expirará en " . Config::get('auth.reset_token_expiry', 1) . " hora(s).\n\n";
        $message .= "Si no solicitaste este cambio, puedes ignorar este email.\n";
        $message .= "Tu contraseña no cambiará a menos que accedas al enlace anterior.\n\n";
        $message .= "Saludos,\n";
        $message .= "El equipo de " . Config::get('app.name') . "\n";
        
        $headers = "From: " . Config::get('auth.email_from', 'noreply@example.com') . "\r\n";
        $headers .= "Reply-To: " . Config::get('auth.email_reply_to', 'soporte@example.com') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        return mail($usuario->email, $subject, $message, $headers);
    }

    /**
     * Mostrar formulario para nueva contraseña
     */
    public function reset($token)
    {
        if (!Config::get('auth.allow_restore_password_by_email', false)) {
            Flash::error('La restauración de contraseña por email no está disponible');
            return Redirect::to('index/index');
        }

        $usuario = Usuario::validateResetToken($token);
        
        if (!$usuario) {
            Flash::error('El enlace de restauración es inválido o ha expirado');
            return Redirect::toAction('forgot');
        }

        $this->token = $token;
        $this->title = 'Nueva Contraseña';
    }

    /**
     * Procesar nueva contraseña
     */
    public function update_password()
    {
        if (!Config::get('auth.allow_restore_password_by_email', false)) {
            Flash::error('La restauración de contraseña por email no está disponible');
            return Redirect::to('index/index');
        }

        $this->isPublic = true;

        if (Input::hasPost('token', 'password', 'password_confirm')) {
            $token = Input::post('token');
            $password = Input::post('password');
            $password_confirm = Input::post('password_confirm');

            // Validar que las contraseñas coincidan
            if ($password !== $password_confirm) {
                Flash::error('Las contraseñas no coinciden');
                return Redirect::toAction('reset/' . $token);
            }

            // Validar token
            $usuario = Usuarios::validateResetToken($token);
            
            if (!$usuario) {
                Flash::error('El enlace de restauración es inválido o ha expirado');
                return Redirect::toAction('forgot');
            }

            // Actualizar contraseña
            if ($usuario->updatePassword($password)) {
                // Limpiar token
                $usuario->clearResetToken();
                
                Flash::success('Contraseña actualizada correctamente. Ahora puedes iniciar sesión');
                
                // Registrar en log
                LogAcceso::registrarAcceso($usuario->id, 'password_reset_completed', [
                    'email' => $usuario->email
                ]);
                
                Event::trigger('auth.password_reset_completed', [$usuario->id, $usuario->email]);
                
                return Redirect::to('index/index');
            } else {
                Flash::error('Error al actualizar la contraseña');
            }
        }

        return Redirect::toAction('forgot');
    }

    /**
     * Cambiar contraseña desde perfil (usuario autenticado)
     */
    public function cambiar()
    {
        // Verificar autenticación
        if (!AuthHelper::isAuthenticated()) {
            Flash::error('Debe iniciar sesión para cambiar la contraseña');
            return Redirect::to('index/index');
        }
        
        $usuario = AuthHelper::getAuthUser();
        $usuarioObj = (new Usuario())->find($usuario['id']);
        
        if (!$usuarioObj) {
            Flash::error('Usuario no encontrado');
            return Redirect::to('index/status');
        }
        
        if (Input::hasPost('password_actual', 'password_nueva', 'password_confirm')) {
            $password_actual = Input::post('password_actual');
            $password_nueva = Input::post('password_nueva');
            $password_confirm = Input::post('password_confirm');
            
            // Verificar contraseña actual
            if (!password_verify($password_actual, $usuarioObj->password)) {
                Flash::error('La contraseña actual es incorrecta');
                return Redirect::to('password/cambiar');
            }
            
            // Validar que las nuevas contraseñas coincidan
            if ($password_nueva !== $password_confirm) {
                Flash::error('Las nuevas contraseñas no coinciden');
                return Redirect::to('password/cambiar');
            }
            
            // Actualizar contraseña
            $usuarioObj->password = password_hash($password_nueva, PASSWORD_DEFAULT);
            
            if ($usuarioObj->update()) {
                Flash::success('Contraseña cambiada correctamente');
                
                // Registrar en log
                LogAcceso::registrarAcceso($usuario['id'], 'password_changed', [
                    'email' => $usuario['email']
                ]);
                
                Event::trigger('auth.password_changed', [
                    $usuario['id'],
                    $usuario['email']
                ]);
                
                // Enviar email de notificación
                $this->enviarEmailCambioContraseña($usuarioObj);
                
                return Redirect::to('index/status');
            } else {
                Flash::error('Error al cambiar la contraseña');
            }
        }
        
        $this->title = 'Cambiar Contraseña';
    }
    
    /**
     * Métodos auxiliares
     */
    
    private function validarTokenReset($token)
    {
        $usuario = (new Usuario())->find_first(
            "reset_token = '$token' AND reset_expira > NOW() AND activo = 1 AND auth_method = 'email'"
        );
        
        return $usuario;
    }
    
    private function enviarEmailReset($usuario, $token)
    {
        $appName = Config::get('app.name');
        $baseUrl = Config::get('app.url');
        $resetUrl = $baseUrl . '/password/reset/' . $token;
        
        $asunto = "Restablecer Contraseña - $appName";
        $mensaje = "Hola {$usuario->nombre},\n\n";
        $mensaje .= "Has solicitado restablecer tu contraseña en $appName.\n\n";
        $mensaje .= "Para establecer una nueva contraseña, haz clic en el siguiente enlace:\n";
        $mensaje .= "$resetUrl\n\n";
        $mensaje .= "Este enlace expirará en 1 hora.\n\n";
        $mensaje .= "Si no solicitaste restablecer tu contraseña, ignora este email.\n\n";
        $mensaje .= "Saludos,\n";
        $mensaje .= "El equipo de $appName";
        
        $headers = "From: " . Config::get('auth.email.from_name') . 
                  " <" . Config::get('auth.email.from_email') . ">\r\n";
        
        return mail($usuario->email, $asunto, $mensaje, $headers);
    }
    
    private function enviarEmailConfirmacion($usuario)
    {
        $appName = Config::get('app.name');
        
        $asunto = "Contraseña Actualizada - $appName";
        $mensaje = "Hola {$usuario->nombre},\n\n";
        $mensaje .= "Tu contraseña en $appName ha sido actualizada exitosamente.\n\n";
        $mensaje .= "Si no realizaste este cambio, por favor contacta inmediatamente con soporte.\n\n";
        $mensaje .= "Saludos,\n";
        $mensaje .= "El equipo de $appName";
        
        $headers = "From: " . Config::get('auth.email.from_name') . 
                  " <" . Config::get('auth.email.from_email') . ">\r\n";
        
        return mail($usuario->email, $asunto, $mensaje, $headers);
    }
    
    private function enviarEmailCambioContraseña($usuario)
    {
        $appName = Config::get('app.name');
        $fecha = date('d/m/Y H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        
        $asunto = "Cambio de Contraseña - $appName";
        $mensaje = "Hola {$usuario->nombre},\n\n";
        $mensaje .= "Tu contraseña en $appName ha sido cambiada exitosamente.\n\n";
        $mensaje .= "Detalles del cambio:\n";
        $mensaje .= "- Fecha: $fecha\n";
        $mensaje .= "- IP: $ip\n\n";
        $mensaje .= "Si no realizaste este cambio, por favor contacta inmediatamente con soporte.\n\n";
        $mensaje .= "Saludos,\n";
        $mensaje .= "El equipo de $appName";
        
        $headers = "From: " . Config::get('auth.email.from_name') . 
                  " <" . Config::get('auth.email.from_email') . ">\r\n";
        
        return mail($usuario->email, $asunto, $mensaje, $headers);
    }
}
