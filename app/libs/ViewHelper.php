<?php
// app/libs/ViewHelper.php

class ViewHelper
{
    /**
     * Mostrar tiempo en formato relativo
     */
    public static function timeAgo($datetime)
    {
        if (!$datetime || $datetime == '0000-00-00 00:00:00') {
            return 'Nunca';
        }
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'hace ' . $diff . ' segundo' . ($diff != 1 ? 's' : '');
        } elseif ($diff < 3600) {
            $minutos = floor($diff / 60);
            return 'hace ' . $minutos . ' minuto' . ($minutos != 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $horas = floor($diff / 3600);
            return 'hace ' . $horas . ' hora' . ($horas != 1 ? 's' : '');
        } elseif ($diff < 2592000) {
            $dias = floor($diff / 86400);
            return 'hace ' . $dias . ' día' . ($dias != 1 ? 's' : '');
        } elseif ($diff < 31536000) {
            $meses = floor($diff / 2592000);
            return 'hace ' . $meses . ' mes' . ($meses != 1 ? 'es' : '');
        } else {
            $anos = floor($diff / 31536000);
            return 'hace ' . $anos . ' año' . ($anos != 1 ? 's' : '');
        }
    }
    
    /**
     * Formatear fecha
     */
    public static function fecha($datetime, $formato = 'd/m/Y H:i')
    {
        if (!$datetime || $datetime == '0000-00-00 00:00:00') {
            return '--';
        }
        
        return date($formato, strtotime($datetime));
    }
    
    /**
     * Mostrar estado como badge
     */
    public static function badgeEstado($activo)
    {
        if ($activo) {
            return '<span class="label label-success"><i class="fa fa-check"></i> Activo</span>';
        } else {
            return '<span class="label label-danger"><i class="fa fa-times"></i> Inactivo</span>';
        }
    }
    
    /**
     * Mostrar rol como badge
     */
    public static function badgeRol($rol)
    {
        $clases = [
            'admin' => 'danger',
            'superadmin' => 'danger',
            'editor' => 'warning',
            'moderador' => 'info',
            'usuario' => 'default'
        ];
        
        $clase = isset($clases[strtolower($rol)]) ? $clases[strtolower($rol)] : 'default';
        
        return '<span class="label label-' . $clase . '">' . h($rol) . '</span>';
    }
    
    /**
     * Truncar texto
     */
    public static function truncar($texto, $longitud = 100, $sufijo = '...')
    {
        if (strlen($texto) <= $longitud) {
            return $texto;
        }
        
        return substr($texto, 0, $longitud) . $sufijo;
    }
}