<?php
// delete_client.php
// Script para eliminar un usuario (solo para administradores)

session_start(); // Inicia la sesión

// Verificar si el usuario logueado es un administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // Si no es administrador, redirigir a login o a una página de error
    header("Location: login.php"); // O una página de error de permisos
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// Verificar si se recibió el ID del usuario por POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id_to_delete = $_POST['user_id'];

    // Validar que el user_id sea un número entero
    if (!filter_var($user_id_to_delete, FILTER_VALIDATE_INT)) {
        // Redirigir de vuelta a la página de administración de clientes con un mensaje de error
        header("Location: clientes_administrar.php?message=" . urlencode("ID de usuario inválido."));
        exit();
    }

    // Preparar la consulta SQL para eliminar el usuario
    // Es crucial verificar que el usuario a eliminar NO sea el propio administrador logueado
    // para evitar que un admin se elimine a sí mismo.
    // También verificamos que el usuario a eliminar no sea otro administrador.
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id != ? AND is_admin = FALSE");
    $stmt->bind_param("ii", $user_id_to_delete, $_SESSION['user_id']); // ii: two integers

    if ($stmt->execute()) {
        // Verificar si se afectó alguna fila (si el usuario existía, no era admin y no era el admin logueado)
        if ($stmt->affected_rows > 0) {
            // Usuario eliminado exitosamente
            $message = "Usuario con ID " . htmlspecialchars($user_id_to_delete) . " eliminado exitosamente.";
        } else {
            // No se encontró el usuario, ya era admin, o era el admin logueado
             $message = "No se pudo eliminar el usuario (podría no existir, ser un administrador o ser tu propio usuario).";
        }
         // Redirigir de vuelta a la página de administración de clientes con un mensaje de éxito o advertencia
         header("Location: clientes_administrar.php?message=" . urlencode($message));
         exit();

    } else {
        // Error en la ejecución de la consulta
        $message = "Error al eliminar el usuario: " . $stmt->error;
        // Redirigir de vuelta a la página de administración de clientes con un mensaje de error
        header("Location: clientes_administrar.php?message=" . urlencode($message));
        exit();
    }

    $stmt->close(); // Cerrar la declaración preparada

} else {
    // Si se accede directamente a este archivo sin POST, redirigir a la página de administración de clientes
    header("Location: clientes_administrar.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
