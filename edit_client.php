<?php
// edit_client.php
// Página para que el administrador edite los datos de un cliente

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$user_id = $_GET['user_id'] ?? null;
$client_data = null;
$message = ''; // Variable para mensajes de estado

// --- Lógica para obtener los datos del cliente ---
if ($user_id === null || !filter_var($user_id, FILTER_VALIDATE_INT)) {
    $message = '<p class="text-red-600 mb-4">ID de cliente inválido.</p>';
} else {
    // Obtener los datos del cliente (asegurándose de que no sea un administrador)
    $stmt = $conn->prepare("SELECT id, username, email, dni, phone, points FROM users WHERE id = ? AND is_admin = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $client_data = $result->fetch_assoc();
    } else {
        $message = '<p class="text-red-600 mb-4">Cliente no encontrado o no es un cliente (podría ser un administrador).</p>';
    }
    $stmt->close();
}

// --- Lógica para guardar los datos modificados ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_client_data']) && $client_data) {
    // Asegurarse de que el ID del usuario del POST coincida con el del GET
    $posted_user_id = $_POST['user_id'] ?? null;
    if ($posted_user_id != $user_id) {
         $message = '<p class="text-red-600 mb-4">Error de seguridad: ID de usuario no coincide.</p>';
    } else {
        $new_username = $_POST['username'];
        $new_email = $_POST['email'];
        $new_dni = $_POST['dni'];
        $new_phone = $_POST['phone'];
        $new_points = $_POST['points'];

        // Validaciones básicas (puedes añadir más según necesites)
        if (empty($new_username) || empty($new_email) || empty($new_dni) || empty($new_phone) || !isset($new_points) || !filter_var($new_points, FILTER_VALIDATE_INT)) {
             $message = '<p class="text-red-600 mb-4">Por favor, completa todos los campos correctamente (los puntos deben ser un número entero).</p>';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
             $message = '<p class="text-red-600 mb-4">Formato de email inválido.</p>';
        } else {
            // Preparar la consulta SQL para actualizar los datos del cliente
            // Se excluye la contraseña y el estado de administrador de la edición aquí
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, dni = ?, phone = ?, points = ? WHERE id = ? AND is_admin = FALSE");
            $update_stmt->bind_param("sssisi", $new_username, $new_email, $new_dni, $new_phone, $new_points, $user_id); // s: string, i: integer

            if ($update_stmt->execute()) {
                $message = '<p class="text-green-600 mb-4">Datos del cliente actualizados exitosamente.</p>';
                // Opcional: Recargar los datos del cliente después de guardar para mostrar los cambios
                 $stmt_reloaded = $conn->prepare("SELECT id, username, email, dni, phone, points FROM users WHERE id = ? AND is_admin = FALSE");
                 $stmt_reloaded->bind_param("i", $user_id);
                 $stmt_reloaded->execute();
                 $result_reloaded = $stmt_reloaded->get_result();
                 if ($result_reloaded->num_rows === 1) {
                     $client_data = $result_reloaded->fetch_assoc();
                 }
                 $stmt_reloaded->close();

            } else {
                 // Error si el nombre de usuario, email o DNI ya existen (UNIQUE constraint) u otro error de DB
                 if ($conn->errno == 1062) { // Código de error para entrada duplicada
                      $message = '<p class="text-red-600 mb-4">Error al actualizar: El nombre de usuario, email o DNI ya existe.</p>';
                 } else {
                      $message = '<p class="text-red-600 mb-4">Error al actualizar los datos del cliente: ' . $update_stmt->error . '</p>';
                 }
            }
            $update_stmt->close();
        }
    }
}


