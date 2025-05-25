<?php
// admin.php
// Panel de administración para gestionar productos (con botón Ver Clientes y Popup Admins)
// Incluye verificación de IP.
// El formulario "Agregar Nuevo Producto" y los botones "Eliminar Producto" solo son visibles para el usuario con username "admin".

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

// Consulta para obtener la IP asignada y el username del administrador logueado
$stmt_admin_info = $conn->prepare("SELECT assigned_ip, username FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
// Considerar manejo de errores si prepare falla en un entorno de producción
if ($stmt_admin_info === false) {
     error_log("Error interno al verificar IP/Username (Prepare failed): " . $conn->error);
     $assigned_ip = null; // Aseguramos que la variable esté definida
     $admin_username = null; // Aseguramos que la variable esté definida
} else {
    $stmt_admin_info->bind_param("i", $user_id);
    // Considerar manejo de errores si execute falla en un entorno de producción
    if (!$stmt_admin_info->execute()) {
        error_log("Error interno al verificar IP/Username (Execute failed): " . $stmt_admin_info->error);
        $assigned_ip = null; // Aseguramos que la variable esté definida
        $admin_username = null; // Aseguramos que la variable esté definida
    } else {
        $stmt_admin_info->bind_result($assigned_ip, $admin_username); // Obtenemos assigned_ip y username
        $stmt_admin_info->fetch();
    }
    $stmt_admin_info->close();
}

// Verifica si el usuario logueado tiene el username "admin"
$is_super_admin_user = ($admin_username === 'admin'); // Comparación con 'admin' en minúsculas


// Si el administrador tiene una IP asignada en la base de datos Y
// la IP actual NO coincide con la IP asignada, denegar el acceso.
// Consideramos que si assigned_ip es NULL, no hay restricción de IP para ese admin.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    // Denegar acceso y redirigir a login con un mensaje
    // Es importante cerrar la conexión antes de redirigir si no se va a hacer un exit() inmediato
    $conn->close(); // Cierra la conexión antes de redirigir
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- FIN: Obtener información del Administrador Logueado y Verificación de IP ---


$message = ''; // Variable para mensajes de estado
$admin_points = 0; // Variable para almacenar los puntos del administrador

// Obtener los puntos del administrador logueado (este código ya estaba)
// Puedes reutilizar $user_id del bloque de verificación de IP
$stmt_points = $conn->prepare("SELECT points FROM users WHERE id = ? AND is_admin = TRUE");
if ($stmt_points === false) {
     error_log("Error interno al obtener puntos (Prepare failed): " . $conn->error);
} else {
    $stmt_points->bind_param("i", $user_id);
    if (!$stmt_points->execute()) {
        error_log("Error interno al obtener puntos (Execute failed): " . $stmt_points->error);
    } else {
        $stmt_points->bind_result($admin_points);
        $stmt_points->fetch();
    }
    $stmt_points->close();
}


// Lógica para mostrar productos existentes (este código ya estaba)
$sql = "SELECT id, name, price, image_url FROM products ORDER BY created_at DESC";
$result = $conn->query($sql);

$products = [];
if ($result === false) { // Manejo de error para la consulta de productos
     $message .= '<p class="text-red-500">Error al cargar productos: ' . $conn->error . '</p>';
} else {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    } else {
        $message .= '<p class="text-gray-600 mb-4">No hay productos registrados en este momento.</p>';
    }
}

// --- INICIO: Obtener lista de administradores y sus IPs para el popup ---
$admins_list = [];
// MODIFICACIÓN: Seleccionar también la columna send_ip
$sql_admins = "SELECT username, assigned_ip, send_ip FROM users WHERE is_admin = TRUE ORDER BY username ASC";
$result_admins = $conn->query($sql_admins);

if ($result_admins === false) {
    // Manejar error, pero no detener la carga de la página si falla la lista de admins
    error_log("Error al cargar lista de administradores para popup: " . $conn->error);
    // Podrías añadir un mensaje en el popup diciendo que no se pudo cargar la lista
} else {
    if ($result_admins->num_rows > 0) {
        while($row_admin = $result_admins->fetch_assoc()) {
            $admins_list[] = $row_admin;
        }
    }
}
// --- FIN: Obtener lista de administradores y sus IPs para el popup ---


$conn->close(); // Cerrar la conexión a la base de datos (después de obtener todos los datos necesarios)

