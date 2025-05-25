<?php
// index.php
// Página principal del sitio web con Hero Section mejorada (altura reducida), enfoque en login/registro,
// mención sutil para admins no logueados, y listado de productos en un grid de 3 columnas responsivo y ajustable
// que se extiende de borde a borde, con imágenes de productos que llenan su contenedor usando Flexbox.
// Usa estilos locales.

session_start(); // Inicia la sesión

// --- INICIO: Definir variables de estado de login al principio ---
// Definir estas variables aquí asegura que siempre existan,
// incluso si el usuario no está logueado o si se redirige.
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
// --- FIN: Definir variables de estado de login al principio ---


// Redirigir si el usuario ya está logueado
// Ahora usamos las variables $is_logged_in y $is_admin que ya están definidas
if ($is_logged_in) {
    if ($is_admin) {
        header("Location: admin.php"); // Redirigir a admin si es admin
    } else {
        header("Location: products.php"); // Redirigir a productos si es usuario normal
    }
    exit();
}

// Si el usuario NO está logueado, el script continúa para mostrar el HTML de la página principal.

// --- INICIO: Incluir conexión a DB y obtener productos ---
require 'db_connect.php'; // Incluye el archivo de conexión a la base de datos

$products = []; // Array para almacenar los productos
$message = ''; // Variable para mensajes (puede usarse para errores de DB)

// Consulta para obtener todos los productos
$sql = "SELECT id, name, image_url FROM products ORDER BY created_at DESC"; // Seleccionamos solo ID, nombre e imagen
$result = $conn->query($sql);

if ($result === false) {
    // Manejar error si la consulta falla
    $message = '<p class="text-red-500">Error al cargar los productos: ' . $conn->error . '</p>';
} else {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    } else {
        $message = '<p class="text-gray-600 mb-4">No hay productos disponibles en este momento.</p>';
    }
}

// Cerrar la conexión a la base de datos después de obtener los datos
$conn->close();
// --- FIN: Incluir conexión a DB y obtener productos ---

