<?php
// view_orders.php
// Página para que los administradores visualicen y gestionen pedidos
// Incluye verificación de IP.
// Añadido botón de Exportar Pedidos visible solo para el usuario "admin".
// El botón "Borrar" pedido individual solo es visible para el usuario "admin".
// Añadidos checkboxes para seleccionar pedidos y botón "Eliminar Seleccionados" visible solo para el usuario "admin".

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- INICIO: Obtener información del Administrador Logueado y Verificación de IP ---
$user_id_admin = $_SESSION['user_id'];
$current_ip = $_SERVER['REMOTE_ADDR'];

// Consulta para obtener el username y la IP asignada al administrador logueado
$stmt_admin_info = $conn->prepare("SELECT username, assigned_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
// Considerar manejo de errores si prepare falla en un entorno de producción
if ($stmt_admin_info === false) {
     error_log("Error interno al verificar IP/Username (Prepare failed): " . $conn->error);
     $assigned_ip = null; // Aseguramos que la variable esté definida
     $admin_username = null; // Aseguramos que la variable esté definida
} else {
    $stmt_admin_info->bind_param("i", $user_id_admin);
    if (!$stmt_admin_info->execute()) {
        error_log("Error interno al verificar IP/Username (Execute failed): " . $stmt_admin_info->error);
        $assigned_ip = null; // Aseguramos que la variable esté definida
        $admin_username = null; // Aseguramos que la variable esté definida
    } else {
        $stmt_admin_info->bind_result($admin_username, $assigned_ip);
        $stmt_admin_info->fetch();
    }
    $stmt_admin_info->close();
}

// Verifica si el usuario logueado tiene el username "admin"
$is_super_admin_user = ($admin_username === 'admin'); // Comparación con 'admin' en minúsculas


// Si el administrador tiene una IP asignada Y la IP actual NO coincide, denegar el acceso.
// Consideramos que si assigned_ip es NULL, no hay restricción de IP para ese admin.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- FIN: Obtener información del Administrador Logueado y Verificación de IP ---


$orders = []; // Array para almacenar los pedidos agrupados con sus items
$message = ''; // Variable para mensajes de estado

// Obtener mensaje de la URL si existe (por ejemplo, después de eliminar un pedido o procesar)
if (isset($_GET['message'])) {
    // Decide el color del mensaje basado en el contenido (esto es básico, podrías mejorarlo)
    $message_text = htmlspecialchars(urldecode($_GET['message']));
    $message_color = 'text-green-600'; // Color por defecto para éxito

    // Si el mensaje contiene "Error" o "inválido", usa color rojo
    if (strpos($message_text, 'Error') !== false || strpos($message_text, 'inválido') !== false || strpos($message_text, 'problema') !== false || strpos($message_text, 'denegado') !== false || strpos($message_text, 'Advertencia') !== false) {
        $message_color = 'text-red-600';
    }

    $message = '<p class="' . $message_color . ' mb-4">' . $message_text . '</p>';
}


// Consulta SQL para obtener todos los pedidos, la información del usuario que realizó el pedido,
// y los detalles de los items de cada pedido.
// Unimos orders con users y luego con order_items y products.
// Ordenamos por fecha de creación del pedido (más reciente primero) y luego por ID de pedido.
$sql = "SELECT
            o.id AS order_id,
            o.total_amount,
            o.status,
            o.created_at AS order_date,
            u.username AS customer_name,
            oi.product_id,
            oi.quantity AS item_quantity,
            oi.price AS item_price_at_purchase,
            p.name AS product_name,
            p.image_url
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        ORDER BY o.created_at DESC, o.id, p.name"; // Ordenar para facilitar el agrupamiento en PHP

$stmt = $conn->prepare($sql);

// --- INICIO DE LA CORRECCIÓN PARA EL ERROR prepare() fallido ---
// Verifica si la preparación de la consulta falló
if ($stmt === false) {
    // Si falló, muestra un error detallado y detiene la ejecución
    die('Error al preparar la consulta de pedidos: ' . $conn->error);
    // En un entorno de producción, podrías querer registrar el error
    // y mostrar un mensaje más amigable al usuario.
}
// --- FIN DE LA CORRECCIÓN ---


// Si la preparación fue exitosa, ejecutar la consulta
if ($stmt->execute()) {
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Procesar los resultados para agrupar los items por pedido
        $grouped_orders = []; // Usamos un array temporal para agrupar
        while($row = $result->fetch_assoc()) {
            $order_id = $row['order_id'];

            // Si es el primer item de este pedido, inicializar la entrada del pedido
            if (!isset($grouped_orders[$order_id])) {
                $grouped_orders[$order_id] = [
                    'id' => $row['order_id'],
                    'total_amount' => $row['total_amount'],
                    'status' => $row['status'],
                    'order_date' => $row['order_date'],
                    'customer_name' => $row['customer_name'],
                    'items' => [] // Inicializar array para los items de este pedido
                ];
            }

            // Añadir el item actual al array de items de este pedido
            $grouped_orders[$order_id]['items'][] = [
                'product_id' => $row['product_id'],
                'item_quantity' => $row['item_quantity'],
                'item_price_at_purchase' => $row['item_price_at_purchase'],
                'product_name' => $row['product_name'],
                'image_url' => $row['image_url']
            ];
        }
         // Convertir el array asociativo (con keys de order_id) a un array indexado para usar en el foreach
        $orders = array_values($grouped_orders);

    } else {
        $message .= '<p class="text-gray-600 mb-4">No hay pedidos registrados en este momento.</p>';
    }
} else {
    // Este bloque ahora solo se ejecutará si execute() falla, no prepare()
    $message .= '<p class="text-red-500">Error al cargar los pedidos: ' . $stmt->error . '</p>';
    // Considerar un manejo de error más robusto en producción
}

