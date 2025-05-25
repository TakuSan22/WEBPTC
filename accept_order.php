<?php
// accept_order.php
// Script para marcar un pedido como "Accepted" (solo para administradores)
// Recibe el ID del pedido por POST.
// Modificado para guardar el ID del administrador que ACEPTÓ el pedido,
// añadir verificación de IP, y permitir múltiples pedidos en estado 'Accepted'
// por diferentes administradores.

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // Si no es administrador, redirigir con un mensaje de error
    $message = urlencode("Acceso denegado. Solo administradores pueden aceptar pedidos.");
    header("Location: view_orders.php?message=" . $message);
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- INICIO: Verificación de IP del Administrador ---
$user_id_admin = $_SESSION['user_id'];
$current_ip = $_SERVER['REMOTE_ADDR'];

$stmt_ip = $conn->prepare("SELECT assigned_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
if ($stmt_ip === false) { die('Error interno al verificar IP (Prepare failed): ' . $conn->error); }
$stmt_ip->bind_param("i", $user_id_admin);
if (!$stmt_ip->execute()) { die('Error interno al verificar IP (Execute failed): ' . $stmt_ip->error); }
$stmt_ip->bind_result($assigned_ip);
$stmt_ip->fetch();
$stmt_ip->close();

if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- FIN: Verificación de IP del Administrador ---


$message = ''; // Variable para el mensaje de redirección

// Asegurarse de que la petición sea POST y que se reciba el order_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    // Validar que el order_id sea un número entero
    if (!filter_var($order_id, FILTER_VALIDATE_INT)) {
        $message = urlencode("ID de pedido inválido.");
        header("Location: view_orders.php?message=" . $message);
        $conn->close();
        exit();
    }

    // --- Lógica para permitir múltiples pedidos Aceptados ---
    // Eliminamos la verificación global de si YA existe *algún* pedido en estado 'Accepted'.
    // Ahora, solo verificamos que el pedido actual esté 'Pending' antes de aceptarlo.
    // Esto permite que varios pedidos estén en estado 'Accepted' al mismo tiempo,
    // cada uno aceptado por un administrador diferente.
    // --- FIN: Lógica para permitir múltiples pedidos Aceptados ---


    // Preparar la consulta SQL para actualizar el estado del pedido a 'Accepted'
    // Y GUARDAR el ID del administrador que lo aceptó.
    // Solo actualizamos si el estado actual es 'Pending'
    $stmt = $conn->prepare("UPDATE orders SET status = 'Accepted', accepted_by_admin_id = ? WHERE id = ? AND status = 'Pending'");
     if ($stmt === false) {
        $message = urlencode("Error al preparar la consulta de aceptación: " . $conn->error);
        header("Location: view_orders.php?message=" . $message);
        $conn->close();
        exit();
    }
    // Vinculamos el ID del administrador que acepta (i) y el ID del pedido (i)
    $stmt->bind_param("ii", $user_id_admin, $order_id);

    if ($stmt->execute()) {
        // Verificar si se afectó alguna fila
        if ($stmt->affected_rows > 0) {
            // Éxito: el pedido fue marcado como aceptado y el admin ID guardado.
            $message = urlencode("Pedido #" . $order_id . " marcado como aceptado.");
        } else {
            // El pedido con ese ID no fue encontrado o ya no estaba en estado 'Pending'
            $message = urlencode("No se pudo aceptar el pedido #" . $order_id . " (podría no existir o ya no estar pendiente).");
        }
    } else {
        // Error en la ejecución de la consulta
        $message = urlencode("Error al actualizar el estado del pedido: " . $stmt->error);
    }

    $stmt->close(); // Cerrar la declaración preparada

    // Redirigir de vuelta a la página de visualización de pedidos con el mensaje
    header("Location: view_orders.php?message=" . $message);
    exit(); // Es crucial llamar a exit() después de header("Location:")

} else {
    // Si se accede directamente a este archivo sin POST, redirigir a la página de visualización de pedidos
    header("Location: view_orders.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
