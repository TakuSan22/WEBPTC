<?php
// manage_users.php
// Página para que el administrador gestione usuarios y los promueva

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$message = ''; // Variable para mensajes de estado

// Mostrar mensaje de importación si existe (desde promote_user.php)
if (isset($_SESSION['promote_message'])) {
    $message = $_SESSION['promote_message'];
    unset($_SESSION['promote_message']); // Limpiar el mensaje de la sesión
}


// Obtener todos los usuarios
$sql = "SELECT id, username, email, dni, phone, is_admin, admin_ip FROM users ORDER BY username ASC";
$result = $conn->query($sql);

$users = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    $message = '<p class="text-gray-600 mb-4">No hay usuarios registrados.</p>';
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
         .user-item {
            border: 1px solid #e2e8f0; /* border-gray-200 */
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
                 <a href="manage_users.php" class="px-4 py-2 bg-yellow-500 text-white font-semibold rounded-md hover:bg-yellow-600 transition-colors">
                    Usuarios
                </a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Administrar Clientes / Usuarios</h2>
            <?php echo $message; // Mostrar mensajes de estado ?>

            <?php if (!empty($users)): ?>
                <div class="space-y-4">
                    <?php foreach ($users as $user): ?>
                        <div class="user-item p-4 rounded-md shadow-sm bg-gray-50 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></h3>
                                <p class="text-gray-600 text-sm">Email: <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                                <p class="text-gray-600 text-sm">DNI: <?php echo htmlspecialchars($user['dni'] ?? 'N/A'); ?></p>
                                <p class="text-gray-600 text-sm">Teléfono: <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
                                <p class="text-gray-700 font-medium">Rol: <?php echo $user['is_admin'] ? '<span class="text-green-600">Administrador</span>' : 'Usuario Normal'; ?></p>
                                <?php if ($user['is_admin'] && $user['admin_ip']): ?>
                                    <p class="text-gray-700 text-sm">IP Asignada: <span class="font-mono"><?php echo htmlspecialchars($user['admin_ip']); ?></span></p>
                                <?php elseif ($user['is_admin'] && !$user['admin_ip']): ?>
                                     <p class="text-yellow-600 text-sm">Advertencia: Administrador sin IP asignada.</p>
                                <?php endif; ?>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <?php if (!$user['is_admin']): // Mostrar botón solo si no es administrador ?>
                                     <form action="promote_user.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres convertir a <?php echo htmlspecialchars($user['username']); ?> en administrador y asignarle una IP?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit"
                                                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors text-sm">
                                            Hacer Administrador
                                        </button>
                                    </form>
                                <?php else: // Opcional: Botón o mensaje para administradores existentes ?>
                                     <?php endif; ?>
                                 </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