$conn->close(); // Cerrar la conexión a la base de datos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
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
            max-width: 600px; /* Ancho adecuado para el formulario de edición */
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
         /* Estilos para botones */
        .bg-blue-500 {
            background-color: #3b82f6;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bg-blue-500:hover {
            background-color: #2563eb; /* hover:bg-blue-600 */
        }
         .bg-red-500 {
            background-color: #ef4444;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bg-red-500:hover {
            background-color: #dc2626; /* hover:bg-red-600 */
        }
         .bg-green-500 {
             background-color: #22c55e;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.75rem 1.5rem; /* py-3 px-6 */
             border-radius: 0.375rem; /* rounded-md */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.5); /* focus:shadow-outline (approx) */
        }
        .bg-green-500:hover {
            background-color: #16a34a; /* hover:bg-green-700 */
        }
         .bg-yellow-500 {
             background-color: #f59e0b;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.5); /* focus:shadow-outline (approx) */
         }
         .bg-yellow-500:hover {
             background-color: #d97706; /* hover:bg-yellow-700 */
         }
         .bg-purple-500 {
            background-color: #a855f7;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bg-purple-500:hover {
            background-color: #9333ea; /* hover:bg-purple-600 */
        }
         .bg-gray-500 {
             background-color: #6b7280;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.5); /* focus:shadow-outline (approx) */
         }
         .bg-gray-500:hover {
            background-color: #4b5563; /* hover:bg-gray-700 */
         }


         /* Estilos para navegación */
         header {
            background-color: #ffffff; /* bg-white */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.075); /* shadow-md */
            padding-top: 1rem; /* py-4 */
            padding-bottom: 1rem; /* py-4 */
        }
         header .container {
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem; /* px-4 */
            padding-right: 1rem; /* px-4 */
            display: flex;
            align-items: center; /* items-center */
            justify-content: space-between; /* justify-between */
        }
         header h1 {
            font-size: 1.5rem; /* text-2xl */
            font-weight: 700; /* font-bold */
            color: #2563eb; /* text-blue-600 */
            margin-right: 1rem; /* mr-4 */
        }
         header nav ul {
            display: flex;
            space-x: 1rem; /* space-x-4 */
        }
         header nav a {
            color: #4b5563; /* text-gray-600 */
            transition-property: color, background-color, border-color, text-decoration, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
         header nav a:hover {
            color: #2563eb; /* hover:text-blue-600 */
         }
         main {
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem; /* px-4 */
            padding-right: 1rem; /* px-4 */
            padding-top: 2rem; /* py-8 */
            padding-bottom: 2rem; /* py-8 */
            max-width: 960px; /* container max-width */
         }
         main section {
            background-color: #ffffff; /* bg-white */
            padding: 1.5rem; /* p-6 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-lg */
            margin-bottom: 2rem; /* mb-8 */
         }
         main h2 {
            font-size: 1.25rem; /* text-xl */
            font-weight: 600; /* font-semibold */
            color: #374151; /* text-gray-700 */
            margin-bottom: 1rem; /* mb-4 */
         }
         main h3 {
            font-size: 1.125rem; /* text-lg */
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* text-gray-800 */
            margin-bottom: 0.5rem; /* mb-2 */
         }
         main p {
            color: #4b5563; /* text-gray-600 */
            margin-bottom: 1rem; /* mb-4 */
         }
         main .text-gray-600 {
            color: #4b5563;
         }
         main .mb-4 {
            margin-bottom: 1rem;
         }
         main .font-bold {
             font-weight: 700;
         }
         main .space-y-4 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-y-reverse: 0;
            margin-top: calc(1rem * calc(1 - var(--tw-space-y-reverse)));
            margin-bottom: calc(1rem * var(--tw-space-y-reverse));
         }
         main .flex {
             display: flex;
         }
         main .justify-between {
             justify-content: space-between;
         }
         main .items-center {
             align-items: center;
         }
         main .ml-4 {
             margin-left: 1rem;
         }
         main .space-x-2 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-x-reverse: 0;
            margin-right: calc(0.5rem * var(--tw-space-x-reverse));
            margin-left: calc(0.5rem * calc(1 - var(--tw-space-x-reverse)));
         }
         main .text-sm {
             font-size: 0.875rem;
         }
         main form button {
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5); /* focus:shadow-outline (approx) */
         }
         main form button.bg-red-500:hover {
             background-color: #dc2626; /* hover:bg-red-700 */
         }
         main form button.bg-blue-500:hover {
             background-color: #2563eb; /* hover:bg-blue-700 */
         }
         main .grid {
            display: grid;
         }
         main .gap-4 {
             gap: 1rem;
         }
         main .md\:grid-cols-3 {
             grid-template-columns: repeat(3, minmax(0, 1fr));
         }
         main .md\:col-span-3 {
             grid-column: span 3 / span 3;
         }
         main .justify-center {
             justify-content: center;
         }
         main label {
             display: block;
             color: #374151; /* text-gray-700 */
             font-size: 0.875rem; /* text-sm */
             font-weight: 700; /* font-bold */
             margin-bottom: 0.5rem; /* mb-2 */
         }
         main input[type="date"],
         main input[type="text"],
         main input[type="number"],
         main input[type="email"] { /* Añadido email */
             box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); /* shadow */
             appearance: none;
             border: 1px solid #d1d5db; /* border */
             border-radius: 0.25rem; /* rounded */
             width: 100%;
             padding-top: 0.5rem; /* py-2 */
             padding-bottom: 0.5rem; /* py-2 */
             padding-left: 0.75rem; /* px-3 */
             padding-right: 0.75rem; /* px-3 */
             color: #374151; /* text-gray-700 */
             line-height: 1.25; /* leading-tight */
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5); /* focus:shadow-outline (approx) */
         }
         main a.bg-gray-500 {
             background-color: #6b7280;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.5); /* focus:shadow-outline (approx) */
         }
         main a.bg-gray-500:hover {
             background-color: #4b5563; /* hover:bg-gray-700 */
         }

         footer {
            background-color: #1f2937; /* bg-gray-800 */
            color: white;
            padding-top: 1.5rem; /* py-6 */
            padding-bottom: 1.5rem; /* py-6 */
            margin-top: 2rem; /* mt-8 */
         }
         footer .container {
             margin-left: auto;
             margin-right: auto;
             padding-left: 1rem; /* px-4 */
             padding-right: 1rem; /* px-4 */
             text-align: center;
         }
    </style>
