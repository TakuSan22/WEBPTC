<?php
// view_cart.php
// Página para visualizar el contenido del carrito de compras del usuario

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no está logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$user_id = $_SESSION['user_id'];
$cart_items = []; // Array para almacenar los items del carrito con detalles del producto
$total_amount = 0; // Variable para calcular el total del carrito
$message = ''; // Variable para mensajes de estado

// Consulta SQL para obtener los items del carrito del usuario, uniendo con la tabla de productos
$sql = "SELECT
            c.id as cart_item_id,
            c.product_id,
            c.quantity,
            p.name as product_name,
            p.price as product_price,
            p.image_url
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?";

$stmt = $conn->prepare($sql);

// --- INICIO DE LA CORRECCIÓN PARA EL ERROR ---
// Verifica si la preparación de la consulta falló
if ($stmt === false) {
    // Si falló, muestra un error detallado y detiene la ejecución
    die('Error al preparar la consulta del carrito: ' . $conn->error);
    // En un entorno de producción, podrías querer registrar el error
    // y mostrar un mensaje más amigable al usuario.
}
// --- FIN DE LA CORRECCIÓN ---


$stmt->bind_param("i", $user_id); // i: integer

if ($stmt->execute()) {
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Calcular el subtotal para cada item y sumarlo al total general
            $subtotal = $row['quantity'] * $row['product_price'];
            $total_amount += $subtotal;

            // Almacenar los datos del item, incluyendo subtotal
            $cart_items[] = [
                'cart_item_id' => $row['cart_item_id'],
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'product_name' => $row['product_name'],
                'product_price' => $row['product_price'],
                'image_url' => $row['image_url'],
                'subtotal' => $subtotal // Añadir el subtotal calculado
            ];
        }
    } else {
        $message = '<p class="text-gray-600">Tu carrito está vacío.</p>';
    }
} else {
    // Error en la ejecución de la consulta
    $message = '<p class="text-red-500">Error al cargar el carrito: ' . $stmt->error . '</p>';
    // Considerar un manejo de error más robusto en producción
}

$stmt->close(); // Cerrar la declaración preparada
$conn->close(); // Cerrar la conexión a la base de datos

// Obtener una marca de tiempo actual para usar en el cache-busting de imágenes
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #f0f4f8, #d9e2ec);
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 960px;
        }
         .nav-center {
            flex-grow: 1;
            text-align: center;
        }
         .nav-center ul {
            display: inline-flex;
            justify-content: center;
            width: 100%;
        }
         /* Estilos para botones */
        .bg-blue-500 {
            background-color: #3b82f6;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bg-blue-500:hover {
            background-color: #2563eb; /* hover:bg-blue-600 */
        }
         .bg-red-500 {
            background-color: #ef4444;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bg-red-500:hover {
            background-color: #dc2626; /* hover:bg-red-600 */
        }
         .bg-green-500 {
             background-color: #22c55e;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.5); /* focus:shadow-outline (approx) */
        }
        .bg-green-500:hover {
            background-color: #16a34a; /* hover:bg-green-700 */
        }
         .bg-yellow-500 {
             background-color: #f59e0b;
             color: white;
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.5); /* focus:shadow-outline (approx) */
         }
         .bg-yellow-500:hover {
             background-color: #d97706; /* hover:bg-yellow-700 */
         }


         /* Estilos para navegación */
         header {
            background-color: #ffffff; /* bg-white */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.075); /* shadow-md */
            padding-top: 1rem; /* py-4 */
            padding-bottom: 1rem; /* py-4 */
        }
         header .container {
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem; /* px-4 */
            padding-right: 1rem; /* px-4 */
            display: flex;
            align-items: center; /* items-center */
            justify-content: space-between; /* justify-between */
        }
         header h1 {
            font-size: 1.5rem; /* text-2xl */
            font-weight: 700; /* font-bold */
            color: #2563eb; /* text-blue-600 */
            margin-right: 1rem; /* mr-4 */
        }
         header nav ul {
            display: flex;
            space-x: 1rem; /* space-x-4 */
        }
         header nav a {
            color: #4b5563; /* text-gray-600 */
            transition-property: color, background-color, border-color, text-decoration, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
         header nav a:hover {
            color: #2563eb; /* hover:text-blue-600 */
         }
         main {
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem; /* px-4 */
            padding-right: 1rem; /* px-4 */
            padding-top: 2rem; /* py-8 */
            padding-bottom: 2rem; /* py-8 */
            max-width: 960px; /* container max-width */
         }
         main section {
            background-color: #ffffff; /* bg-white */
            padding: 1.5rem; /* p-6 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-lg */
            margin-bottom: 2rem; /* mb-8 */
         }
         main h2 {
            font-size: 1.25rem; /* text-xl */
            font-weight: 600; /* font-semibold */
            color: #374151; /* text-gray-700 */
            margin-bottom: 1rem; /* mb-4 */
         }
         main h3 {
            font-size: 1.125rem; /* text-lg */
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* text-gray-800 */
            margin-bottom: 0.5rem; /* mb-2 */
         }
         main p {
            color: #4b5563; /* text-gray-600 */
            margin-bottom: 1rem; /* mb-4 */
         }
         .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem; /* mt-6 */
         }
         .cart-table th, .cart-table td {
            padding: 0.75rem; /* py-3 px-4 */
            text-align: left;
            border-bottom: 1px solid #e2e8f0; /* border-gray-200 */
         }
         .cart-table th {
            background-color: #f8f8f8; /* bg-gray-50 */
            font-weight: 600; /* font-semibold */
            color: #4b5563; /* text-gray-600 */
         }
         .cart-item-image {
             width: 80px; /* w-20 */
             height: auto;
             border-radius: 0.25rem; /* rounded */
             margin-right: 1rem; /* mr-4 */
         }
         .text-right {
             text-align: right;
         }
         .font-bold {
             font-weight: 700;
         }


         footer {
            background-color: #1f2937; /* bg-gray-800 */
            color: white;
            padding-top: 1.5rem; /* py-6 */
            padding-bottom: 1.5rem; /* py-6 */
            margin-top: 2rem; /* mt-8 */
         }
         footer .container {
             margin-left: auto;
             margin-right: auto;
             padding-left: 1rem; /* px-4 */
             padding-right: 1rem; /* px-4 */
             text-align: center;
         }
    </style>