$stmt->close(); // Cerrar la declaración preparada
$conn->close(); // Cerrar la conexión a la base de datos (después de obtener los datos)

// Obtener una marca de tiempo actual para usar en el cache-busting de imágenes
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Admin</title>
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
        .order-card {
            border: 1px solid #e2e8f0; /* border-gray-200 */
            border-radius: 0.5rem; /* rounded-lg */
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-md */
            margin-bottom: 1.5rem; /* mb-6 */
            background-color: #ffffff; /* bg-white */
             display: flex; /* Usar flexbox para checkbox y contenido */
             align-items: flex-start; /* Alinear items al inicio */
             padding: 1rem; /* Añadir padding */
        }
         .order-card .order-content {
             flex-grow: 1; /* Permitir que el contenido del pedido ocupe el espacio restante */
         }
         .order-card input[type="checkbox"] {
             margin-right: 1rem; /* Espacio entre checkbox y contenido */
             margin-top: 0.5rem; /* Alinear checkbox con el inicio del contenido */
             transform: scale(1.2); /* Aumentar un poco el tamaño del checkbox */
         }

        .order-header {
            background-color: #f7fafc; /* bg-gray-50 */
            padding: 1rem; /* p-4 */
            border-bottom: 1px solid #e2e8f0; /* border-gray-200 */
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrap on small screens */
            gap: 1rem; /* Space between items when wrapping */
        }
         .order-header > div {
             flex-shrink: 0; /* Prevent details from shrinking too much */
         }
         .order-header .actions {
             display: flex;
             flex-wrap: wrap; /* Allow buttons to wrap */
             gap: 0.5rem; /* Space between buttons */
             justify-content: flex-end; /* Align buttons to the right */
             flex-grow: 1; /* Allow actions container to grow */
         }
          .order-header .actions form,
          .order-header .actions a {
              margin: 0; /* Remove default form/link margin */
          }

        .order-details {
            padding: 1rem; /* p-4 */
        }
        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem; /* mb-3 */
            padding-bottom: 0.75rem; /* pb-3 */
            border-bottom: 1px dashed #e2e8f0; /* border-gray-200 */
        }
        .order-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .order-item-image {
             width: 60px; /* w-16 */
             height: auto;
             border-radius: 0.25rem; /* rounded */
             margin-right: 1rem; /* mr-4 */
        }
        .order-item-info {
            flex-grow: 1;
        }
        .order-summary {
            padding: 1rem; /* p-4 */
            background-color: #f7fafc; /* bg-gray-50 */
            border-top: 1px solid #e2e8f0; /* border-gray-200 */
            text-align: right;
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
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Pedidos de Clientes</h2>
                <?php if ($is_super_admin_user): ?>
                    <a href="export_orders_xls.php" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors text-sm">
                        Exportar Pedidos (.xls)
                    </a>
                <?php endif; ?>
            </div>

            <?php echo $message; // Mostrar mensaje de no hay pedidos, error o estado de eliminación ?>

            <?php if (!empty($orders)): ?>
                <?php if ($is_super_admin_user): ?>
                    <form action="delete_order.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres eliminar los pedidos seleccionados? Esta acción es irreversible.');">
                        <div class="flex items-center mb-4 space-x-4">
                            <input type="checkbox" id="selectAllOrders" class="form-checkbox h-5 w-5 text-blue-600">
                            <label for="selectAllOrders" class="text-gray-700 font-medium">Seleccionar Todos</label>
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors text-sm">
                                Eliminar Seleccionados
                            </button>
                        </div>
                <?php endif; ?>

                <div class="space-y-6">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <?php if ($is_super_admin_user): ?>
                                <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="order-checkbox form-checkbox h-5 w-5 text-blue-600">
                            <?php endif; ?>
                            <div class="order-content">
                                <div class="order-header">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">Pedido #<?php echo htmlspecialchars($order['id']); ?></h3>
                                        <p class="text-sm text-gray-600">Cliente: <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p class="text-sm text-gray-600">Fecha: <?php echo htmlspecialchars($order['order_date']); ?></p>
                                        <?php
                                            $status_color = 'text-gray-600'; // Default color
                                            if ($order['status'] == 'Pending') {
                                                $status_color = 'text-yellow-600';
                                            } elseif ($order['status'] == 'Accepted') {
                                                $status_color = 'text-blue-600';
                                            } elseif ($order['status'] == 'Processed') {
                                                $status_color = 'text-green-600';
                                            }
                                        ?>
                                        <p class="text-sm font-bold <?php echo $status_color; ?>">Estado: <?php echo htmlspecialchars($order['status']); ?></p>
                                    </div>
                                    <div class="actions">
                                        <?php if ($order['status'] == 'Pending'): ?>
                                            <form action="accept_order.php" method="post">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-sm">
                                                    Aceptar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($order['status'] == 'Accepted' || $order['status'] == 'Processed'): // Muestra "Detalle" si está Aceptado o Procesado ?>
                                             <a href="Ver_Pedido.php?order_id=<?php echo $order['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded text-sm">
                                                Detalle
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($is_super_admin_user): ?>
                                            <?php endif; ?>
                                    </div>
                                </div>
                                <div class="order-details">
                                    <h4 class="text-md font-semibold text-gray-700 mb-3">Items:</h4>
                                    <?php if (!empty($order['items'])): ?>
                                         <div class="space-y-3">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="order-item">
                                                     <img src="<?php echo htmlspecialchars($item['image_url']); ?>?v=<?php echo $timestamp; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                                    <div class="order-item-info">
                                                        <p class="text-gray-800 font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                                        <p class="text-sm text-gray-600">Cantidad: <?php echo htmlspecialchars($item['item_quantity']); ?></p>
                                                        <p class="text-sm text-gray-600">Precio Unitario (al comprar): $<?php echo htmlspecialchars(number_format($item['item_price_at_purchase'], 2)); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                         </div>
                                    <?php else: ?>
                                        <p class="text-gray-600 text-sm">Este pedido no tiene items.</p>
                                    <?php endif; ?>
                                </div>
                                 <div class="order-summary">
                                    <p class="text-lg font-bold text-gray-800">Total del Pedido: $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($is_super_admin_user): ?>
                    </form> <?php endif; ?>

            <?php endif; ?>

        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

    <?php if ($is_super_admin_user): ?>
        <script>
            // Script para la funcionalidad de "Seleccionar Todos"
            document.addEventListener('DOMContentLoaded', function() {
                const selectAllCheckbox = document.getElementById('selectAllOrders');
                const orderCheckboxes = document.querySelectorAll('.order-checkbox');

                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        orderCheckboxes.forEach(checkbox => {
                            checkbox.checked = selectAllCheckbox.checked;
                        });
                    });
                }

                // Opcional: Si desmarcas un checkbox individual, desmarca el "Seleccionar Todos"
                orderCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        if (!this.checked) {
                            selectAllCheckbox.checked = false;
                        } else {
                            // Opcional: Si todos los checkboxes individuales están marcados, marca "Seleccionar Todos"
                            const allChecked = Array.from(orderCheckboxes).every(cb => cb.checked);
                            if (allChecked) {
                                selectAllCheckbox.checked = true;
                            }
                        }
                    });
                });
            });
        </script>
    <?php endif; ?>

</body>
</html>