</head>
<body class="font-sans antialiased text-gray-800">

    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-blue-600 mr-4">Panel de Administración</h1>

            <nav class="nav-center">
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                 <a href="admin.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                    Productos (Admin)
                </a>
                 <a href="view_orders.php" class="px-4 py-2 bg-purple-500 text-white font-semibold rounded-md hover:bg-purple-600 transition-colors">
                    Pedidos
                </a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Editar Cliente</h2>

            <?php echo $message; // Mostrar mensajes de estado ?>

            <?php if ($client_data): ?>
                <form action="edit_client.php?user_id=<?php echo htmlspecialchars($client_data['id']); ?>" method="post">
                     <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($client_data['id']); ?>">

                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Nombre de Usuario:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($client_data['username']); ?>" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client_data['email']); ?>" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                     <div class="mb-4">
                        <label for="dni" class="block text-gray-700 text-sm font-bold mb-2">DNI:</label>
                        <input type="text" id="dni" name="dni" value="<?php echo htmlspecialchars($client_data['dni']); ?>" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                     <div class="mb-4">
                        <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Teléfono:</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($client_data['phone']); ?>" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-6">
                        <label for="points" class="block text-gray-700 text-sm font-bold mb-2">Puntos:</label>
                        <input type="number" id="points" name="points" value="<?php echo htmlspecialchars($client_data['points']); ?>" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="submit" name="save_client_data"
                                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                            Guardar Datos Nuevos
                        </button>
                         <a href="clientes_administrar.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                            Volver a Administrar Clientes
                        </a>
                    </div>
                </form>
            <?php elseif (empty($message)): ?>
                 <p class="text-red-600 mb-4">No se pudo cargar la información del cliente.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
