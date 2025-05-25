<?php
// add_to_cart.php
// Script para a�adir un producto al carrito

session_start(); // Inicia la sesi�n

// Verificar si el usuario est� logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no est� logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexi�n a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];

    // Validar que el product_id sea un n�mero entero
    if (!filter_var($product_id, FILTER_VALIDATE_INT)) {
        echo "ID de producto inv�lido.";
        // Considerar redirigir con un mensaje de error
        exit();
    }

    // Verificar si el producto ya est� en el carrito del usuario
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // El producto ya est� en el carrito, actualizar la cantidad
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
        // El producto no est� en el carrito, insertarlo
        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);

        if ($insert_stmt->execute()) {
            // Producto a�adido al carrito exitosamente
            header("Location: view_cart.php"); // Redirigir al carrito
            exit();
        } else {
            echo "Error al a�adir el producto al carrito: " . $insert_stmt->error;
            // Considerar redirigir con un mensaje de error
        }
        $insert_stmt->close();
    }

    $stmt->close(); // Cerrar la declaraci�n preparada
} else {
    // Si se accede directamente a este archivo sin POST, redirigir a productos
    header("Location: products.php");
    exit();
}

$conn->close(); // Cerrar la conexi�n a la base de datos
?>
