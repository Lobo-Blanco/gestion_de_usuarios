<?php
// app/lib/Flash.php

/**
 * Sistema de mensajes flash
 */
class Flash
{
    const SUCCESS = 'success';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    
    /**
     * Añadir mensaje flash
     */
    public static function add($type, $message)
    {
        $messages = Session::get('flash_messages.' . $type, []);
        $messages[] = $message;
        Session::set('flash_messages.' . $type, $messages);
    }
    
    /**
     * Añadir mensaje de éxito
     */
    public static function success($message)
    {
        self::add(self::SUCCESS, $message);
    }
    
    /**
     * Añadir mensaje de error
     */
    public static function error($message)
    {
        self::add(self::ERROR, $message);
    }
    
    /**
     * Añadir mensaje de advertencia
     */
    public static function warning($message)
    {
        self::add(self::WARNING, $message);
    }
    
    /**
     * Añadir mensaje informativo
     */
    public static function info($message)
    {
        self::add(self::INFO, $message);
    }
    
    /**
     * Obtener todos los mensajes
     */
    public static function getMessages($type = null)
    {
        if ($type) {
            $messages = Session::get('flash_messages.' . $type, []);
            Session::delete('flash_messages.' . $type);
            return $messages;
        }
        
        $allMessages = [];
        $types = [self::SUCCESS, self::ERROR, self::WARNING, self::INFO];
        
        foreach ($types as $type) {
            $messages = Session::get('flash_messages.' . $type, []);
            if (!empty($messages)) {
                $allMessages[$type] = $messages;
                Session::delete('flash_messages.' . $type);
            }
        }
        
        return $allMessages;
    }
    
    /**
     * Verificar si hay mensajes
     */
    public static function hasMessages($type = null)
    {
        if ($type) {
            return !empty(Session::get('flash_messages.' . $type, []));
        }
        
        $types = [self::SUCCESS, self::ERROR, self::WARNING, self::INFO];
        foreach ($types as $type) {
            if (!empty(Session::get('flash_messages.' . $type, []))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mostrar mensajes en formato HTML
     */
    public static function display($type = null)
    {
        $messages = self::getMessages($type);
        
        if (empty($messages)) {
            return '';
        }
        
        $html = '<div class="flash-messages">';
        
        if ($type && is_array($messages)) {
            foreach ($messages as $message) {
                $html .= self::formatMessage($type, $message);
            }
        } elseif (is_array($messages)) {
            foreach ($messages as $msgType => $typeMessages) {
                foreach ($typeMessages as $message) {
                    $html .= self::formatMessage($msgType, $message);
                }
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Formatear mensaje individual
     */
    private static function formatMessage($type, $message)
    {
        $icons = [
            self::SUCCESS => 'check-circle',
            self::ERROR => 'exclamation-circle',
            self::WARNING => 'exclamation-triangle',
            self::INFO => 'info-circle'
        ];
        
        $icon = $icons[$type] ?? 'info-circle';
        
        return sprintf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">
                <i class="fas fa-%s mr-2"></i>
                %s
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>',
            $type,
            $icon,
            htmlspecialchars($message)
        );
    }
    
    /**
     * Mensaje rápido (muestra y limpia inmediatamente)
     */
    public static function quick($type, $message)
    {
        self::add($type, $message);
        return self::display($type);
    }
}