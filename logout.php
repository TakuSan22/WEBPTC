<?php
// logout.php
// Script para cerrar la sesi�n del usuario

session_start(); // Inicia la sesi�n

// Destruir todas las variables de sesi�n
$_SESSION = array();

// Si se desea destruir completamente la sesi�n, tambi�n borre la cookie de sesi�n.
// Nota: Esto destruir� la sesi�n, y no solo los datos de sesi�n.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesi�n
session_destroy();

// Redirigir a la p�gina principal (login)
header("Location: index.php");
exit();
?>
