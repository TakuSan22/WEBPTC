<?php
// checkout.php
// Script para procesar el pago y guardar el pedido

session_start(); // Inicia la sesi�n

// Verificar si el usuario est� logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no est� logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexi�n a la base de datos

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $total_amount = $_POST['total_amount'];
    $cart_items = $_POST['cart_items']; // Array con los items del carrito

    // Iniciar una transacci�n para asegurar la integridad de los datos
    $conn->begin_transaction();

    try {
        // 1. Insertar el pedido en la tabla 'orders'
        $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount) VALUES (?, ?)");
        $stmt_order->bind_param("id", $user_id, $total_amount); // i: integer, d: double

        if (!$stmt_order->execute()) {
            throw new Exception("Error al crear el pedido: " . $stmt_order->error);
        }

        // Obtener el ID del pedido reci�n insertado
        $order_id = $conn->insert_id;
        $stmt_order->close();

        // 2. Insertar los detalles de los items del pedido en la tabla 'order_items'
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");

        foreach ($cart_items as $item_data) {
            $product_id = $item_data['product_id'];
            $quantity = $item_data['quantity'];
            $price = $item_data['price'];

            $stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $price); // i: integer, i: integer, i: integer, d: double

            if (!$stmt_item->execute()) {
                throw new Exception("Error al insertar item del pedido: " . $stmt_item->error);
            }
        }
        $stmt_item->close();

        // 3. Vaciar el carrito del usuario
        $stmt_clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt_clear_cart->bind_param("i", $user_id);

        if (!$stmt_clear_cart->execute()) {
             throw new Exception("Error al vaciar el carrito: " . $stmt_clear_cart->error);
        }
        $stmt_clear_cart->close();


        // Si todo fue exitoso, confirmar la transacci�n
        $conn->commit();

        // Redirigir a una p�gina de confirmaci�n o a la p�gina de productos
        header("Location: products.php?order_success=true"); // Puedes a�adir un par�metro para mostrar un mensaje
        exit();

    } catch (Exception $e) {
        // Si algo fall�, revertir la transacci�n
        $conn->rollback();
        echo "Error en el proceso de pago: " . $e->getMessage();
        // Considerar redirigir a una p�gina de error o mostrar un mensaje m�s amigable
    }

} else {
    // Si se accede directamente a este archivo sin POST, redirigir al carrito
    header("Location: view_cart.php");
    exit();
}

$conn->close(); // Cerrar la conexi�n a la base de datos
?>
