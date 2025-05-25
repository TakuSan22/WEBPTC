<?php
// clientes_administrar.php
// Página para administrar clientes (usuarios no administradores)
// Incluye verificación de IP.
// La sección completa de administración de clientes solo es visible para el usuario con username "admin".

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- INICIO: Obtener información del Administrador Logueado y Verificación de IP ---
$user_id = $_SESSION['user_id'];
$current_ip = $_SERVER['REMOTE_ADDR'];

// Consulta para obtener el username y la IP asignada al administrador logueado
// MODIFICACIÓN: Seleccionar también el username
$stmt_admin_info = $conn->prepare("SELECT username, assigned_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
// Considerar manejo de errores si prepare falla en un entorno de producción
if ($stmt_admin_info === false) {
     error_log("Error interno al verificar IP/Username (Prepare failed): " . $conn->error);
     $assigned_ip = null; // Aseguramos que la variable esté definida
     $username = null; // Aseguramos que la variable esté definida
} else {
    $stmt_admin_info->bind_param("i", $user_id);
    if (!$stmt_admin_info->execute()) {
        error_log("Error interno al verificar IP/Username (Execute failed): " . $stmt_admin_info->error);
        $assigned_ip = null; // Aseguramos que la variable esté definida
        $username = null; // Aseguramos que la variable esté definida
    } else {
        $stmt_admin_info->bind_result($username, $assigned_ip);
        $stmt_admin_info->fetch();
    }
    $stmt_admin_info->close();
}

// Verifica si el usuario logueado tiene el username "admin"
$is_super_admin_user = ($username === 'admin'); // Comparación con 'admin' en minúsculas


// Si el administrador tiene una IP asignada Y la IP actual NO coincide, denegar el acceso.
// Consideramos que si assigned_ip es NULL, no hay restricción de IP para ese admin.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- FIN: Obtener información del Administrador Logueado y Verificación de IP ---


$message = ''; // Variable para mensajes de estado
$clients = []; // Array para almacenar los clientes

// Lógica para mostrar clientes (usuarios donde is_admin es FALSE)
// Esta consulta solo se ejecutará si el usuario es 'admin' más abajo en el HTML
// Pero la dejamos aquí por si se necesitara en otra parte del script PHP.
$sql = "SELECT id, username, email, dni, phone, points FROM users WHERE is_admin = FALSE ORDER BY username ASC";
$result = $conn->query($sql);

if ($result === false) { // Añadir manejo de error para query simple
    $message .= '<p class="text-red-500">Error al cargar clientes: ' . $conn->error . '</p>';
} else {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    } else {
        $message .= '<p class="text-gray-600 mb-4">No hay clientes registrados en este momento.</p>';
    }
}

$conn->close(); // Cerrar la conexión a la base de datos (después de obtener los datos)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Clientes</title>
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
            max-width: 960px;
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
        .client-card {
            border: 1px solid #e2e8f0; /* border-gray-200 */
            border-radius: 0.5rem; /* rounded */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); /* shadow-sm */
            padding: 1rem; /* p-4 */
            margin-bottom: 1rem; /* mb-4 */
             background-color: #ffffff; /* bg-white */
        }
        .client-info p {
            margin-bottom: 0.5rem; /* mb-2 */
             color: #4b5563; /* text-gray-600 */
             font-size: 0.9rem; /* text-sm */
        }
        .client-info p strong {
            color: #1f2937; /* text-gray-800 */
        }
        .client-actions {
            display: flex;
            flex-wrap: wrap; /* Permite que los botones se envuelvan */
            gap: 0.5rem; /* Espacio entre botones */
            margin-top: 1rem; /* mt-4 */
        }
        .client-actions form, .client-actions a {
            flex-grow: 1; /* Permite que los botones crezcan para ocupar espacio */
            min-width: 120px; /* Ancho mínimo para los botones en pantallas pequeñas */
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
             display: inline-block;
             text-align: center;
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
             display: inline-block;
             text-align: center;
        }
        .bg-red-500:hover {
            background-color: #dc2626; /* hover:bg-red-600 */
        }
         .bg-green-500 {
             background-color: #22c55e;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.5); /* focus:shadow-outline (approx) */
             display: inline-block;
             text-align: center;
        }
        .bg-green-500:hover {
            background-color: #16a34a; /* hover:bg-green-700 */
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
             display: inline-block;
             text-align: center;
        }
        .bg-purple-500:hover {
            background-color: #9333ea; /* hover:bg-purple-600 */
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
         main p {
            color: #4b5563; /* text-gray-600 */
            margin-bottom: 1rem; /* mb-4 */
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
                 <a href="clientes_administrar.php" class="px-4 py-2 bg-green-500 text-white font-semibold rounded-md hover:bg-green-600 transition-colors">
                    Clientes
                </a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php if ($is_super_admin_user): ?> <section class="bg-white p-6 rounded-lg shadow-lg mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Administración de Clientes</h2>

                 <?php // Mostrar mensaje si existe (ej: después de eliminar un cliente)
                 if (isset($_GET['message'])) {
                     $message_text = htmlspecialchars(urldecode($_GET['message']));
                     $message_color = 'text-green-600';

                     if (strpos($message_text, 'Error') !== false || strpos($message_text, 'inválido') !== false || strpos($message_text, 'problema') !== false || strpos($message_text, 'denegado') !== false) {
                         $message_color = 'text-red-600';
                     }

                     echo '<p class="' . $message_color . ' mb-4">' . $message_text . '</p>';
                 }
                 ?>

                <?php if (empty($clients)): ?>
                    <?php echo $message; // Muestra el mensaje si no hay clientes ?>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($clients as $client): ?>
                            <div class="client-card bg-white rounded shadow-sm flex flex-col">
                                <div class="client-info flex-grow">
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($client['id']); ?></p>
                                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($client['username']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                                    <p><strong>DNI:</strong> <?php echo htmlspecialchars($client['dni']); ?></p>
                                    <p><strong>Puntos:</strong> <?php echo htmlspecialchars($client['points']); ?></p>
                                </div>
                                <div class="client-actions mt-4 flex flex-wrap gap-2">
                                     <form action="make_admin.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres hacer a este usuario administrador?');">
                                        <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                        <button type="submit"
                                                class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors text-sm w-full md:w-auto">
                                            Poner como Admin
                                        </button>
                                    </form>
                                    <a href="edit_client.php?user_id=<?php echo $client['id']; ?>"
                                       class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors text-sm text-center w-full md:w-auto">
                                        Cambiar Especificaciones
                                    </a>
                                     <form action="delete_client.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres eliminar a este cliente?');">
                                        <input type="hidden" name="user_id" value="<?php echo $client['id']; ?>">
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors text-sm w-full md:w-auto">
                                            Eliminar Cliente
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?> </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
