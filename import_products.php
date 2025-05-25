<?php
// import_products.php
// Script para importar productos desde un archivo CSV (solo para administradores) (Limpio)

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$message = ''; // Variable para mensajes de estado

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];

    // Validar la subida del archivo
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = '<p class="text-red-600 mb-4">Error al subir el archivo: Código ' . $file['error'] . '</p>';
        // Puedes añadir mensajes más específicos según el código de error
    } elseif ($file['type'] !== 'text/csv' && mime_content_type($file['tmp_name']) !== 'text/csv') {
         $message = '<p class="text-red-600 mb-4">Tipo de archivo inválido. Solo se permiten archivos CSV.</p>';
    } else {
        // Procesar el archivo CSV
        // Usar la ruta temporal del archivo subido
        $temp_file_path = $file['tmp_name'];

        $handle = fopen($temp_file_path, 'r');

        if ($handle !== FALSE) {
            // Leer la primera fila como cabecera y saltarla
            // Si tu CSV NO tiene cabecera, COMENTA la siguiente línea
            $header = fgetcsv($handle);

            $imported_count = 0;
            $errors = [];
            $row_number = 1; // Para seguimiento de filas en el CSV (empezando después de la cabecera si se lee)
            $expected_columns = 5; // El CSV exportado tiene 5 columnas (ID, Nombre, Precio, URL Imagen, Fecha Creacion)

            // Iniciar una transacción para asegurar la integridad de los datos
            $conn->begin_transaction();

            try {
                // Preparar la consulta de inserción
                // NOTA: No insertamos el ID ni la fecha de creación del CSV exportado.
                // La base de datos generará un nuevo ID y fecha de creación.
                $stmt = $conn->prepare("INSERT INTO products (name, price, image_url) VALUES (?, ?, ?)");

                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_number++; // Incrementar contador de fila

                    // Verificación del número de columnas
                    if (count($data) < $expected_columns) {
                         $errors[] = "Fila #" . $row_number . ": Número de columnas incorrecto. Se esperaban " . $expected_columns . " columnas, se encontraron " . count($data) . ".";
                         continue; // Saltar esta fila e ir a la siguiente
                    }

                    // Asumimos que el CSV tiene el formato: ID, Nombre, Precio, URL Imagen, Fecha Creacion
                    // Ajustamos los índices para obtener Nombre, Precio y URL Imagen
                    // Aplicamos trim() para eliminar espacios en blanco
                    $name = trim($data[1] ?? '');      // Nombre está en el índice 1
                    $price = trim($data[2] ?? '');     // Precio está en el índice 2
                    $image_url = trim($data[3] ?? ''); // URL Imagen está en el índice 3


                    // Validaciones básicas de los datos de la fila
                    // Validamos que el Nombre no esté vacío, el Precio no esté vacío y sea numérico
                    if (!empty($name) && !empty($price) && is_numeric($price)) {
                        // Vincular parámetros y ejecutar la inserción
                        $stmt->bind_param("sds", $name, $price, $image_url); // s: string, d: double

                        if ($stmt->execute()) {
                            $imported_count++;
                        } else {
                            // Registrar error para esta fila
                            $errors[] = "Fila #" . $row_number . " (Nombre: '" . htmlspecialchars($name) . "'): Error de base de datos - " . $stmt->error;
                        }
                    } else {
                         // Registrar error para esta fila (datos inválidos o faltantes)
                         $errors[] = "Fila #" . $row_number . " con datos inválidos o faltantes (Nombre vacío, Precio vacío o no numérico): " . htmlspecialchars(implode(",", $data));
                    }
                }

                // Si no hubo errores de base de datos en las inserciones
                if (empty($errors)) {
                    $conn->commit(); // Confirmar la transacción
                    $message = '<p class="text-green-600 mb-4">Importación completada. Se importaron ' . $imported_count . ' productos.</p>';
                } else {
                    // Si hay errores, revertimos la transacción para no importar datos parciales
                    $conn->rollback();
                    $message = '<p class="text-red-600 mb-4">Importación fallida debido a errores en los datos o formato. No se importó ningún producto.</p>';
                    $message .= '<p class="text-red-600">Errores encontrados:</p><ul>';
                    foreach ($errors as $error) {
                        $message .= '<li>' . $error . '</li>';
                    }
                    $message .= '</ul>';
                }

                $stmt->close(); // Cerrar la declaración preparada

            } catch (Exception $e) {
                $conn->rollback(); // Revertir la transacción si ocurre una excepción
                $message = '<p class="text-red-600 mb-4">Error durante la importación: ' . $e->getMessage() . '</p>';
            }


            fclose($handle); // Cerrar el archivo
        } else {
            $message = '<p class="text-red-600 mb-4">Error al abrir el archivo CSV temporal.</p>';
        }
    }
} else {
    // Si se accede directamente a este archivo sin POST o sin archivo
    // Redirigir al panel de administración
    header("Location: admin.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos

// Guardar el mensaje de estado en la sesión y redirigir al panel de administración
$_SESSION['import_message'] = $message;
header("Location: admin.php");
exit();
?>
