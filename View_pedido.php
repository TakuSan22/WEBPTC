<?php
// View_pedido.php
// Script para recibir y visualizar datos de pedidos enviados por HTTP POST

// Este script debe estar corriendo en un servidor web (como Apache con PHP)
// en la dirección IP y puerto especificados (ej: 192.168.1.101:8080).
// Puede que necesites configurar tu servidor web para escuchar en el puerto 8080
// y dirigir las peticiones a este archivo.

// Permitir peticiones desde cualquier origen (para pruebas locales)
// En un entorno de producción, deberías restringir esto a orígenes de confianza.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain"); // Opcional: indicar que la respuesta es texto plano

// Verificar si la petición es POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener los datos crudos del cuerpo de la petición POST
    // Esto es útil si el cliente envía datos que no son pares clave-valor de formulario estándar
    // $raw_post_data = file_get_contents('php://input');

    // Si los datos se envían como pares clave-valor de formulario (como en Pedido_cliente.php)
    // Puedes acceder a ellos a través de la superglobal $_POST
    if (!empty($_POST)) {
        echo "Datos de Pedido Recibidos:\n";
        // Iterar sobre los datos recibidos y mostrarlos
        foreach ($_POST as $key => $value) {
            echo htmlspecialchars($key) . ": " . htmlspecialchars($value) . "\n";
        }
        // También puedes mostrar los datos crudos si usaste file_get_contents
        // echo "\nDatos POST crudos:\n" . htmlspecialchars($raw_post_data);

        // Opcional: Puedes guardar estos datos en un archivo de log o en una base de datos
        // $log_file = 'received_orders.log';
        // file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $raw_post_data . "\n", FILE_APPEND);

        // Enviar una respuesta al cliente (Pedido_cliente.php)
        http_response_code(200); // Código de estado HTTP 200 OK
        echo "\nConfirmación: Datos recibidos correctamente.";

    } else {
        // Si la petición POST no contiene datos en $_POST
        http_response_code(400); // Código de estado HTTP 400 Bad Request
        echo "Error: No se recibieron datos en la petición POST.";
    }

} else {
    // Si la petición no es POST
    http_response_code(405); // Código de estado HTTP 405 Method Not Allowed
    echo "Método no permitido. Solo se aceptan peticiones POST.";
}

?>
