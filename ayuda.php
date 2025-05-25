<?php
// ayuda.php
// Página de Ayuda sobre Reparación de PC

session_start(); // Inicia la sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda - Reparación de PC</title>
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
			background:black;
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
    </style>
</head>
<body class="font-sans antialiased text-gray-800">

    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-blue-600 mr-4">Umbrella Informatica</h1>

            <nav class="nav-center">
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-600 hover:text-blue-600">Inicio</a></li>
                    <li><a href="ayuda.php" class="text-gray-600 hover:text-blue-600 font-semibold">Ayuda</a></li>
                    <li><a href="soporte.php" class="text-gray-600 hover:text-blue-600">Soporte</a></li>
                    <li><a href="novedades.php" class="text-gray-600 hover:text-blue-600">Novedades</a></li>
                </ul>
            </nav>

            <div class="flex space-x-4 ml-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="view_cart.php" class="px-4 py-2 bg-blue-500 text-white font-semibold rounded-md hover:bg-blue-600 transition-colors">
                        Carrito
                    </a>
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
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Ayuda con la Reparación de tu PC</h2>
            <p class="text-gray-600 leading-relaxed mb-4">
                Bienvenido a nuestra sección de Ayuda. Aquí encontrarás información útil para solucionar problemas comunes con tu computadora. Si tienes dificultades para identificar el problema o necesitas asistencia, nuestra sección de Soporte está lista para ayudarte. Explora nuestras Novedades para conocer las últimas herramientas y técnicas de diagnóstico.
            </p>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Problemas Comunes y Soluciones:</h3>
            <ul class="list-disc list-inside text-gray-600 mb-4">
                <li>Mi PC está lenta.</li>
                <li>Aparecen mensajes de error extraños.</li>
                <li>La pantalla se queda en negro.</li>
                <li>Problemas de conexión a internet.</li>
                <li>El equipo no enciende.</li>
            </ul>
            <p class="text-gray-600 leading-relaxed">
                Para cada uno de estos problemas, ofrecemos guías paso a paso y consejos prácticos. Utiliza la barra de búsqueda o navega por nuestras categorías para encontrar la solución que necesitas.
            </p>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2023 Mi Sitio Web. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>
