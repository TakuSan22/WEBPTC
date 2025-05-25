<?php
// Ver_Pedido.php
// Página para ver los detalles de un pedido específico (solo para administradores)
// Modificado para mostrar quién procesó el pedido, añadir verificación de IP,
// mostrar la IP/Puerto de envío Winsock (del admin logueado), y verificar acceso por admin que aceptó.

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- INICIO: Verificación de IP del Administrador y obtención de send_ip ---
$user_id_admin_logueado = $_SESSION['user_id']; // ID del administrador logueado
$current_ip = $_SERVER['REMOTE_ADDR'];

// Consulta para obtener la IP asignada y la IP de envío del administrador logueado
$stmt_admin_info = $conn->prepare("SELECT assigned_ip, send_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
if ($stmt_admin_info === false) { die('Error interno al verificar IP o obtener send_ip (Prepare failed): ' . $conn->error); }
$stmt_admin_info->bind_param("i", $user_id_admin_logueado);
if (!$stmt_admin_info->execute()) { die('Error interno al verificar IP o obtener send_ip (Execute failed): ' . $stmt_admin_info->error); }
$stmt_admin_info->bind_result($assigned_ip, $admin_send_ip_logueado); // Obtenemos assigned_ip y send_ip del admin logueado
$stmt_admin_info->fetch();
$stmt_admin_info->close();


// Si el administrador tiene una IP asignada Y la IP actual NO coincide, denegar el acceso.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- FIN: Verificación de IP del Administrador y obtención de send_ip ---


$order = null; // Variable para almacenar los datos del pedido
$order_items = []; // Array para almacenar los items del pedido
$message = ''; // Variable para mensajes de estado
$order_id = null; // Inicializar order_id

// Obtener mensaje de la URL si existe (por ejemplo, después de procesar el pedido)
if (isset($_GET['message'])) {
     $message_text = htmlspecialchars(urldecode($_GET['message']));
    $message_color = 'text-green-600'; // Color por defecto para éxito

    // Si el mensaje contiene "Error" o "inválido", usa color rojo
    if (strpos($message_text, 'Error') !== false || strpos($message_text, 'inválido') !== false || strpos($message_text, 'problema') !== false || strpos($message_text, 'denegado') !== false || strpos($message_text, 'Advertencia') !== false) {
        $message_color = 'text-red-600';
    }
     $message = '<p class="' . $message_color . ' mb-4">' . $message_text . '</p>';
}


// Obtener el ID del pedido de la URL
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];

    // Validar que el order_id sea un número entero
    if (!filter_var($order_id, FILTER_VALIDATE_INT)) {
        $message = '<p class="text-red-500">ID de pedido inválido.</p>';
    } else {
        // Obtener los detalles del pedido principal, incluyendo accepted_by_admin_id
        $sql_order = "SELECT
                          o.id,
                          o.total_amount,
                          o.status,
                          o.created_at,
                          o.accepted_by_admin_id, -- Obtenemos el ID del admin que aceptó
                          u_customer.username AS customer_name,
                          u_admin_processed.username AS processed_by_admin_username -- Nombre del admin que procesó
                      FROM orders o
                      JOIN users u_customer ON o.user_id = u_customer.id -- Join para obtener el nombre del cliente
                      LEFT JOIN users u_admin_processed ON o.processed_by_admin_id = u_admin_processed.id -- LEFT JOIN para obtener el nombre del admin que procesó
                      WHERE o.id = ?";
        $stmt_order = $conn->prepare($sql_order);

        if ($stmt_order === false) {
            die('Error al preparar la consulta del pedido: ' . $conn->error);
        }

        $stmt_order->bind_param("i", $order_id);

        if ($stmt_order->execute()) {
            $result_order = $stmt_order->get_result();
            if ($result_order->num_rows > 0) {
                $order = $result_order->fetch_assoc();

                // --- INICIO: VERIFICACIÓN DE ACCESO POR ADMINISTRADOR QUE ACEPTÓ ---
                // Si el pedido tiene un accepted_by_admin_id registrado (estado Accepted o Processed)
                // Y el ID del administrador logueado NO coincide con ese ID, denegar el acceso.
                // Los pedidos Pending (accepted_by_admin_id IS NULL) pueden ser vistos por cualquier admin.
                if ($order['accepted_by_admin_id'] !== NULL && $order['accepted_by_admin_id'] !== $user_id_admin_logueado) {
                     $conn->close(); // Cerrar conexión antes de redirigir
                     $message_denied = urlencode("Acceso denegado: Solo el administrador que aceptó este pedido puede ver sus detalles.");
                     header("Location: view_orders.php?message=" . $message_denied);
                     exit(); // Detener ejecución
                }
                // --- FIN: VERIFICACIÓN DE ACCESO POR ADMINISTRADOR QUE ACEPTÓ ---


                // Si se encontró el pedido Y el administrador tiene permiso para verlo, obtener sus items
                $sql_items = "SELECT oi.quantity, oi.price as item_price_at_purchase, p.name as product_name, p.image_url
                              FROM order_items oi
                              JOIN products p ON oi.product_id = p.id
                              WHERE oi.order_id = ?";
                $stmt_items = $conn->prepare($sql_items);

                 if ($stmt_items === false) {
                    die('Error al preparar la consulta de items del pedido: ' . $conn->error);
                }

                $stmt_items->bind_param("i", $order_id);

                 if ($stmt_items->execute()) {
                    $result_items = $stmt_items->get_result();
                    while ($row_item = $result_items->fetch_assoc()) {
                        $order_items[] = $row_item;
                    }
                } else {
                     $message .= '<p class="text-red-500">Error al cargar los items del pedido: ' . $stmt_items->error . '</p>';
                }
                $stmt_items->close();

            } else {
                $message = '<p class="text-red-500">Pedido no encontrado.</p>';
            }
        } else {
             $message = '<p class="text-red-500">Error al cargar los detalles del pedido: ' . $stmt_order->error . '</p>';
        }
        $stmt_order->close();
    }

} else {
    $message = '<p class="text-red-500">No se especificó un ID de pedido.</p>';
}


