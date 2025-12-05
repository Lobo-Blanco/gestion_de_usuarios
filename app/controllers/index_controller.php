<?php
// app/modules/auth/controllers/auth_controller.php
//Load::Lib("AuthHooks");
//Load::Lib("AuthHelper");

class IndexController extends AppController
{
    /**
     * Método principal que decide el tipo de autenticación
     */

    public function before_filter()
    {
        // Inicializar middleware de autenticación
        AuthMiddleware::init();

    }

    public function index()
    {
        $this->isValid = AuthHelper::isAuthenticated();
        $this->current_user = AuthMiddleware::getAuthUser(); //AuthHelper::getAuthUser();

        // Verificar si ya está autenticado
        if ($this->isValid) {
            return $this->status();
        }

        // Intentar autenticación automática vía REMOTE_USER
        if (AuthHelper::handleAuthentication()) {
            return $this->status();
        }

        // Mostrar formulario de login/registro
        return $this->showLoginForm();
    }

    /**
     * Mostrar formulario de login/registro
     */
    private function showLoginForm()
    {Logger::Log("login", "Accedemos a showLoginForm");
        $this->allow_registration = Config::get('auth.credentials.allow_registration');
        $this->allow_restore_password = Config::get('auth.credentials.allow_restore_password_by_email');
        $this->title = 'Iniciar Sesión';
        
        View::select('login');
    }

    /**
     * Procesar login con credenciales
     */
    public function login()
    {
        if (Input::hasPost('codigo') && Input::hasPost('password')) {
            $auth = AuthHelper::getAuthAdapter();

            if ($auth->identify(Input::post('codigo'), Input::post('password'), 'auth')) {
                $usuarios_id = Session::get('id', 'app_auth');
                Event::trigger('auth.login_success', [$usuarios_id, Input::post('codigo')]);

                Flash::success('¡Bienvenido!');
                Redirect::toAction('status');
            } else {
                Flash::error($auth->getError());
                Event::trigger('auth.login_failed', [Input::post('email')]);
            }
        }
        
        Redirect::to('index/');
    }

    /**
     * Procesar registro con email
     */
    public function register()
    {
        if (!Config::get('auth.credentials.allow_registration', true)) {
            Flash::error('El registro no está permitido');
            Redirect::toAction('index');
        }

        if (Input::hasPost('codigo', 'email', 'password', 'nombre')) {
            $usuario = new Usuarios();
            $usuario->codigo = Input::post('codigo'); 
            $usuario->email = Input::post('email');
            $usuario->password = hash(Config::get("auth.auth_algorithm"), Input::post('password'));
            $usuario->nombre = Input::post('nombre');
            $usuario->activo = Config::get('auth.credentials.auto_activate', true) ? 1 : 0;

            if ($usuario->save()) {
                Event::trigger('auth.registered', [$usuario->id, $usuario->email]);
                
                // Autenticar automáticamente si está configurado
                if (Config::get('auth.credentials.auto_activate')) {
                    $this->authenticateUser($usuario, 'email');
                    Flash::success('¡Registro exitoso! Bienvenido.');
                } else {
                    Flash::success('¡Registro exitoso! Por favor inicia sesión.');
                }
                
                Redirect::toAction('status');
            } else {
                Flash::error('Error en el registro: ' . implode(', ', $usuario->get_errors()));
            }
        }

        Redirect::toAction('index');
    }

    /**
     * Mostrar estado de autenticación
     */
    public function status()
    {
        if (!AuthHelper::isAuthenticated()) {
            Redirect::toAction('index');
        }

        $this->usuario = AuthHelper::getAuthUser();
        $this->title = 'Estado de Autenticación';
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        $usuarios_id = Session::get('id', 'app_auth');
        $auth_method = Session::get('auth_method', 'app_auth');
        
        Event::trigger('auth.logout', [$usuarios_id, $auth_method]);
        
        $auth = AuthHelper::getAuthAdapter();
        $auth->logout();
        
        Flash::info('Sesión cerrada correctamente');
        Redirect::toAction('index');
    }

    /**
     * Autenticar usuario manualmente
     */
    private function authenticateUser($usuario, $method)
    {
        Session::set('id', $usuario->id, 'app_auth');
        Session::set('codigo', $usuario->codigo, 'app_auth');
        Session::set('nombre', $usuario->nombre, 'app_auth');
        Session::set('email', $usuario->email, 'app_auth');
        Session::set('rol', $usuario->rol, 'app_auth');
        Session::set('auth_method', $method, 'app_auth');
        Session::set('app_user_session', true);
    }
}