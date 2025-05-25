<?php
// products.php
// Página para que los usuarios vean los productos (Productos en 3 columnas)

session_start(); // Inicia la sesión

// Verificar si el usuario está logueado (no es necesario ser admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirigir a login si no está logueado
    exit();
}

require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$message = ''; // Variable para mensajes de estado

// Lógica para mostrar productos existentes
$sql = "SELECT id, name, price, image_url FROM products ORDER BY created_at DESC";
$result = $conn->query($sql);

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $message = '<p class="text-gray-600 mb-4">No hay productos disponibles en este momento.</p>';
}

$conn->close(); // Cerrar la conexión a la base de datos (después de obtener los datos)

// Obtener una marca de tiempo actual para usar en el cache-busting de imágenes
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuestros Productos</title>
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
         /* Estilos para las tarjetas de producto */
         .product-card {
            border: 1px solid #e2e8f0; /* border-gray-200 */
            border-radius: 0.5rem; /* rounded-lg */
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* shadow-md */
            display: flex;
            flex-direction: column;
        }
        .product-image {
            width: 100%;
            height: 150px; /* Altura fija para las imágenes */
            object-fit: cover; /* Asegura que la imagen cubra el área sin distorsionarse */
            border-top-left-radius: 0.5rem; /* rounded-t-lg */
            border-top-right-radius: 0.5rem; /* rounded-t-lg */
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
         main .text-gray-600 {
            color: #4b5563;
         }
         main .mb-4 {
            margin-bottom: 1rem;
         }
         main .grid {
            display: grid;
            gap: 1.5rem; /* gap-6 */
         }
         @media (min-width: 640px) { /* sm breakpoint */
            main .sm:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
         }
         @media (min-width: 768px) { /* md breakpoint */
            main .md:grid-cols-3 { /* 3 columnas en pantallas medianas y superiores */
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
         }
         /* Eliminamos lg:grid-cols-4 para mantener 3 columnas en pantallas grandes */
         /* @media (min-width: 1024px) { // lg breakpoint
            main .lg:grid-cols-4 {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
         } */
         main .p-4 {
            padding: 1rem;
         }
         main .w-full {
            width: 100%;
         }
         main .mt-auto {
            margin-top: auto;
         }
         main form button {
             font-weight: 700; /* font-bold */
             padding: 0.5rem 1rem; /* py-2 px-4 */
             border-radius: 0.25rem; /* rounded */
             transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
             transition-duration: 150ms;
             transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             outline: none; /* focus:outline-none */
             box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5); /* focus:shadow-outline (approx) */
         }
         main form button.bg-blue-500:hover {
             background-color: #2563eb; /* hover:bg-blue-700 */
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
            <h1 class="text-2xl font-bold text-blue-600 mr-4">Nuestros Productos</h1>

            <nav class="nav-center">
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                 <a href="view_cart.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                    Carrito
                </a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600 transition-colors">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Lista de Productos</h2>
            <?php echo $message; // Mostrar mensajes de estado ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="product-card rounded-lg shadow-md overflow-hidden flex flex-col">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>?v=<?php echo $timestamp; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <div class="p-4 flex-grow flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-gray-600 mb-4">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                            </div>
                            <form action="add_to_cart.php" method="post" class="mt-auto">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit"
                                        class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                                    Añadir al Carrito
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Tienda Online.</p>
        </div>
    </footer>

</body>
</html>
