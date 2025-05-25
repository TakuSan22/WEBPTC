<?php
// delete_product.php
// Script para eliminar un producto (solo para administradores)
// MODIFICADO: Ya no elimina el archivo de imagen f�sico.

session_start(); // Inicia la sesi�n

// Verificar si el usuario est� logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexi�n a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];

    // Validar que el product_id sea un n�mero entero
    if (!filter_var($product_id, FILTER_VALIDATE_INT)) {
        echo "ID de producto inv�lido.";
        // Considerar redirigir con un mensaje de error
        exit();
    }

    // --- C�digo eliminado: Ya no se obtiene la ruta de la imagen para eliminar el archivo f�sico ---
    // $stmt_img = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
    // $stmt_img->bind_param("i", $product_id);
    // $stmt_img->execute();
    // $stmt_img->bind_result($image_url);
    // $stmt_img->fetch();
    // $stmt_img->close();
    // --- Fin C�digo eliminado ---


    // Preparar la consulta SQL para eliminar el producto de la base de datos
    // La configuraci�n ON DELETE CASCADE en la tabla cart eliminar� los items del carrito asociados.
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id); // i: integer

    if ($stmt->execute()) {
        // Producto eliminado exitosamente de la base de datos.
        // --- C�digo eliminado: Ya no se elimina el archivo de imagen f�sico ---
        // Opcional: Eliminar el archivo de imagen f�sico si existe
        // if ($image_url && file_exists($image_url)) {
        //     unlink($image_url); // Elimina el archivo
        // }
        // --- Fin C�digo eliminado ---

        header("Location: admin.php"); // Redirigir al panel de administraci�n
        exit();
    } else {
        echo "Error al eliminar el producto de la base de datos: " . $stmt->error;
        // Considerar redirigir con un mensaje de error
    }

    $stmt->close(); // Cerrar la declaraci�n preparada

} else {
    // Si se accede directamente a este archivo sin POST, redirigir al panel de administraci�n
    header("Location: admin.php");
    exit();
}

$conn->close(); // Cerrar la conexi�n a la base de datos
?>