// Resto del HTML de admin.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
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
        /* Tus estilos CSS aquí */
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
        .product-card {
            border: 1px solid #e2e8f0; /* border-gray-200 */
            border-radius: 0.5rem; /* rounded */
            overflow: hidden; /* Asegura que la imagen no se salga */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); /* shadow-sm */
            display: flex; /* Flexbox para organizar contenido */
            flex-direction: column; /* Items apilados verticalmente */
        }

         .product-card img {
             width: 100%; /* Imagen ocupa todo el ancho del contenedor */
             height: 150px; /* Altura fija para consistencia, ajusta si es necesario */
             object-fit: cover; /* Cubre el área manteniendo la proporción */
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
             display: inline-block; /* Make links look like buttons */
             text-align: center; /* Center text in links */
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
        .bg-gray-600 { /* Estilo para el nuevo botón */
             background-color: #4b5563;
             color: white;
             font-weight: 600; /* font-semibold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded-md */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             display: inline-block;
             text-align: center;
             cursor: pointer;
         }
         .bg-gray-600:hover {
             background-color: #374151; /* hover:bg-gray-700 */
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

        /* Estilos para el Modal (Pop-up) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
            padding: 1rem; /* Add some padding */
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto; /* Center the modal */
            padding: 20px;
            border: 1px solid #888;
            width: 90%; /* Responsive width */
            max-width: 500px; /* Max width */
            border-radius: 0.5rem; /* Rounded corners */
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: relative; /* Needed for close button positioning */
            max-height: 90vh; /* Max height to prevent overflow */
            overflow-y: auto; /* Enable scrolling inside content */
        }

        .close-button {
            color: #aaa;
            float: right; /* Position to the right */
            font-size: 28px;
            font-weight: bold;
            position: absolute; /* Absolute position relative to modal-content */
            top: 10px;
            right: 15px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .admin-list {
            list-style: none;
            padding: 0;
            margin-top: 1rem;
        }

        .admin-list li {
            background-color: #e2e8f0; /* bg-gray-200 */
            padding: 0.75rem; /* p-3 */
            margin-bottom: 0.5rem; /* mb-2 */
            border-radius: 0.25rem; /* rounded */
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on small screens */
            gap: 0.5rem; /* Space between items */
        }

        .admin-list li strong {
            color: #1f2937; /* text-gray-800 */
        }
        .admin-list li span {
            font-size: 0.9rem; /* text-sm */
            color: #4b5563; /* text-gray-600 */
        }
         .admin-list li .ip-info {
             display: flex;
             flex-direction: column;
             align-items: flex-end; /* Align IPs to the right */
             font-size: 0.8rem; /* Smaller text for IPs */
             color: #374151; /* text-gray-700 */
         }
         .admin-list li .ip-info span {
             font-size: 0.8rem; /* Smaller text for IPs */
             color: #374151; /* text-gray-700 */
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
                 <button id="openAdminPopup" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors">
                    Ver Admins & IPs
                 </button>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <div class="flex items-center mb-4">
                 <h2 class="text-xl font-semibold text-gray-700">Administración de Productos</h2>
                  <?php // Mostrar IP actual del admin si es autorizada (opcional en esta página) ?>
             </div>

            <?php // Mostrar mensaje si existe (ej: después de agregar/eliminar producto)
             if (isset($_GET['message'])) {
                 $message_text = htmlspecialchars(urldecode($_GET['message']));
                 $message_color = 'text-green-600';

                 if (strpos($message_text, 'Error') !== false || strpos($message_text, 'inválido') !== false || strpos($message_text, 'problema') !== false || strpos($message_text, 'denegado') !== false) {
                     $message_color = 'text-red-600';
                 }

                 echo '<p class="' . $message_color . ' mb-4">' . $message_text . '</p>';
             }
             ?>

            <?php if ($is_super_admin_user): ?> <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Agregar Nuevo Producto</h3>
                    <form action="add_product.php" method="post" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nombre del Producto:</label>
                            <input type="text" id="name" name="name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Precio:</label>
                            <input type="number" id="price" name="price" step="0.01" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-700">Imagen:</label>
                            <input type="file" id="image" name="image" accept="image/*" required
                                   class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        <button type="submit"
                                class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors">
                            Agregar Producto
                        </button>
                    </form>
                </div>

                <hr class="my-8">

                <div class="flex space-x-4 mb-6">
                    <a href="import_products.php" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors">
                        Importar Productos
                    </a>
                    <a href="export_products.php" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors">
                        Exportar Productos
                    </a>
                </div>
            <?php endif; ?> <h3 class="text-lg font-semibold text-gray-700 mb-3">Productos Existentes</h3>

            <?php if (empty($products)): ?>
                <?php echo $message; // Muestra el mensaje si no hay productos ?>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card bg-white rounded shadow-sm overflow-hidden flex flex-col">
                             <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-40 object-cover product-image">
                        <div class="p-4 flex-grow flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-gray-600 mb-4">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                            </div>
                            <?php if ($is_super_admin_user): ?> <form action="delete_product.php" method="post" class="mt-auto" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este producto?');">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit"
                                            class="w-full bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors text-sm">
                                        Eliminar Producto
                                    </button>
                                </form>
                            <?php endif; ?> </div>
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

    <div id="adminModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Administradores y IPs Asignadas</h2>

            <?php if (!empty($admins_list)): ?>
                <ul class="admin-list">
                    <?php foreach ($admins_list as $admin): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($admin['username']); ?>:</strong>
                            <div class="ip-info">
                                <span>IP Acceso: <?php echo htmlspecialchars($admin['assigned_ip'] ?? 'No asignada'); ?></span>
                                <span>IP Envío: <?php echo htmlspecialchars($admin['send_ip'] ?? 'No asignada'); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-600">No se encontraron administradores con IP asignada.</p>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Lógica JavaScript para mostrar y ocultar el modal
        const modal = document.getElementById("adminModal");
        const openBtn = document.getElementById("openAdminPopup");
        const closeBtn = document.getElementsByClassName("close-button")[0];

        // Cuando el usuario hace clic en el botón, abre el modal
        openBtn.onclick = function() {
          modal.style.display = "flex"; // Usamos flex para centrar
        }

        // Cuando el usuario hace clic en la X, cierra el modal
        closeBtn.onclick = function() {
          modal.style.display = "none";
        }

        // Cuando el usuario hace clic fuera del modal, ciérralo
        window.onclick = function(event) {
          if (event.target == modal) {
            modal.style.display = "none";
          }
        }
    </script>

</body>
</html>
