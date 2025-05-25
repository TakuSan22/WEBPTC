<?php
// delete_order.php
// Script para eliminar uno o varios pedidos (solo para el administrador con username "admin")
// Incluye verificación de IP y verificación de username "admin".

session_start(); // Inicia la sesión

// Verificar si el usuario logueado es un administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // Si no es administrador, redirigir
    header("Location: login.php"); // O una página de error de permisos
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- INICIO: Obtener información del Administrador Logueado y Verificación de IP + Username ---
$admin_user_id_action = $_SESSION['user_id']; // ID del admin logueado que está haciendo la acción
$current_ip = $_SERVER['REMOTE_ADDR'];

// Consulta para obtener la IP asignada al administrador logueado Y su username
$stmt_admin_info = $conn->prepare("SELECT assigned_ip, username FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
if ($stmt_admin_info === false) {
    error_log("Error interno al verificar IP/Username (Prepare failed): " . $conn->error);
    die('Error interno.'); // Detener si falla la preparación crítica
}
$stmt_admin_info->bind_param("i", $admin_user_id_action);
if (!$stmt_admin_info->execute()) {
    error_log("Error interno al verificar IP/Username (Execute failed): " . $stmt_admin_info->error);
    die('Error interno.'); // Detener si falla la ejecución crítica
}
$stmt_admin_info->bind_result($assigned_ip_action, $admin_username); // Obtenemos el username del admin
$stmt_admin_info->fetch();
$stmt_admin_info->close();


// Si el administrador tiene una IP asignada Y la IP actual NO coincide, denegar el acceso.
if ($assigned_ip_action !== NULL && $assigned_ip_action !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para realizar esta acción de eliminación. Su IP actual es: " . $current_ip));
    exit();
}

// --- Restricción adicional: Solo permitir la eliminación al usuario con username "admin" ---
$is_super_admin_user = ($admin_username === 'admin'); // Verifica si el username es 'admin'

if (!$is_super_admin_user) {
    $conn->close();
    header("Location: view_orders.php?message=" . urlencode("Acceso denegado. Solo el usuario 'admin' puede eliminar pedidos."));
    exit();
}
// --- FIN: Obtener información del Administrador Logueado y Verificación de IP + Username ---


$message = ''; // Variable para mensajes de estado
$orders_deleted_count = 0; // Contador de pedidos eliminados

// Verificar si se recibió el/los ID(s) del pedido por POST
// Puede ser un solo 'order_id' (desde el botón individual, si se descomenta en view_orders.php)
// o un array 'order_ids' (desde la selección masiva)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $order_ids_to_delete = [];

    // Verificar si se recibió un array de IDs (eliminación masiva)
    if (isset($_POST['order_ids']) && is_array($_POST['order_ids']) && !empty($_POST['order_ids'])) {
        // Limpiar y validar cada ID en el array
        foreach ($_POST['order_ids'] as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT)) {
                $order_ids_to_delete[] = (int)$id; // Añadir ID válido al array
            }
        }
    }
    // Verificar si se recibió un solo ID (eliminación individual, si se descomenta el botón en view_orders.php)
    // Esta parte se ejecutará si se usó el botón individual y no la selección masiva
    // if (isset($_POST['order_id']) && !empty($_POST['order_id']) && !isset($_POST['order_ids'])) {
    //     if (filter_var($_POST['order_id'], FILTER_VALIDATE_INT)) {
    //         $order_ids_to_delete[] = (int)$_POST['order_id']; // Añadir el ID individual
    //     }
    // }


    // Si no hay IDs válidos para eliminar, redirigir con un mensaje
    if (empty($order_ids_to_delete)) {
        $message = urlencode("No se seleccionaron pedidos válidos para eliminar.");
        header("Location: view_orders.php?message=" . $message);
        $conn->close();
        exit();
    }

    // Construir la cláusula WHERE para la consulta SQL
    // Creamos placeholders (?) para cada ID en el array
    $placeholders = implode(',', array_fill(0, count($order_ids_to_delete), '?'));

    // Preparar la consulta SQL para eliminar los pedidos seleccionados
    // Gracias a ON DELETE CASCADE en la tabla order_items, los items asociados se eliminarán automáticamente.
    // No hay restricción de estado aquí, el admin puede eliminar pedidos en cualquier estado.
    $sql_delete = "DELETE FROM orders WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql_delete);

     if ($stmt === false) {
        $message = urlencode("Error al preparar la consulta de eliminación masiva: " . $conn->error);
         header("Location: view_orders.php?message=" . $message);
         $conn->close();
        exit();
    }

    // Vincular los parámetros. Necesitamos especificar el tipo para cada placeholder.
    // Como todos son enteros, usamos 'i' repetido por el número de IDs.
    $types = str_repeat('i', count($order_ids_to_delete));
    // Usamos call_user_func_array para vincular un número dinámico de parámetros
    $bind_params = array_merge([$types], $order_ids_to_delete);
    call_user_func_array([$stmt, 'bind_param'], $bind_params);


    if ($stmt->execute()) {
        // Obtener el número de filas afectadas
        $orders_deleted_count = $stmt->affected_rows;

        if ($orders_deleted_count > 0) {
            $message = urlencode($orders_deleted_count . " pedido(s) eliminado(s) exitosamente.");
        } else {
             $message = urlencode("No se eliminaron pedidos (podrían no existir o ya haber sido eliminados).");
        }
         // Redirigir de vuelta a la página de visualización de pedidos
         header("Location: view_orders.php?message=" . $message);
         exit();

    } else {
        // Error en la ejecución de la consulta
        $message = urlencode("Error al eliminar los pedidos seleccionados: " . $stmt->error);
        header("Location: view_orders.php?message=" . $message);
        exit();
    }

    $stmt->close(); // Cerrar la declaración preparada

} else {
    // Si se accede directamente a este archivo sin POST
    header("Location: view_orders.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos (esta línea podría no alcanzarse si hay exit() antes)
?>
