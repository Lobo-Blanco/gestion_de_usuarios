<?php
/**
 * @see Controller nuevo controller
 */
require_once CORE_PATH . 'kumbia/controller.php';

/**
 * Controlador principal que heredan los controladores
 *
 * Todos las controladores heredan de esta clase en un nivel superior
 * por lo tanto los métodos aquií definidos estan disponibles para
 * cualquier controlador.
 *
 * @category Kumbia
 * @package Controller
 */
abstract class AppController extends Controller
{
    final protected function initialize()
    {
        // Registrar eventos de autenticación si no están registrados
        $this->registerAuthEvents();
    }

    final protected function finalize()
    {
    }

    /**
     * Registrar todos los eventos de autenticación
     */
    protected function registerAuthEvents()
    {
        // Verificar si los eventos ya están registrados para evitar duplicados
        if (!Event::hasHandler('auth.remote_success')) {
            Event::bind('auth.remote_success', ['AuthHooks', 'onRemoteAuth']);
        }
        
        if (!Event::hasHandler('auth.registered')) {
            Event::bind('auth.registered', ['AuthHooks', 'onEmailRegister']);
        }
        
        if (!Event::hasHandler('auth.email_success')) {
            Event::bind('auth.email_success', ['AuthHooks', 'onEmailLogin']);
        }
        
        if (!Event::hasHandler('auth.password_changed')) {
            Event::bind('auth.password_changed', ['AuthHooks', 'onPasswordUpdated']);
        }

        if (!Event::hasHandler('admin.access')) {
            Event::bind('admin.access', ['AuthHooks', 'onAdminAccess']);
        }
        
        if (!Event::hasHandler('admin.user_updated')) {
            Event::bind('admin.user_updated', ['AuthHooks', 'onUserUpdated']);
        }

        if (!Event::hasHandler('auth.password_reset_requested')) {
            Event::bind('auth.password_reset_requested', ['AuthHook', 'onPasswordResetRequested']);
        }

        if (!Event::hasHandler('auth.password_reset_completed')) {
            Event::bind('auth.password_reset_completed', ['AuthHook', 'onPasswordResetCompleted']);
        }
    }
}

