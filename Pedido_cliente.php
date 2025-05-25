<?php
// Pedido_cliente.php
// Página para ver los detalles de un pedido específico y enviarlo (usa CDN y estilos inline)

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- VERIFICACIÓN AÑADIDA ---
// Asegurarse de que $conn es un objeto de conexión válido
if ($conn->connect_error) {
    // Si hay un error de conexión (esto debería ser manejado en db_connect.php, pero es una doble verificación)
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Si $conn no es un objeto mysqli válido (por si db_connect.php falló silenciosamente)
// Aunque connect_error arriba ya debería capturar la mayoría de los fallos de conexión
if (!($conn instanceof mysqli)) {
     die("Error: No se pudo obtener una conexión válida a la base de datos.");
}
// --- FIN VERIFICACIÓN AÑADIDA ---


$order_id = $_GET['order_id'] ?? null;
$order_details = null;
$order_items = [];
$error_message = '';
$success_message = '';

// Obtener la IP del administrador logueado
$admin_ip = null;
$admin_id = $_SESSION['user_id']; // Obtener el ID del admin logueado

// --- Lógica para obtener la IP del admin ---
// Esta sección ahora está dentro de un bloque que se ejecuta solo si $conn es válido
if (isset($admin_id)) {
    // La línea 27 original está ahora dentro de este bloque condicional
    $stmt_admin_ip = $conn->prepare("SELECT admin_ip FROM users WHERE id = ? AND is_admin = TRUE");

    // Verificar si la preparación de la consulta fue exitosa
    if ($stmt_admin_ip === false) {
        $error_message = "Error al preparar la consulta de IP del administrador: " . $conn->error;
    } else {
        $stmt_admin_ip->bind_param("i", $admin_id);
        $stmt_admin_ip->execute();
        $stmt_admin_ip->bind_result($admin_ip);
        $stmt_admin_ip->fetch();
        $stmt_admin_ip->close();

        if (!$admin_ip) {
            // Si el usuario es admin pero no tiene IP asignada
            $error_message = "Tu cuenta de administrador no tiene una dirección IP asignada para enviar pedidos.";
        }
    }
} else {
    // Esto no debería ocurrir debido a la verificación de login al inicio, pero es una precaución
    $error_message = "Usuario no logueado.";
}
// --- Fin Lógica para obtener la IP del admin ---


// Validar y obtener el ID del pedido si no hay error de IP y la conexión es válida
// Añadir verificación de $conn aquí también antes de usarlo
if (empty($error_message) && ($conn instanceof mysqli)) {
    if ($order_id === null || !filter_var($order_id, FILTER_VALIDATE_INT)) {
        $error_message = "ID de pedido inválido.";
    } else {
        // Obtener detalles del pedido y del usuario
        $stmt_order = $conn->prepare("SELECT o.id, o.user_id, u.username, o.order_date, o.total_amount FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");

        if ($stmt_order === false) {
             $error_message = "Error al preparar la consulta de detalles del pedido: " . $conn->error;
        } else {
            $stmt_order->bind_param("i", $order_id);
            $stmt_order->execute();
            $result_order = $stmt_order->get_result();

            if ($result_order->num_rows === 1) {
                $order_details = $result_order->fetch_assoc();

                // Obtener los items del pedido
                $stmt_items = $conn->prepare("SELECT oi.id, oi.product_id, p.name, oi.quantity, oi.price FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");

                if ($stmt_items === false) {
                    $error_message = "Error al preparar la consulta de ítems del pedido: " . $conn->error;
                } else {
                    $stmt_items->bind_param("i", $order_id);
                    $stmt_items->execute();
                    $result_items = $stmt_items->get_result();

                    while($row_item = $result_items->fetch_assoc()) {
                        $order_items[] = $row_item;
                    }
                    $stmt_items->close();
                }

            } else {
                $error_message = "Pedido no encontrado.";
            }
            $stmt_order->close();
        }
    }
}


// --- Lógica para enviar el pedido y sumar puntos ---
// Añadir verificación de $conn aquí también antes de usarlo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_order']) && empty($error_message) && ($conn instanceof mysqli)) {
    if ($order_details && $admin_ip) {
        $admin_username = $_SESSION['username'] ?? 'Administrador Desconocido'; // Obtener nombre del admin logueado
        $customer_username = $order_details['username']; // Obtener nombre del usuario del pedido

        // Construir la cadena de datos en el formato: "admin","usuario","producto1","producto2",...
        $data_parts = [];

        // Añadir nombre del administrador entre comillas dobles
        $data_parts[] = '"' . str_replace('"', '""', $admin_username) . '"'; // Escapar comillas dentro del nombre si las hay

        // Añadir nombre del usuario del pedido entre comillas dobles
        $data_parts[] = '"' . str_replace('"', '""', $customer_username) . '"'; // Escapar comillas dentro del nombre si las hay

        // Añadir cada nombre de producto entre comillas dobles
        foreach ($order_items as $item) {
            $data_parts[] = '"' . str_replace('"', '""', $item['name']) . '"'; // Escapar comillas dentro del nombre si las hay
        }

        // Unir todas las partes con una coma
        $data_to_send = implode(",", $data_parts);


        // Dirección IP y puerto de destino (usando la IP del administrador logueado)
        $target_ip = $admin_ip; // Usar la IP del administrador
        $target_port = 5454; // Puerto fijo

        // Usar cURL para enviar los datos (asumiendo que el destino espera una petición HTTP POST)
        $ch = curl_init('http://' . $target_ip . ':' . $target_port);

        if ($ch) {
            curl_setopt($ch, CURLOPT_POST, 1); // Configurar como POST
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_to_send); // Datos a enviar
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devolver la respuesta como string

            // Opcional: Configurar un timeout para evitar que la página se quede colgada
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 segundos de timeout

            // Deshabilitar verificación SSL si es necesario (solo para pruebas en local)
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            // Ejecutar la petición
            $response = curl_exec($ch);

            // Verificar si hubo errores en cURL
            if (curl_errno($ch)) {
                $success_message = '<p class="text-red-600 mb-4">Error al enviar el pedido: ' . curl_error($ch) . '</p>';
            } else {
                // Petición exitosa, mostrar respuesta del servidor (si la hay)
                $success_message = '<p class="text-green-600 mb-4">Pedido enviado exitosamente.</p>';
                if ($response) {
                    $success_message .= '<p class="text-gray-700">Respuesta del servidor: ' . htmlspecialchars($response) . '</p>';
                }

                // --- Sumar puntos al administrador ---
                $points_to_add = 100; // Puntos a sumar por envío exitoso
                $stmt_update_points = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");

                if ($stmt_update_points === false) {
                    $success_message .= '<p class="text-yellow-600">Advertencia: Error al preparar la consulta para sumar puntos: ' . $conn->error . '</p>';
                } else {
                    $stmt_update_points->bind_param("ii", $points_to_add, $admin_id);

                    if ($stmt_update_points->execute()) {
                        // Puntos sumados exitosamente
                        // Puedes actualizar la sesión si quieres mostrar los puntos inmediatamente
                        // $_SESSION['points'] = ($_SESSION['points'] ?? 0) + $points_to_add;
                        $success_message .= '<p class="text-green-600">¡Se han sumado ' . $points_to_add . ' puntos a tu cuenta!</p>';
                    } else {
                        // Error al sumar puntos
                        $success_message .= '<p class="text-yellow-600">Advertencia: Error al sumar puntos: ' . $stmt_update_points->error . '</p>';
                    }
                    $stmt_update_points->close();
                }
                // --- Fin Sumar puntos ---

            }

            // Cerrar la sesión cURL
            curl_close($ch);
        } else {
             $success_message = '<p class="text-red-600 mb-4">Error al inicializar cURL. Asegúrate de que la extensión cURL esté habilitada en tu PHP.</p>';
        }

    } else {
        // Si no hay detalles del pedido o no hay IP del admin (aunque ya se verificó antes)
        $error_message = $error_message ?: "No se pudo enviar el pedido. Verifica los detalles del pedido y la configuración de IP del administrador.";
    }
}
// --- Fin Lógica para enviar el pedido y sumar puntos ---


