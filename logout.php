<?php
// logout.php
// Script para cerrar la sesión del usuario

session_start(); // Inicia la sesión

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir completamente la sesión, también borre la cookie de sesión.
// Nota: Esto destruirá la sesión, y no solo los datos de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir a la página principal (login)
header("Location: index.php");
exit();
?>
