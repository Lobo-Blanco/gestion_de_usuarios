<?php
/**
 * KumbiaPHP web & app Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 *
 * @category   KumbiaPHP
 * @package    Helpers
 *
 * @copyright  Copyright (c) 2005 - 2023 KumbiaPHP Team (http://www.kumbiaphp.com)
 * @license    https://github.com/KumbiaPHP/KumbiaPHP/blob/master/LICENSE   New BSD License
 */

/**
 * Clase para enviar mensajes a la vista
 *
 * Envio de mensajes de advertencia, éxito, información
 * y errores a la vista.
 * Tambien envia mensajes en la consola, si se usa desde consola.
 *
 * @category   Kumbia
 * @package    Flash
 */
class Flash
{
    /**
     * Array de mensajes flash almacenados
     *
     * @var array
     */
    public static $messages = [];

    /**
     * Visualiza un mensaje flash
     *
     * @param string $type  Para tipo de mensaje y para CSS class='$name'.
     * @param string $text  Mensaje a mostrar
     */
    public static function show(string $type, string $content): void
    {
        $message = ['type' => $type, 'content' => $content];

        if (Session::has('flash')) {
            self::$messages = SesSion::get('flash');
        }

        self::$messages[] = $message;
        Session::set('flash', self::$messages);
    }

    /**
     * Visualiza un mensaje de error
     *
     * @param string $text
     */
    public static function error(string $text): void
    {
        self::show('error', $text);
    }

    /**
     * Visualiza un mensaje de advertencia en pantalla
     *
     * @param string $text
     */
    public static function warning(string $text): void
    {
        self::show('warning', $text);
    }

    /**
     * Visualiza informacion en pantalla
     *
     * @param string $text
     */
    public static function info(string $text): void
    {
        self::show('info', $text);
    }

    /**
     * Visualiza informacion de suceso correcto en pantalla
     *
     * @param string $text
     */
    public static function success(string $text): void
    {
        self::show('success', $text);
    }

    /**
     * Obtiene el contenido del primer mensaje flash del tipo especificado
     *
     * @param string $type
     * @return string
     */
    public static function get($type = '')
    {
        self::$messages = Session::get('flash') ?: [];
        
        foreach (self::$messages as $key => $message) {
            if ($type === '' || $message['type'] === $type) {
                unset(self::$messages[$key]);
                Session::set('flash', self::$messages);
                return $message['content'];
            }
        }
        
        return '';
    }

    /**
     * Obtiene el tipo del primer mensaje flash (y lo elimina del array)
     *
     * @return string
     */
    public static function getType()
    {
        self::$messages = Session::get('flash') ?: [];
        
        if (!empty(self::$messages)) {
            $message = array_shift(self::$messages);
            Session::set('flash', self::$messages);
            return $message['type'];
            //return self::$messages[0]['type'];
        }
        
        return '';
    }

    /**
     * Verifica si existe un mensaje flash del tipo especificado
     * o si hay mensajes si no se especifica tipo
     *
     * @param string $type
     * @return boolean
     */
    public static function has($type = "")
    {
        self::$messages = Session::get('flash') ?: [];
        
        if ($type === "") {
            return !empty(self::$messages);
        }
        
        foreach (self::$messages as $message) {
            if ($message['type'] === $type) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Limpia los mensajes flash
     */
    public static function clean()
    {
        Session::delete('flash');
        self::$messages = [];
    }
}