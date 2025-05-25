<?php
// db_connect.php
// Script para conectar a la base de datos

$servername = "localhost"; // Usualmente localhost para XAMPP
$username = "root"; // Usuario por defecto de MySQL en XAMPP
$password = ""; // Contrase�a por defecto de MySQL en XAMPP (usualmente vac�a)
$dbname = "tienda_online"; // Nombre de la base de datos que creamos

// Crear conexi�n
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexi�n
if ($conn->connect_error) {
    die("Conexi�n fallida: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres a utf8mb4
$conn->set_charset("utf8mb4");

// Nota: En un entorno de producci�n, la gesti�n de errores deber�a ser m�s sofisticada
// y los detalles de conexi�n no deber�an estar directamente en el c�digo fuente.
?>
