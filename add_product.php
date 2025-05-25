<?php
// add_product.php
// Script para procesar la adición de un nuevo producto (con depuración)

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php"); // Redirigir a login si no es admin
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

// --- Inicio de Depuración ---
echo "<pre>";
echo "Datos POST recibidos:\n";
print_r($_POST);
echo "\nInformación de Archivos subidos (\$_FILES):\n";
print_r($_FILES);
echo "</pre>";

// Códigos de error de subida de archivo PHP:
// UPLOAD_ERR_OK (0): La subida fue exitosa.
// UPLOAD_ERR_INI_SIZE (1): El archivo subido excede la directiva upload_max_filesize en php.ini.
// UPLOAD_ERR_FORM_SIZE (2): El archivo subido excede la directiva MAX_FILE_SIZE que fue especificada en el formulario HTML.
// UPLOAD_ERR_PARTIAL (3): El archivo subido fue sólo parcialmente subido.
// UPLOAD_ERR_NO_FILE (4): No se subió ningún archivo.
// UPLOAD_ERR_NO_TMP_DIR (6): Falta una carpeta temporal.
// UPLOAD_ERR_CANT_WRITE (7): Fallo al escribir el archivo en el disco.
// UPLOAD_ERR_EXTENSION (8): Una extensión de PHP detuvo la subida del archivo.

if (isset($_FILES['image'])) {
    echo "\nCódigo de error de la imagen subida: " . $_FILES['image']['error'] . "\n";
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo "Posible causa del error: Consulta la lista de códigos de error de subida de archivo PHP arriba.\n";
    }
} else {
    echo "\nEl campo 'image' no está presente en \$_FILES. Asegúrate de que el nombre del input file en el formulario sea 'image' y que el formulario tenga enctype='multipart/form-data'.\n";
}
echo "<hr>";
// --- Fin de Depuración ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $price = $_POST['price'];

    // Manejo de la subida de imagen
    $image_url = null;
    // La condición clave: verificar si el archivo se subió correctamente
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/"; // Directorio donde se guardarán las imágenes (¡asegúrate de que exista y tenga permisos de escritura!)
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        // Validar tipo de archivo (opcional pero recomendado)
        $allowed_types = array("jpg", "jpeg", "png", "gif");
        if (in_array($imageFileType, $allowed_types)) {
            // Mover el archivo subido al directorio de destino
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = $target_file; // Guardar la ruta relativa del archivo
            } else {
                // Si move_uploaded_file falla, mostrar error más específico
                echo "Error al mover el archivo subido. Verifica los permisos de la carpeta 'uploads'.";
                // Considerar redirigir con un mensaje de error
                exit(); // Detener la ejecución si la subida falla
            }
        } else {
            echo "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
            // Considerar redirigir con un mensaje de error
            exit(); // Detener la ejecución si el tipo de archivo es inválido
        }
    } else {
         // Si no se subió ningún archivo o hubo un error en la subida inicial
         if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
             echo "Error en la subida del archivo con código: " . $_FILES['image']['error'];
             // Puedes añadir más detalles aquí basándote en el código de error
         } else {
             // Este caso cubre UPLOAD_ERR_NO_FILE o si $_FILES['image'] no está seteado
             echo "No se seleccionó ningún archivo para subir o hubo un problema con el formulario.";
         }
         // Considerar redirigir con un mensaje de error
         exit(); // Detener la ejecución si la subida inicial falla
    }


    // Validaciones básicas
    if (empty($name) || empty($price) || $image_url === null) {
        // Este mensaje ahora debería ser menos probable si la subida falla antes
        echo "Por favor, completa todos los campos (Nombre, Precio) y asegúrate de que la imagen se subió correctamente.";
        // Considerar redirigir con un mensaje de error
    } else {
        // Preparar la consulta SQL para insertar el producto
        $stmt = $conn->prepare("INSERT INTO products (name, price, image_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $name, $price, $image_url); // s: string, d: double (para precio)

        if ($stmt->execute()) {
            // Producto agregado exitosamente, redirigir al panel de administración
            header("Location: admin.php");
            exit();
        } else {
            echo "Error al agregar el producto a la base de datos: " . $stmt->error;
            // Considerar redirigir con un mensaje de error
        }

        $stmt->close(); // Cerrar la declaración preparada
    }
} else {
    // Si se accede directamente a este archivo sin POST, redirigir al panel de administración
    header("Location: admin.php");
    exit();
}

$conn->close(); // Cerrar la conexión a la base de datos
?>
