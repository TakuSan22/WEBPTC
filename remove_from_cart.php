<?php
// remove_from_cart.php
// Script para quitar un item del carrito

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no está logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $cart_item_id = $_POST['cart_item_id'];

    // Validar que el cart_item_id sea un número entero
    if (!filter_var($cart_item_id, FILTER_VALIDATE_INT)) {
        echo "ID de item de carrito inválido.";
        // Considerar redirigir con un mensaje de error
        exit();
    }

    // Preparar la consulta SQL para eliminar el item del carrito
    // Se verifica que el item pertenezca al usuario logueado por seguridad
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_item_id, $user_id); // ii: two integers

    if ($stmt->execute()) {
        // Item eliminado del carrito exitosamente
        header("Location: view_cart.php"); // Redirigir a la página del carrito
        exit();
    } else {
        echo "Error al quitar el item del carrito: " . $stmt->error;
        // Considerar redirigir con un mensaje de error
    }

    $stmt->close(); // Cerrar la declaración preparada

} else {
    // Si se accede directamente a este archivo sin POST, redirigir al carrito
    header("Location: view_cart.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
