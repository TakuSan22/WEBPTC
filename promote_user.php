<?php
// promote_user.php
// Script para convertir un usuario normal en administrador y asignarle la siguiente IP

session_start(); // Inicia la sesión

// Verificar si el usuario que realiza la acción está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$promote_message = ''; // Variable para mensajes de estado

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id_to_promote = $_POST['user_id'];

    // Validar que el user_id sea un número entero
    if (!filter_var($user_id_to_promote, FILTER_VALIDATE_INT)) {
        $promote_message = '<p class="text-red-600 mb-4">ID de usuario inválido.</p>';
    } else {
        // Verificar que el usuario exista y NO sea ya un administrador
        $stmt_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_id_to_promote);
        $stmt_check->execute();
        $stmt_check->bind_result($is_admin);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($is_admin) {
            $promote_message = '<p class="text-yellow-600 mb-4">El usuario seleccionado ya es administrador.</p>';
        } else {
            // --- Lógica para encontrar la siguiente IP disponible ---
            // Asumimos un formato de IP 192.168.1.X
            $base_ip_segment = '192.168.1.';
            $starting_octet = 101; // El primer octeto para administradores

            // Obtener la última IP asignada a un administrador
            // Ordenamos por el último octeto (convertido a número) para encontrar la más alta
            $stmt_last_ip = $conn->prepare("SELECT admin_ip FROM users WHERE is_admin = TRUE AND admin_ip IS NOT NULL ORDER BY INET_ATON(admin_ip) DESC LIMIT 1");
            $stmt_last_ip->execute();
            $stmt_last_ip->bind_result($last_admin_ip);
            $stmt_last_ip->fetch();
            $stmt_last_ip->close();

            $next_admin_ip = $base_ip_segment . $starting_octet; // IP inicial por defecto

            if ($last_admin_ip) {
                // Si hay una última IP, calcular la siguiente
                $parts = explode('.', $last_admin_ip);
                if (count($parts) === 4 && $parts[0] === '192' && $parts[1] === '168' && $parts[2] === '1') {
                    $last_octet = intval($parts[3]);
                    $next_octet = $last_octet + 1;
                    // Puedes añadir validación aquí para asegurar que no exceda 254
                    if ($next_octet <= 254) {
                         $next_admin_ip = $base_ip_segment . $next_octet;
                    } else {
                         // Manejar caso donde no hay IPs disponibles en el rango
                         $promote_message = '<p class="text-red-600 mb-4">No hay direcciones IP disponibles en el rango 192.168.1.101-254 para asignar.</p>';
                         $next_admin_ip = null; // No asignar IP si no hay disponibles
                    }
                } else {
                    // Si la última IP no tiene el formato esperado, usar la inicial
                     $promote_message = '<p class="text-yellow-600 mb-4">Advertencia: La última IP de administrador no tiene el formato esperado. Asignando IP inicial: ' . $next_admin_ip . '</p>';
                }
            }

            // --- Fin Lógica para encontrar la siguiente IP disponible ---

            // Promover al usuario y asignar la IP (solo si se pudo calcular una IP)
            if ($next_admin_ip !== null) {
                 $stmt_promote = $conn->prepare("UPDATE users SET is_admin = TRUE, admin_ip = ? WHERE id = ?");
                 $stmt_promote->bind_param("si", $next_admin_ip, $user_id_to_promote);

                 if ($stmt_promote->execute()) {
                     $promote_message = '<p class="text-green-600 mb-4">Usuario promovido a administrador y IP ' . htmlspecialchars($next_admin_ip) . ' asignada exitosamente.</p>';
                 } else {
                     $promote_message = '<p class="text-red-600 mb-4">Error al promover usuario o asignar IP: ' . $stmt_promote->error . '</p>';
                 }
                 $stmt_promote->close();
            }
        }
    }
} else {
    // Si se accede directamente a este archivo sin POST o sin user_id
    $promote_message = '<p class="text-red-600 mb-4">Solicitud inválida.</p>';
}

$conn->close(); // Cerrar la conexión a la base de datos

// Guardar el mensaje de estado en la sesión y redirigir a la página de gestión de usuarios
$_SESSION['promote_message'] = $promote_message;
header("Location: manage_users.php");
exit();
?>
