<?php
session_start();
require 'db_connect.php'; // Conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $user_id = $_SESSION['user_id']; // Asegúrate de que el usuario esté logueado
        $target_dir = "uploads/profile_pictures/";
        $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validar tipo de archivo
        $allowed_types = array("jpg", "jpeg", "png", "gif");
        if (in_array($imageFileType, $allowed_types)) {
            // Mover el archivo subido
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                // Actualizar la base de datos con la nueva imagen
                $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $target_file, $user_id);
                $stmt->execute();
                $stmt->close();
                header("Location: profile.php"); // Redirigir a la página de perfil
                exit();
            } else {
                echo "Error al mover el archivo subido.";
            }
        } else {
            echo "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
        }
    } else {
        echo "Error en la subida del archivo.";
    }
}
?>