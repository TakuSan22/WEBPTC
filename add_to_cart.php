<?php
// add_to_cart.php
// Script para añadir un producto al carrito

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no está logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];

    // Validar que el product_id sea un número entero
    if (!filter_var($product_id, FILTER_VALIDATE_INT)) {
        echo "ID de producto inválido.";
        // Considerar redirigir con un mensaje de error
        exit();
    }

    // Verificar si el producto ya está en el carrito del usuario
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // El producto ya está en el carrito, actualizar la cantidad
        $stmt->bind_result($cart_item_id, $current_quantity);
        $stmt->fetch();
        $new_quantity = $current_quantity + 1;

        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item_id);

        if ($update_stmt->execute()) {
            // Cantidad actualizada exitosamente
            header("Location: view_cart.php"); // Redirigir al carrito
            exit();
        } else {
            echo "Error al actualizar la cantidad en el carrito: " . $update_stmt->error;
            // Considerar redirigir con un mensaje de error
        }
        $update_stmt->close();

    } else {
        // El producto no está en el carrito, insertarlo
        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);

        if ($insert_stmt->execute()) {
            // Producto añadido al carrito exitosamente
            header("Location: view_cart.php"); // Redirigir al carrito
            exit();
        } else {
            echo "Error al añadir el producto al carrito: " . $insert_stmt->error;
            // Considerar redirigir con un mensaje de error
        }
        $insert_stmt->close();
    }

    $stmt->close(); // Cerrar la declaración preparada
} else {
    // Si se accede directamente a este archivo sin POST, redirigir a productos
    header("Location: products.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
