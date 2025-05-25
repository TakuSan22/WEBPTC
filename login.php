<?php
// login.php
// P�gina de inicio de sesi�n de usuarios (usa CDN y estilos inline)

session_start(); // Inicia la sesi�n

// Redirigir si el usuario ya est� logueado
if (isset($_SESSION['user_id'])) {
     if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header("Location: admin.php"); // Redirigir a admin si es admin
    } else {
        header("Location: products.php"); // Redirigir a productos si es usuario normal
    }
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexi�n a la base de datos

$message = ''; // Variable para mensajes de estado

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validaciones b�sicas
    if (empty($username) || empty($password)) {
        $message = '<p class="text-red-600 mb-4">Por favor, ingresa nombre de usuario y contrase�a.</p>';
    } else {
        // Preparar la consulta SQL para obtener el usuario
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result(); // Almacenar el resultado para verificar el n�mero de filas

        if ($stmt->num_rows == 1) {
            // Vincular las columnas a variables
            $stmt->bind_result($id, $db_username, $hashed_password, $is_admin);
            $stmt->fetch(); // Obtener los valores

            // Verificar la contrase�a hasheada
            if (password_verify($password, $hashed_password)) {
                // Contrase�a correcta, iniciar sesi�n
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['is_admin'] = $is_admin; // Guardar si es admin en la sesi�n

                // Redirigir seg�n el rol
                if ($is_admin) {
                    header("Location: admin.php");
                } else {
                    header("Location: products.php");
                }
                exit();
            } else {
                // Contrase�a incorrecta
                $message = '<p class="text-red-600 mb-4">Contrase�a incorrecta.</p>';
            }
        } else {
            // Usuario no encontrado
            $message = '<p class="text-red-600 mb-4">No se encontr� el usuario.</p>';
        }

        $stmt->close(); // Cerrar la declaraci�n preparada
    }
}

$conn->close(); // Cerrar la conexi�n a la base de datos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesi�n</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #f0f4f8, #d9e2ec);
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 400px; /* Ancho m�s peque�o para el formulario */
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 flex items-center justify-center min-h-screen">

    <div class="container mx-auto px-4 py-8 bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-blue-600 mb-6 text-center">Iniciar Sesi�n</h1>

        <?php echo $message; // Mostrar mensajes de estado ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Contrase�a:</label>
                <input type="password" id="password" name="password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                    Iniciar Sesi�n
                </button>
                <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" href="register.php">
                    �No tienes cuenta? Reg�strate
                </a>
            </div>
        </form>
    </div>

</body>
</html>
