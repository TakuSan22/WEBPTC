<?php
// process_order.php
// Script to mark an order as "Processed" (admin only)
// Receives order ID via POST.
// Modified to save the processing admin's ID, add IP verification,
// and send order details to the admin's 'send_ip' via socket.

session_start(); // Start the session

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // If not admin, redirect with an error message
    $message = urlencode("Acceso denegado. Solo administradores pueden procesar pedidos.");
    header("Location: view_orders.php?message=" . $message);
    exit();
}

require 'db_connect.php'; // Include the database connection file

// --- START: Admin IP Verification and get send_ip ---
$user_id_admin = $_SESSION['user_id']; // ID of the logged-in admin
$current_ip = $_SERVER['REMOTE_ADDR'];

// Query to get the assigned IP, username, and send IP for the logged-in admin
$stmt_admin_info = $conn->prepare("SELECT assigned_ip, username, send_ip FROM users WHERE id = ? AND is_admin = TRUE LIMIT 1");
if ($stmt_admin_info === false) { die('Error interno al verificar IP o obtener send_ip (Prepare failed): ' . $conn->error); }
$stmt_admin_info->bind_param("i", $user_id_admin);
if (!$stmt_admin_info->execute()) { die('Error interno al verificar IP o obtener send_ip (Execute failed): ' . $stmt_admin_info->error); }
$stmt_admin_info->bind_result($assigned_ip, $admin_username, $admin_send_ip); // Get assigned_ip, username, and send_ip
$stmt_admin_info->fetch();
$stmt_admin_info->close();


// If the admin has an assigned IP AND the current IP does NOT match, deny access.
if ($assigned_ip !== NULL && $assigned_ip !== $current_ip) {
    $conn->close();
    header("Location: login.php?message=" . urlencode("Acceso denegado: IP no autorizada para este administrador. Su IP actual es: " . $current_ip));
    exit();
}
// --- END: Admin IP Verification and get send_ip ---


$message = ''; // Variable for the redirect message
// Admin username is already in $admin_username
// Admin send IP is already in $admin_send_ip


