<?php
// app/models/usuario.php

class Usuarios extends ActiveRecord
{
    /**
     * Verifica si el registro es nuevo
     * @return bool
     */
    public function is_new_record()
    {
        return empty($this->id) || $this->id == 0;
    }

    public function initialize()
    {
        $this->validates_presence_of('codigo', 'nombre');
        $this->validates_uniqueness_of('codigo');
    }

    public function before_save()
    {
        if ($this->is_new_record()) {
            $this->created_at = date('Y-m-d H:i:s');
            if (empty($this->rol)) {
                $this->rol = 'usuario';
            }
            
            // Para nuevos registros, registrar que se estableció contraseña
            $this->password_changed_at = date('Y-m-d H:i:s');
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Buscar usuario por Codigo
     */
    public static function findByCodigo($codigo)
    {
        return (new self())->find_first("codigo = '$codigo'");
    }

    /**
     * Buscar usuario por email (para restauración)
     */
    public static function findByEmail($email)
    {
        return (new self())->find_first("email = '$email'");
    }

    /**
     * Crear usuario desde REMOTE_USER si no existia
     */
    public static function createFromRemoteUser($remote_user)
    {
       $usuario = new self();
        $usuario->codigo = $remote_user; // REMOTE_USER es el código
        $usuario->nombre = $remote_user;
        $usuario->email = $remote_user . '@dominio.com'; // Email temporal
        $usuario->rol = 'usuario';
        $usuario->activo = 1;
        
        if ($usuario->save()) {
            Event::trigger('user.remote_created', [$usuario->id, $remote_user]);
            return $usuario;
        }
    }

    /**
     * Generar token de restauración
     */
    public function generateResetToken()
    {
        // Generar token único
        $token = bin2hex(random_bytes(32));
        
        // Establecer tiempo de expiración (1 hora por defecto)
        $expiry_hours = Config::get('auth.reset_token_expiry', 1);
        $expiry_time = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));
        
        // Guardar token y tiempo de expiración
        $this->reset_token = $token;
        $this->reset_token_expires = $expiry_time;
        
        return $this->update() ? $token : false;
    }

    /**
     * Validar token de restauración
     */
    public static function validateResetToken($token)
    {
        if (empty($token)) {
            return false;
        }
        
        // Buscar usuario con token válido y no expirado
        $usuario = (new self())->find_first("reset_token = '$token'");
        
        if ($usuario) {
            // Verificar que el token no haya expirado
            $current_time = date('Y-m-d H:i:s');
            if ($usuario->reset_token_expires && $usuario->reset_token_expires > $current_time) {
                return $usuario;
            } else {
                // Token expirado, limpiarlo
                $usuario->clearResetToken();
            }
        }
        
        return false;
    }

    /**
     * Limpiar token después de usarlo
     */
    public function clearResetToken()
    {
        $this->reset_token = null;
        $this->reset_token_expires = null;
        return $this->update();
    }

    /**
     * Actualizar contraseña (para uso general)
     */
    public function updatePassword($new_password)
    {
        $this->password = hash(Config::get("auth.auth_algorithm"), $new_password);
        $this->password_changed_at = date('Y-m-d H:i:s');
        
        if ($this->update()) {
            Event::trigger('auth.password_changed', [$this->id, $this->code]);
            return true;
        }
        
        return false;
    }

    /**
     * Restablecer contraseña (incluye limpiar token)
     */
    public function resetPassword($new_password)
    {
        $this->password = hash(Config::get("auth.auth_algorithm"), $new_password, );
        $this->password_changed_at = date('Y-m-d H:i:s');
        
        // Limpiar token de restauración
        $this->reset_token = null;
        $this->reset_token_expires = null;
        
        return $this->update();
    }

    /**
     * Verificar si la contraseña es correcta
     */
    public function verifyPassword($password)
    {
        return $this->pasword == hash(Config::get("auth.auth_algorithm"), $password);
    }
}