</head>
<body class="font-sans antialiased text-gray-800">

    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-blue-600 mr-4">Umbrella Informatica</h1>

            <nav class="nav-center">
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <a href="admin.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                            Admin
                        </a>
                         <a href="view_orders.php" class="px-4 py-2 bg-purple-500 text-white font-semibold rounded-md hover:bg-purple-600 transition-colors">
                            Pedidos
                        </a>
                    <?php else: ?>
                        <a href="view_cart.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                            Carrito
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                        Cerrar Sesión
                    </a>
                <?php else: ?>
                    <a href="login.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                        Login
                    </a>
                    <a href="register.php" class="px-4 py-2 bg-green-500 text-white font-semibold rounded-md hover:bg-green-600 transition-colors">
                        Registro
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Tu Carrito de Compras</h2>

            <?php echo $message; // Mostrar mensaje de carrito vacío o error ?>

            <?php if (!empty($cart_items)): ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td class="flex items-center">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>?v=<?php echo $timestamp; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                                    <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                </td>
                                <td>$<?php echo htmlspecialchars(number_format($item['product_price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></td>
                                <td>
                                    <form action="remove_from_cart.php" method="post" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este item del carrito?');">
                                        <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold py-1 px-2 rounded">
                                            Eliminar
                                        </button>
                                    </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right font-bold">Total:</td>
                            <td class="font-bold">$<?php echo htmlspecialchars(number_format($total_amount, 2)); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mt-6 text-right">
                    <form action="checkout.php" method="post">
                        <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                        <?php foreach ($cart_items as $item): ?>
                            <input type="hidden" name="cart_items[<?php echo $item['product_id']; ?>][product_id]" value="<?php echo $item['product_id']; ?>">
                            <input type="hidden" name="cart_items[<?php echo $item['product_id']; ?>][quantity]" value="<?php echo $item['quantity']; ?>">
                            <input type="hidden" name="cart_items[<?php echo $item['product_id']; ?>][price]" value="<?php echo $item['product_price']; ?>">
                        <?php endforeach; ?>

                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                            Proceder al Pago
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="mt-6">
                <a href="products.php" class="text-blue-600 hover:underline">Seguir Comprando</a>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>