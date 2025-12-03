<?php
// app/controllers/test_controller.php
// (Recuerda eliminar este controlador después de usarlo)

class TestController extends AppController
{
    /**
     * Método para crear usuarios de prueba
     * Acceder via: /test/create_users
     */
    public function create_users()
    {
        try {
            $usuarios = [
                [
                    'nombre' => 'Administrador Principal',
                    'email' => 'nacor.blanco@gmail.com',
                    'password' => 'password123',
                    'rol' => 'admin',
                    'activo' => 1
                ],
                [
                    'nombre' => 'Usuario Regular',
                    'email' => 'nacor.movil@gmail.com', 
                    'password' => 'password123',
                    'rol' => 'usuario',
                    'activo' => 1
                ],
                [
                    'nombre' => 'Editor',
                    'email' => 'elloboblanco@gmail.com',
                    'password' => 'password123',
                    'rol' => 'editor',
                    'activo' => 1
                ]
            ];

            $creados = [];
            
            foreach ($usuarios as $datos) {
                $usuario = new Usuario();
                $usuario->nombre = $datos['nombre'];
                $usuario->email = $datos['email'];
                $usuario->password = hash("md5", $datos['password']);
                $usuario->rol = $datos['rol'];
                $usuario->activo = $datos['activo'];
                
                if ($usuario->save()) {
                    $creados[] = $datos['email'];
                } else {
                    echo "Error creando {$datos['email']}<br>";
                }
            }

            if (!empty($creados)) {
                echo "Usuarios creados exitosamente:<br>";
                foreach ($creados as $email) {
                    echo "- $email (contraseña: password123)<br>";
                }
            }

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        
        // No renderizar vista
        view::select(null);
        return false;
    }
    
    /**
     * Listar todos los usuarios existentes
     * Acceder via: /test/list_users
     */
    public function list_users()
    {
        $this->usuarios = (new Usuario())->find();
        
        return false;
    }
}