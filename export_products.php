<?php
// export_products.php
// Script para exportar la lista de productos a un archivo CSV (solo para administradores)

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// Obtener todos los productos de la base de datos
$sql = "SELECT id, name, price, image_url, created_at FROM products ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Configurar las cabeceras HTTP para descargar un archivo CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="productos_exportados_' . date('Ymd') . '.csv"');

    // Crear un puntero a un archivo temporal en memoria
    $output = fopen('php://temp', 'r+');

    // Escribir la cabecera del CSV
    fputcsv($output, array('ID', 'Nombre', 'Precio', 'URL Imagen', 'Fecha Creacion'));

    // Escribir los datos de los productos en el CSV
    while($row = $result->fetch_assoc()) {
        // Formatear el precio a 2 decimales
        $row['price'] = number_format($row['price'], 2, '.', '');
        fputcsv($output, $row);
    }

    // Mover el puntero al inicio del archivo temporal
    rewind($output);

    // Imprimir el contenido del archivo temporal en la salida
    fpassthru($output);

    // Cerrar el puntero del archivo
    fclose($output);

} else {
    // Si no hay productos, mostrar un mensaje o redirigir
    echo "No hay productos para exportar.";
    // Opcional: header("Location: admin.php?message=no_products_to_export");
}

$conn->close(); // Cerrar la conexión a la base de datos

exit(); // Terminar el script
?>
