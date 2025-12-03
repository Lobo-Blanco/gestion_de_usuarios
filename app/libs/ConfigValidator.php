<?php
// app/lib/ConfigValidator.php

class ConfigValidator
{
    public function validateAuthConfig($data)
    {
        $errors = [];
        
        if (isset($data['authentication_modes']) && !is_array($data['authentication_modes'])) {
            $errors[] = 'authentication_modes debe ser un array';
        }
        
        if (isset($data['session']['lifetime'])) {
            $lifetime = (int)$data['session']['lifetime'];
            if ($lifetime < 60 || $lifetime > 86400) {
                $errors[] = 'session.lifetime debe estar entre 60 y 86400 segundos';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function validateAppConfig($data)
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'El nombre de la aplicación es requerido';
        }
        
        if (empty($data['url'])) {
            $errors[] = 'La URL es requerida';
        } elseif (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL inválida';
        }
        
        if (isset($data['debug']) && !is_bool($data['debug'])) {
            $errors[] = 'debug debe ser booleano';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function validateDatabaseConfig($data)
    {
        $errors = [];
        
        if (!isset($data['development'])) {
            $errors[] = 'Configuración development es requerida';
        } else {
            $dev = $data['development'];
            $required = ['driver', 'host', 'username', 'database'];
            
            foreach ($required as $field) {
                if (empty($dev[$field])) {
                    $errors[] = "development.{$field} es requerido";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}