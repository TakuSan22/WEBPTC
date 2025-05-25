<?php
// make_admin.php
// Script to convert a normal user into an administrator
// Assigns the next sequential IP (192.168.1.x) and marks as admin.
// Includes IP verification for the admin performing the action.

session_start(); // Start the session

// Check if the logged-in user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // If not admin, redirect
    header("Location: login.php"); // Or a permissions error page
    exit();
}

require 'db_connect.php'; // Include the database connection file

// --- START: IP Verification for the Admin performing the action ---
$admin_user_id_action = $_SESSION['user_id']; // ID of the logged-in admin performing the action
$current_ip = $_SERVER['REMOTE_ADDR'];

// Query to get the assigned IP for the logged-in admin
$stmt_ip = $conn->prepare("SELECT assigned_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
if ($stmt_ip === false) { die('Error interno al verificar IP (Prepare failed): ' . $conn->error); }
$stmt_ip->bind_param("i", $admin_user_id_action);
if (!$stmt_ip->execute()) { die('Error interno al verificar IP (Execute failed): ' . $stmt_ip->error); }
$stmt_ip->bind_result($assigned_ip_action);
$stmt_ip->fetch();
$stmt_ip->close();


// If the admin has an assigned IP AND the current IP does NOT match, deny access.
if ($assigned_ip_action !== NULL && $assigned_ip_action !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para realizar esta acción de administración. Su IP actual es: " . $current_ip));
    exit();
}
// --- END: IP Verification for the Admin performing the action ---


$message = ''; // Variable for status messages

// Check if user ID was received via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id_to_make_admin = $_POST['user_id'];

    // Validate that user_id is an integer
    if (!filter_var($user_id_to_make_admin, FILTER_VALIDATE_INT)) {
        $message = urlencode("ID de usuario inválido.");
         header("Location: clientes_administrar.php?message=" . $message);
         $conn->close();
        exit();
    }

    // --- Logic to assign the next sequential IP ---

    $next_ip_suffix = 101; // Start with .101 if no admins have an assigned IP

    // Find the highest IP assigned to an admin that starts with 192.168.1.
    $sql_last_ip = "SELECT assigned_ip FROM users
                    WHERE is_admin = TRUE AND assigned_ip IS NOT NULL AND assigned_ip LIKE '192.168.1.%'
                    ORDER BY INET_ATON(assigned_ip) DESC LIMIT 1"; // Order by the numeric value of the IP

    $result_last_ip = $conn->query($sql_last_ip);

    if ($result_last_ip === false) {
         // Error searching for the last IP
         $message = urlencode("Error al buscar la última IP asignada: " . $conn->error);
         header("Location: clientes_administrar.php?message=" . $message);
         $conn->close();
         exit();
    }

    if ($result_last_ip->num_rows > 0) {
        $row_last_ip = $result_last_ip->fetch_assoc();
        $last_assigned_ip = $row_last_ip['assigned_ip'];

        // Extract the last number of the IP (e.g., from '192.168.1.105' get 105)
        $parts = explode('.', $last_assigned_ip);
        $last_number = end($parts); // Gets the last element of the array

        // Ensure the last number is actually a number
        if (is_numeric($last_number)) {
            $next_ip_suffix = (int)$last_number + 1;
        }
         // If not numeric, $next_ip_suffix keeps the initial value 101
    }

    // Construct the next IP
    $next_assigned_ip = '192.168.1.' . $next_ip_suffix;

    // --- End: Logic to assign the next sequential IP ---


    // Prepare the SQL query to update the user:
    // 1. Mark as admin (is_admin = TRUE)
    // 2. Assign the calculated next IP
    // 3. Ensure the user to modify is NOT the logged-in admin themselves
    //    to prevent an admin from removing their own permissions or changing their own IP by mistake.
    $stmt = $conn->prepare("UPDATE users SET is_admin = TRUE, assigned_ip = ? WHERE id = ? AND id != ?");
     if ($stmt === false) {
        $message = urlencode("Error al preparar la consulta de actualización de admin: " . $conn->error);
         header("Location: clientes_administrar.php?message=" . $message);
         $conn->close();
        exit();
    }
    // Bind the assigned IP (s), the ID of the user to modify (i), and the ID of the logged-in admin (i)
    $stmt->bind_param("sii", $next_assigned_ip, $user_id_to_make_admin, $admin_user_id_action); // s: string, i: integer, i: integer

    if ($stmt->execute()) {
        // Check if any row was affected
        if ($stmt->affected_rows > 0) {
            // User successfully converted to admin and IP assigned
            $message = urlencode("Usuario con ID " . htmlspecialchars($user_id_to_make_admin) . " ahora es administrador con IP asignada: " . $next_assigned_ip);
        } else {
            // User not found, or already admin, or was the logged-in admin
             $message = urlencode("No se pudo convertir el usuario con ID " . htmlspecialchars($user_id_to_make_admin) . " a administrador (podría no existir, ya ser administrador o ser tu propio usuario).");
        }
         // Redirect back to the client management page
         header("Location: clientes_administrar.php?message=" . $message);
         exit();

    } else {
        // Error executing the query
        $message = urlencode("Error al actualizar el usuario a administrador: " . $stmt->error);
        header("Location: clientes_administrar.php?message=" . $message);
        exit();
    }

    $stmt->close(); // Close the prepared statement

} else {
    // If this file is accessed directly without POST
    header("Location: clientes_administrar.php");
    exit();
}

$conn->close(); // Close the database connection (this line might not be reached if there's an exit() before)
?>
