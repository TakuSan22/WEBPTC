<?php
// export_orders_xls.php
// Script para exportar datos de pedidos a un archivo CSV (compatible con Excel)
// Solo accesible para administradores con username "admin".
// Incluye verificación de IP.

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
if ($stmt_admin_info === false) {
     error_log("Error interno al verificar IP/Username (Prepare failed): " . $conn->error);
     die('Error interno.'); // Detener si falla la preparación crítica
}
$stmt_admin_info->bind_param("i", $user_id_admin);
if (!$stmt_admin_info->execute()) {
    error_log("Error interno al verificar IP/Username (Execute failed): " . $stmt_admin_info->error);
    die('Error interno.'); // Detener si falla la ejecución crítica
}
$stmt_admin_info->bind_result($admin_username, $assigned_ip);
$stmt_admin_info->fetch();
$stmt_admin_info->close();

// Verifica si el usuario logueado tiene el username "admin"
$is_super_admin_user = ($admin_username === 'admin'); // Comparación con 'admin' en minúsculas


// Si el administrador tiene una IP asignada Y la IP actual NO coincide, denegar el acceso.
// Consideramos que si assigned_ip es NULL, no hay restricción de IP para ese admin.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para esta acción de exportación. Su IP actual es: " . $current_ip));
    exit();
}

// --- Restricción adicional: Solo permitir la exportación al usuario "admin" ---
if (!$is_super_admin_user) {
    $conn->close();
    header("Location: view_orders.php?message=" . urlencode("Acceso denegado. Solo el usuario 'admin' puede exportar pedidos."));
    exit();
}
// --- FIN: Obtener información del Administrador Logueado y Verificación de IP + Username ---


// Establecer encabezados para la descarga del archivo CSV (compatible con Excel)
header('Content-Type: text/csv; charset=utf-8');
// Nombre del archivo con la fecha actual
$filename = 'Pedidos_' . date('Y-m-d') . '.xls'; // Usamos .xls para compatibilidad, pero el contenido es CSV
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Crear un puntero de archivo para la salida estándar
$output = fopen('php://output', 'w');

// Escribir la fila de encabezado (nombres de las columnas)
// Asegúrate de que estos nombres coincidan con los datos que vas a exportar
fputcsv($output, array('ID Pedido', 'Fecha Pedido', 'Estado', 'Total', 'Nombre Cliente', 'ID Producto', 'Nombre Producto', 'Cantidad', 'Precio Unitario (al comprar)'));

// Consulta SQL para obtener todos los datos de pedidos y sus items
// Es la misma consulta que en view_orders.php, pero aquí la usamos para la exportación
$sql = "SELECT
            o.id AS order_id,
            o.total_amount,
            o.status,
            o.created_at AS order_date,
            u.username AS customer_name,
            oi.product_id,
            oi.quantity AS item_quantity,
            oi.price AS item_price_at_purchase,
            p.name AS product_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        ORDER BY o.created_at ASC, o.id, p.name"; // Ordenar por fecha y ID de pedido para agrupar

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Si falla la preparación, escribir un error en el archivo de salida y salir
    fputcsv($output, array('Error al preparar la consulta de exportación: ' . $conn->error));
    fclose($output);
    $conn->close();
    exit();
}

if ($stmt->execute()) {
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $current_order_id = null; // Para saber cuándo cambia de pedido

        while($row = $result->fetch_assoc()) {
            // Si es un nuevo pedido, escribir la información principal del pedido
            if ($row['order_id'] !== $current_order_id) {
                // Si no es el primer pedido, añadir una fila vacía para separar visualmente
                if ($current_order_id !== null) {
                    fputcsv($output, array());
                }
                // Escribir la fila principal del pedido
                fputcsv($output, array(
                    $row['order_id'],
                    $row['order_date'],
                    $row['status'],
                    $row['total_amount'], // Total del pedido
                    $row['customer_name'],
                    '', // Dejar en blanco las columnas de item para la fila principal del pedido
                    '',
                    '',
                    ''
                ));
                $current_order_id = $row['order_id'];
            }

            // Escribir la fila del item del pedido (con la información del pedido repetida o en blanco si se prefiere)
            // Aquí repetimos la información del pedido para que cada fila sea completa
            fputcsv($output, array(
                $row['order_id'], // ID del pedido (repetido)
                $row['order_date'], // Fecha del pedido (repetido)
                $row['status'], // Estado del pedido (repetido)
                '', // Dejar en blanco el total en las filas de item
                $row['customer_name'], // Nombre del cliente (repetido)
                $row['product_id'], // ID del item/producto
                $row['product_name'], // Nombre del item/producto
                $row['item_quantity'], // Cantidad del item
                $row['item_price_at_purchase'] // Precio unitario del item al comprar
            ));
        }
    } else {
        // No hay pedidos para exportar
        fputcsv($output, array('No hay pedidos registrados para exportar.'));
    }
} else {
    // Error en la ejecución de la consulta
    fputcsv($output, array('Error al obtener los pedidos para exportar: ' . $stmt->error));
}

$stmt->close(); // Cerrar la declaración preparada
fclose($output); // Cerrar el puntero del archivo
$conn->close(); // Cerrar la conexión a la base de datos

exit(); // Terminar el script después de generar el archivo
?>