// Cerrar la conexión a la base de datos solo si es un objeto válido
if ($conn instanceof mysqli) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Pedido #<?php echo htmlspecialchars($order_id ?? 'N/A'); ?></title>
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
         .order-item-detail {
            border-bottom: 1px dashed #cbd5e0; /* border-gray-300 */
            padding-bottom: 0.75rem; /* pb-3 */
            margin-bottom: 0.75rem; /* mb-3 */
        }
        .order-item-detail:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
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
         main input[type="text"] {
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
            <?php if ($error_message): ?>
                <div class="text-red-600 mb-4"><?php echo $error_message; ?></div>
            <?php else: ?>
                 <h2 class="text-xl font-semibold text-gray-700 mb-4">Detalles del Pedido #<?php echo htmlspecialchars($order_details['id']); ?></h2>

                 <?php echo $success_message; // Mostrar mensaje de envío y puntos ?>

                 <p class="text-gray-700 mb-2"><span class="font-semibold">Administrador que visualiza:</span> <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></p>
                 <p class="text-gray-700 mb-2"><span class="font-semibold">Usuario del pedido:</span> <?php echo htmlspecialchars($order_details['username']); ?></p>
                 <p class="text-gray-700 mb-2"><span class="font-semibold">Fecha del pedido:</span> <?php echo htmlspecialchars($order_details['order_date']); ?></p>
                 <p class="text-gray-700 font-bold mb-4">Total del pedido: $<?php echo htmlspecialchars(number_format($order_details['total_amount'], 2)); ?></p>

                 <h3 class="text-lg font-semibold text-gray-700 mb-3">Productos en este Pedido:</h3>
                 <?php if (!empty($order_items)): ?>
                     <div class="space-y-3">
                         <?php foreach ($order_items as $item): ?>
                             <div class="order-item-detail border-b border-dashed border-gray-300 pb-3 mb-3 last:border-b-0 last:pb-0 last:mb-0">
                                 <p class="text-gray-800"><span class="font-medium">Producto:</span> <?php echo htmlspecialchars($item['name']); ?></p>
                                 <p class="text-gray-600"><span class="font-medium">Cantidad:</span> <?php echo htmlspecialchars($item['quantity']); ?></p>
                                 <p class="text-gray-600"><span class="font-medium">Precio Unitario:</span> $<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></p>
                                 <p class="text-gray-700"><span class="font-medium">Subtotal:</span> $<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></p>
                             </div>
                         <?php endforeach; ?>
                     </div>
                 <?php else: ?>
                     <p class="text-gray-600">No hay productos listados para este pedido.</p>
                 <?php endif; ?>

                 <div class="mt-6">
                      <h3 class="text-lg font-semibold text-gray-700 mb-3">Enviar Información del Pedido</h3>
                      <p class="text-gray-600 mb-4">Envía la información de este pedido (Nombre del Administrador y Productos) a la dirección <?php echo htmlspecialchars($admin_ip ?? 'N/A'); ?>:5454.</p>

                      <?php if ($admin_ip && ($conn instanceof mysqli)): // Mostrar botón solo si hay una IP de admin válida Y conexión ?>
                          <form action="Pedido_cliente.php?order_id=<?php echo htmlspecialchars($order_id); ?>" method="post">
                              <input type="hidden" name="send_order" value="1">
                              <button type="submit"
                                          class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline transition-colors">
                                  Enviar Pedido
                              </button>
                          </form>
                      <?php else: ?>
                           <p class="text-red-600">No se puede enviar el pedido. Tu cuenta de administrador no tiene una dirección IP de destino configurada o hay un problema de conexión a la base de datos.</p>
                      <?php endif; ?>
                 </div>

            <?php endif; ?>
             <div class="mt-6">
                  <a href="view_orders.php" class="text-blue-600 hover:underline">Volver a la lista de Pedidos</a>
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