// Ensure the request is POST and order_id is received
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    // Validate that order_id is an integer
    if (!filter_var($order_id, FILTER_VALIDATE_INT)) {
        // Redirect with an error message
        $message = urlencode("ID de pedido inválido para procesar.");
        header("Location: view_orders.php?message=" . $message); // Or to Ver_Pedido.php with the ID if possible
        $conn->close();
        exit();
    }

    // --- Logic to verify Accepted status ---
     // Verify that this order is in 'Accepted' state
     $sql_check_accepted = "SELECT id FROM orders WHERE status = 'Accepted' AND id = ? LIMIT 1";
     $stmt_check_accepted = $conn->prepare($sql_check_accepted);
      if ($stmt_check_accepted === false) {
         $message = urlencode("Error al preparar verificación de estado Accepted: " . $conn->error);
         header("Location: view_orders.php?message=" . $message);
         $conn->close();
         exit();
     }
     $stmt_check_accepted->bind_param("i", $order_id);
     if (!$stmt_check_accepted->execute()) {
         $message = urlencode("Error al ejecutar verificación de estado Accepted: " . $stmt_check_accepted->error);
         header("Location: view_orders.php?message=" . $message);
         $conn->close();
         exit();
     }
     $result_check_accepted = $stmt_check_accepted->get_result();


    if ($result_check_accepted->num_rows == 0) {
        // The order is not in 'Accepted' state. It cannot be processed.
        $message = urlencode("El Pedido #" . $order_id . " no está en estado 'Aceptado' y no puede ser procesado.");
        header("Location: view_orders.php?message=" . $message); // Redirect to the list
        $stmt_check_accepted->close();
        $conn->close();
        exit();
    }
     $stmt_check_accepted->close();
    // --- END: Accepted status verification logic ---


    // Prepare the SQL query to update the order status to 'Processed'
    // AND SAVE the ID of the admin who processed it.
    // Only update if the current status is 'Accepted'
    $stmt = $conn->prepare("UPDATE orders SET status = 'Processed', processed_by_admin_id = ? WHERE id = ? AND status = 'Accepted'");
     if ($stmt === false) {
        $message = urlencode("Error al preparar la consulta de procesamiento: " . $conn->error);
        header("Location: view_orders.php?message=" . $message); // Redirect with error
        $conn->close();
        exit();
    }
    // Bind the admin ID (i) and order ID (i)
    $stmt->bind_param("ii", $user_id_admin, $order_id);

    if ($stmt->execute()) {
        // Check if any row was affected
        if ($stmt->affected_rows > 0) {
            // Success: the order was marked as processed.
            // Now, collect data to send to the Winsock server.

            $message = "Pedido #" . $order_id . " marcado como procesado."; // Default success message

            // --- START: Collect order data to send ---

            $customer_username = '';
            $product_names = []; // Array to store product names

            // Get customer username
            $stmt_customer = $conn->prepare("SELECT u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
             if ($stmt_customer === false) {
                 // Handle error, but try to continue if possible
                 error_log("Error preparing customer query for socket: " . $conn->error); // Log the error
             } else {
                 $stmt_customer->bind_param("i", $order_id);
                 if ($stmt_customer->execute()) {
                     $stmt_customer->bind_result($customer_username);
                     $stmt_customer->fetch();
                 } else {
                     error_log("Error executing customer query for socket: " . $stmt_customer->error); // Log the error
                 }
                 $stmt_customer->close();
             }


            // Get product names in the order
            $stmt_products = $conn->prepare("SELECT p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            if ($stmt_products === false) {
                error_log("Error preparing products query for socket: " . $conn->error); // Log the error
            } else {
                $stmt_products->bind_param("i", $order_id);
                 if ($stmt_products->execute()) {
                    $result_products = $stmt_products->get_result();
                    while ($row_product = $result_products->fetch_assoc()) {
                        $product_names[] = $row_product['name'];
                    }
                 } else {
                    error_log("Error executing products query for socket: " . $stmt_products->error); // Log the error
                 }
                $stmt_products->close();
            }


            // Format the data string: admin,client,product1,product2,...
            // Use double quotes to wrap fields as in the example "admin","pepo","producto1","producto2"
            $data_to_send = '"' . $admin_username . '","' . $customer_username . '"';
            if (!empty($product_names)) {
                 // Escape double quotes within product names if they exist
                 $quoted_product_names = array_map(function($name) {
                     return '"' . str_replace('"', '""', $name) . '"';
                 }, $product_names);
                $data_to_send .= ',' . implode(',', $quoted_product_names);
            }


            // --- END: Collect order data to send ---

            // --- START: Send data via Winsock (TCP Socket) ---

            // IP and Port of the Winsock server. Use the logged-in admin's send_ip.
            $winsock_server_ip = $admin_send_ip; // Use the IP obtained from the database
            $winsock_server_port = 5454; // Fixed port as specified
            $socket_timeout = 5; // Seconds timeout for connection and sending

            // Check if the admin has a send IP assigned
            if ($winsock_server_ip === NULL || $winsock_server_ip === '') {
                 $message .= " Advertencia: El administrador no tiene una IP de envío (send_ip) asignada en la base de datos. No se enviarán datos por socket.";
            } else {
                $socket = null; // Initialize socket to null

                // Create TCP/IP socket
                // @ to suppress PHP warnings if the function doesn't exist (though we already checked the extension)
                $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

                if ($socket === false) {
                    $socket_error = socket_strerror(socket_last_error());
                    error_log("socket_create() failed: reason: " . $socket_error);
                     $message .= " Advertencia: Error al crear socket para Winsock. Detalles: " . $socket_error;
                } else {
                    // Set timeout
                    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $socket_timeout, 'usec' => 0));
                    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $socket_timeout, 'usec' => 0));

                    // Connect to the Winsock server
                    // @ to suppress warnings if connection fails
                    $result_connect = @socket_connect($socket, $winsock_server_ip, $winsock_server_port);

                    if ($result_connect === false) {
                        $socket_error = socket_strerror(socket_last_error($socket));
                        error_log("socket_connect() failed: reason: " . $socket_error);
                         $message .= " Advertencia: Error al conectar con servidor Winsock (" . htmlspecialchars($winsock_server_ip) . ":" . $winsock_server_port . "). Detalles: " . $socket_error;
                    } else {
                        // Send the data
                        // Add a delimiter if the Winsock server expects one (e.g., a newline \n or \r\n)
                        $data_to_send_with_delimiter = $data_to_send . "\n"; // Using \n as an example delimiter

                        // @ to suppress warnings if sending fails
                        $bytes_sent = @socket_send($socket, $data_to_send_with_delimiter, strlen($data_to_send_with_delimiter), 0);

                        if ($bytes_sent === false) {
                             $socket_error = socket_strerror(socket_last_error($socket));
                             error_log("socket_send() failed: reason: " . $socket_error);
                             $message .= " Advertencia: Error al enviar datos a Winsock. Detalles: " . $socket_error;
                        } elseif ($bytes_sent < strlen($data_to_send_with_delimiter)) {
                             // Not all bytes were sent
                             error_log("socket_send() sent only " . $bytes_sent . " of " . strlen($data_to_send_with_delimiter) . " bytes.");
                             $message .= " Advertencia: No se enviaron todos los datos a Winsock.";
                        } else {
                             // Send successful
                             // You can add a success message if you want, but the main message already indicates the order was processed.
                             // $message .= " Datos enviados a Winsock.";
                        }

                        // Close the socket
                        socket_close($socket);
                    }
                }
            }

            // --- END: Send data via Winsock ---

            // Redirect back to the order detail page
            // urlencode the final message which might include socket warnings
            header("Location: Ver_Pedido.php?order_id=" . $order_id . "&message=" . urlencode($message));


        } else {
            // The order with that ID was not found or was already processed (or not in 'Accepted')
             $message = urlencode("Pedido #" . $order_id . " no encontrado o no estaba en estado 'Aceptado'.");
             header("Location: view_orders.php?message=" . $message); // Redirect to the list if order doesn't exist or wrong status
        }
    } else {
        // Error executing the UPDATE query
        $message = urlencode("Error al actualizar el estado del pedido: " . $stmt->error);
        header("Location: view_orders.php?message=" . $message); // Redirect with error
    }

    $stmt->close(); // Close the prepared statement

    exit(); // It's crucial to call exit() after header("Location:")

} else {
    // If this file is accessed directly without POST
    header("Location: view_orders.php"); // Redirect to the order list page
    exit();
}

// The database connection is closed before exit() calls or at the end of the script if no early exit.
// $conn->close();
?>