// Obtener una marca de tiempo actual para usar en el cache-busting de imágenes
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Umbrella Informatica</title>
    <link href="css/tailwind.css" rel="stylesheet">

    <script>
        // Configuración inline de Tailwind (puede permanecer)
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
    <link href="css/fonts.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(to bottom right, #f0f4f8, #d9e2ec);
            font-family: 'Inter', sans-serif;
            /* Removido background:black para usar el gradiente */
        }
        .container {
            max-width: 960px; /* Ancho más grande para el contenido centrado (navegación, sobre nosotros) */
        }
        .nav-center {
            flex-grow: 1; /* Permite que este contenedor ocupe el espacio disponible */
            text-align: center; /* Centra los elementos dentro */
        }
        .nav-center ul {
            display: inline-flex; /* Muestra los elementos de la lista en línea */
            justify-content: center; /* Centra los elementos de la lista */
            width: 100%; /* Ocupa todo el ancho disponible en el contenedor flex-grow */
            list-style: none; /* Asegura que no haya viñetas en la navegación */
            padding: 0;
            margin: 0;
        }
         .nav-center li a {
            padding: 0.5rem 1rem;
            color: #4b5563; /* Color de texto gris oscuro */
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
         .nav-center li a:hover {
            color: #2563eb; /* Color azul al pasar el mouse */
         }


        /* Estilos para la Hero Section */
        .hero-section {
            background: url('images/hero-background.jpg') no-repeat center center; /* Reemplaza con la ruta a tu imagen */
            background-size: cover; /* Cubre todo el área */
            color: white; /* Texto blanco para contrastar con la imagen de fondo */
            padding: 4rem 1rem; /* Padding superior e inferior reducido */
            text-align: center;
            position: relative;
            margin-bottom: 2rem; /* Espacio debajo de la sección */
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6); /* Capa semi-transparente más oscura */
            z-index: 1; /* Asegura que la capa esté sobre la imagen pero debajo del contenido */
        }

        .hero-content {
            position: relative;
            z-index: 2; /* Asegura que el contenido esté sobre la capa semi-transparente */
        }

        .hero-content h2 {
            font-size: 2.5rem; /* Título más grande */
            font-weight: 700; /* Negrita */
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5); /* Sombra para el texto */
        }

        .hero-content p {
            font-size: 1.25rem; /* Texto de descripción más grande */
            margin-bottom: 2rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5); /* Sombra para el texto */
        }

        .hero-content .cta-button {
             background-color: #2563eb; /* Azul, para Login */
             color: white;
             font-weight: 700;
             padding: 0.75rem 2rem; /* Más padding */
             border-radius: 0.5rem; /* Bordes más redondeados */
             transition: background-color 0.3s ease;
             text-decoration: none; /* Quitar subrayado por defecto */
             display: inline-block; /* Para que el padding y margin funcionen correctamente */
             box-shadow: 0 4px 6px rgba(0,0,0,0.1); /* Sombra para el botón */
             margin-right: 1rem; /* Espacio entre botones */
        }

         .hero-content .cta-button.register {
             background-color: #22c55e; /* Verde, para Registro */
             margin-right: 0; /* Elimina margen si es el último */
         }

        .hero-content .cta-button:hover {
            background-color: #1d4ed8; /* Azul más oscuro */
        }
         .hero-content .cta-button.register:hover {
            background-color: #16a34a; /* Verde más oscuro */
         }

         /* Estilo para el texto de admin login */
         .admin-login-text {
             color: rgba(255, 255, 255, 0.8); /* Blanco semi-transparente */
             font-size: 0.9rem;
             margin-top: 1.5rem;
             text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
         }
         .admin-login-text a {
             color: white; /* Enlace blanco */
             font-weight: 600;
             text-decoration: underline;
         }
         .admin-login-text a:hover {
             text-decoration: none;
         }


         /* Estilos para navegación (repetidos para claridad, pero deberías tenerlos en un archivo CSS separado o en el head) */
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
            font-size: 1.5rem; /* text-2xl */
            font-weight: 700; /* font-bold */
            color: #374151; /* text-gray-700 */
            margin-bottom: 1rem; /* mb-4 */
         }
         main p {
            color: #4b5563; /* text-gray-600 */
            margin-bottom: 1rem; /* mb-4 */
         }


         /* Estilos para botones de Login/Registro en Header (si se mostraran) */
        .bg-blue-500 {
            background-color: #3b82f6;
            color: white;
            font-weight: 600; /* font-semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.25rem; /* rounded-md */
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
             display: inline-block;
             text-align: center;
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
             display: inline-block;
             text-align: center;
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
             display: inline-block;
             text-align: center;
        }
        .bg-green-500:hover {
            background-color: #16a34a; /* hover:bg-green-700 */
        }

        /* Estilos para la sección de productos destacados (ahora de borde a borde) */
        .featured-products {
            background-color: #ffffff;
            padding: 1.5rem 0; /* Padding vertical, 0 horizontal para que llegue a los bordes */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .featured-products .container {
             /* Usamos el contenedor aquí dentro para centrar el contenido del título y el grid */
             padding: 0 1rem; /* Añadimos padding horizontal para que el contenido no toque los bordes */
        }


        .featured-products h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            text-align: center; /* Centrar el título */
        }

        .product-grid {
            display: grid;
            /* MODIFICACIÓN: Ajustar el grid para tender a 3 columnas en pantallas medianas/grandes */
            /* Aumentamos el minmax para que quepan 3 columnas a 100% zoom como a 125% */
            /* Ajustado a 300px para que quepan 3 columnas a 100% zoom en un contenedor de 960px */
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Mantenemos 300px */
            gap: 1.5rem; /* Espacio entre productos */
            /* Aseguramos que el grid ocupe el ancho completo del contenedor .container dentro de featured-products */
             width: 125%;
        }

        .product-item {
            display: flex;
            flex-direction: column; /* Contenedor flex en columna */
            align-items: center; /* Centrar contenido horizontalmente */
            text-align: center;
            border: 1px solid #e2e8f0; /* Borde suave */
            border-radius: 0.5rem;
            padding: 0; /* Eliminamos el padding del contenedor del item */
            background-color: #f7fafc; /* Fondo ligeramente diferente */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out; /* Animación al pasar el mouse */
            overflow: hidden; /* Asegura que la imagen no se salga del borde redondeado */
        }

        .product-item:hover {
            transform: translateY(-5px); /* Efecto de elevación */
        }


        .product-item img {
            width: 100%; /* Imagen ocupa el ancho del contenedor */
            flex-grow: 1; /* La imagen crece para llenar el espacio disponible */
            object-fit: cover; /* La imagen cubre el área, manteniendo aspecto y recortando si es necesario */
            border-radius: 0.5rem 0.5rem 0 0; /* Bordes redondeados solo en la parte superior */
            margin-bottom: 0; /* Eliminamos el margen inferior de la imagen */
             /* MODIFICACIÓN: Aumentamos min-height y max-height para hacer las imágenes más grandes */
             /* Ajustados para que se parezcan más al 125% de zoom */
             min-height: 240px; /* Aumentado */
             max-height: 400x; /* Aumentado */
        }

         /* Estilo para el contenedor del texto dentro del item */
         .product-item .product-info {
             padding: 1rem; /* Añadimos padding al contenedor del texto */
             width: 100%; /* Asegura que el contenedor de texto ocupe el ancho */
             box-sizing: border-box; /* Incluye padding en el ancho */
             flex-shrink: 0; /* Evita que este contenedor se encoja, dando prioridad al crecimiento de la imagen */
         }


        .product-item p {
            font-size: 1rem; /* Nombre un poco más grande */
            font-weight: 600;
            color: #1f2937; /* Texto más oscuro para el nombre */
            margin-bottom: 0; /* Sin margen debajo del nombre */
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
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600 font-semibold">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                <?php if (!$is_logged_in): ?>
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

    <section class="hero-section">
        <div class="hero-content container mx-auto px-4">
            <h2>Bienvenido a Umbrella Informatica</h2>
            <p>Tu solución integral en productos y servicios informáticos.</p>

            <a href="login.php" class="cta-button">Iniciar Sesión</a>
            <a href="register.php" class="cta-button register">Registrarse</a>

            <p class="admin-login-text">
                ¿Eres administrador? <a href="login.php">Accede aquí</a> (Requiere IP autorizada).
            </p>
        </div>
    </section>

    <section class="featured-products">
	<center><h3>Nuestros Productos</h3></center>
        <div class="container mx-auto">
            

            <?php if (!empty($products)): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>?v=<?php echo $timestamp; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="product-info">
                                    <p><?php echo htmlspecialchars($product['name']); ?></p>
                                </div>
                            </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php echo $message; // Muestra el mensaje de no hay productos o error ?>
            <?php endif; ?>
        </div>
    </section>

    <main class="container mx-auto px-4 py-8">
        <section class="bg-white p-6 rounded-lg shadow-lg mb-8 text-center">
             <h2 class="text-xl font-semibold text-gray-700 mb-4">Sobre Nosotros</h2>
             <p class="text-gray-600 leading-relaxed">
                 Somos tu tienda de confianza para componentes de PC y servicios de reparación. Ofrecemos productos de calidad y asistencia experta.
             </p>
             </section>

        <?php // include 'baner.html'; ?>

    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
