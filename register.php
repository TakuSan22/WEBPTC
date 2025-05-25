<?php
// register.php
// Página de registro de usuarios (con email, DNI, teléfono y Navegación Actualizada)

session_start(); // Inicia la sesión

// Redirigir si el usuario ya está logueado
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$message = ''; // Variable para mensajes de estado

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email']; // Nuevo campo
    $dni = $_POST['dni'];     // Nuevo campo
    $phone = $_POST['phone']; // Nuevo campo

    // Validaciones básicas
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($dni) || empty($phone)) {
        $message = '<p class="text-red-600 mb-4">Por favor, completa todos los campos.</p>';
    } elseif ($password !== $confirm_password) {
        $message = '<p class="text-red-600 mb-4">Las contraseñas no coinciden.</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validar formato de email
         $message = '<p class="text-red-600 mb-4">Formato de email inválido.</p>';
    } else {
        // Hashear la contraseña antes de almacenarla
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Preparar la consulta SQL para evitar inyecciones SQL
        // Incluir los nuevos campos en la consulta INSERT
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, dni, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hashed_password, $email, $dni, $phone); // sssss para 5 strings

        if ($stmt->execute()) {
            $message = '<p class="text-green-600 mb-4">Registro exitoso. Ahora puedes <a href="login.php" class="text-blue-600 hover:underline">iniciar sesión</a>.</p>';
        } else {
            // Error si el usuario, email o DNI ya existen (UNIQUE constraint) u otro error de DB
            if ($conn->errno == 1062) { // Código de error para entrada duplicada
                 // Puedes refinar este mensaje para indicar qué campo es el duplicado si es necesario
                 $message = '<p class="text-red-600 mb-4">El nombre de usuario, email o DNI ya existe.</p>';
            } else {
                 $message = '<p class="text-red-600 mb-4">Error al registrar usuario: ' . $stmt->error . '</p>';
            }
        }

        $stmt->close(); // Cerrar la declaración preparada
    }
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
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
            max-width: 400px; /* Ancho más pequeño para el formulario */
        }
         .nav-center {
            flex-grow: 1;
            text-align: center;
        }
         .nav-center ul {
            display: inline-flex;
            justify-content: center;
            width: 100%;
        }
         header .container {
            max-width: 960px; /* Ancho más grande para la navegación */
         }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 flex flex-col min-h-screen">

    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-blue-600 mr-4">Mi Sitio Web</h1>

            <nav class="nav-center">
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                 <a href="login.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                    Login
                </a>
                <a href="register.php" class="px-4 py-2 bg-green-500 text-white font-semibold rounded-md hover:bg-green-600 transition-colors">
                    Registro
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex-grow flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-sm">
            <h1 class="text-2xl font-bold text-blue-600 mb-6 text-center">Registro de Usuario</h1>

            <?php echo $message; // Mostrar mensajes de estado ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Nombre de Usuario:</label>
                    <input type="text" id="username" name="username" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Contraseña:</label>
                    <input type="password" id="password" name="password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirmar Contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                 <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label for="dni" class="block text-gray-700 text-sm font-bold mb-2">DNI:</label>
                    <input type="text" id="dni" name="dni" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-6">
                    <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Teléfono:</label>
                    <input type="text" id="phone" name="phone" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                        Registrarse
                    </button>
                    <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" href="login.php">
                        ¿Ya tienes cuenta? Inicia Sesión
                    </a>
                </div>
            </form>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