$conn->close(); // Cerrar la conexión a la base de datos (después de obtener los datos si no hubo salida antes)

// Obtener una marca de tiempo actual para usar en el cache-busting de imágenes
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Pedido #<?php echo isset($order['id']) ? htmlspecialchars($order['id']) : ''; ?></title>
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

         /* Estilos para el contenedor de detalles del pedido */
         .order-details-container {
            background-color: #ffffff; /* bg-white */
            padding: 1.5rem; /* p-6 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-lg */
            margin-bottom: 2rem; /* mb-8 */
         }

         /* Estilos para el encabezado del pedido (dentro de los detalles) */
         .order-detail-header {
             border-bottom: 1px solid #e2e8f0; /* border-gray-200 */
             padding-bottom: 1rem; /* pb-4 */
             margin-bottom: 1rem; /* mb-4 */
             display: flex;
             justify-content: space-between;
             align-items: center;
             flex-wrap: wrap;
             gap: 1rem;
         }
         .order-detail-header > div {
             flex-shrink: 0;
         }
         .order-detail-header .actions {
             display: flex;
             flex-wrap: wrap;
             gap: 0.5rem;
             justify-content: flex-end;
             flex-grow: 1;
         }
         .order-detail-header .actions form,
         .order-detail-header .actions a {
             margin: 0;
         }


         /* Estilos para la lista de items */
         .items-list .order-item { /* Reuse order-item class from view_orders */
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem; /* mb-3 */
            padding-bottom: 0.75rem; /* pb-3 */
            border-bottom: 1px dashed #e2e8f0; /* border-gray-200 */
        }
        .items-list .order-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .items-list .order-item-image { /* Reuse order-item-image class */
             width: 60px; /* w-16 */
             height: auto;
             border-radius: 0.25rem; /* rounded */
             margin-right: 1rem; /* mr-4 */
        }
        .items-list .order-item-info { /* Reuse order-item-info class */
            flex-grow: 1;
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
        <section class="order-details-container">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Detalles del Pedido</h2>

            <?php echo $message; // Mostrar mensajes de error o estado ?>

            <?php if ($order): ?>
                <div class="order-detail-header">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Pedido #<?php echo htmlspecialchars($order['id']); ?></h3>
                        <p class="text-sm text-gray-600">Cliente: <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p class="text-sm text-gray-600">Fecha: <?php echo htmlspecialchars($order['created_at']); ?></p>
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

                        <?php if ($order['status'] == 'Accepted' && !empty($admin_send_ip_logueado)): // Mostrar info de envío Winsock solo si el estado es Accepted Y el admin logueado tiene send_ip ?>
                             <p class="text-sm text-gray-700 mt-1">
                                 Al procesar, se enviará información a la IP: <strong><?php echo htmlspecialchars($admin_send_ip_logueado); ?></strong> en el puerto: <strong>5454</strong>.
                             </p>
                        <?php elseif ($order['status'] == 'Accepted' && empty($admin_send_ip_logueado)): // Mensaje si está Accepted pero el admin logueado no tiene send_ip ?>
                              <p class="text-sm text-red-700 mt-1">
                                 Advertencia: No tienes una IP de envío (send_ip) configurada. Los datos no se enviarán por socket al procesar.
                             </p>
                        <?php endif; ?>

                        <?php if (!empty($order['processed_by_admin_username'])): // Mostrar si el pedido fue procesado ?>
                            <p class="text-sm text-gray-700 mt-1">Procesado por: <span class="font-semibold"><?php echo htmlspecialchars($order['processed_by_admin_username']); ?></span></p>
                        <?php endif; ?>
                    </div>
                    <div class="actions">
                        <?php if ($order['status'] == 'Accepted'): ?>
                            <form action="process_order.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres procesar este pedido?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-sm">
                                    Procesar Pedido
                                </button>
                            </form>
                        <?php endif; ?>
                        </div>
                </div>

                <h4 class="text-md font-semibold text-gray-700 mb-3">Items Comprados:</h4>
                 <?php if (!empty($order_items)): ?>
                    <div class="items-list space-y-3">
                         <?php foreach ($order_items as $item): ?>
                             <div class="order-item">
                                  <img src="<?php echo htmlspecialchars($item['image_url']); ?>?v=<?php echo $timestamp; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                 <div class="order-item-info">
                                     <p class="text-gray-800 font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                     <p class="text-sm text-gray-600">Cantidad: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                     <p class="text-sm text-gray-600">Precio Unitario (al comprar): $<?php echo htmlspecialchars(number_format($item['item_price_at_purchase'], 2)); ?></p>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                    </div>
                 <?php else: ?>
                     <p class="text-gray-600 text-sm">Este pedido no tiene items.</p>
                 <?php endif; ?>


                <div class="order-summary mt-6">
                    <p class="text-lg font-bold text-gray-800">Total del Pedido: $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                </div>

            <?php endif; ?>

            <div class="mt-6">
                <a href="view_orders.php" class="text-blue-600 hover:underline">&larr; Volver a la Lista de Pedidos</a>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
