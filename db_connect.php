<?php
// db_connect.php
// Script para conectar a la base de datos

$servername = "localhost"; // Usualmente localhost para XAMPP
$username = "root"; // Usuario por defecto de MySQL en XAMPP
$password = ""; // Contraseña por defecto de MySQL en XAMPP (usualmente vacía)
$dbname = "tienda_online"; // Nombre de la base de datos que creamos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres a utf8mb4
$conn->set_charset("utf8mb4");

// Nota: En un entorno de producción, la gestión de errores debería ser más sofisticada
// y los detalles de conexión no deberían estar directamente en el código fuente.
?>
