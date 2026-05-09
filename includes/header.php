<?php
// Nombre del archivo: includes/header.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-09
// Versión: 1.7
// Descripción: Cabecera global. Se agregó el favicon logo-white.png para la pestaña del navegador. Se añadió una regla CSS (.sidebar-collapsed .submenu) para esconder completamente los submenús cuando el panel lateral se colapsa, evitando el desbordamiento de texto.

$pageTitle = $pageTitle ?? 'Portal de Héroes | Caloritrack';
$bodyClass = $bodyClass ?? 'bg-wellness min-h-screen flex items-center justify-center p-4 font-sans text-gray-800 antialiased relative overflow-hidden';
?>
<!DOCTYPE html>
<html lang="es" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <link rel="icon" type="image/png" href="assets/img/logo-white.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configuración de Tailwind con soporte para Modo Oscuro y Colores Corporativos
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        calori: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            900: '#14532d',
                            mint: '#98FB98',
                            sky: '#ADD8E6',
                            pure: '#FFFFFF',
                            softgray: '#A9A9A9',
                            gold: '#FFD700'
                        },
                        darkbase: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617'
                        }
                    },
                    fontFamily: {
                        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                    }
                }
            }
        }

        // Script de Prevención de Flash: Aplicar tema antes de renderizar
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
        
        .bg-wellness {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #e0f2fe 100%);
        }
        .dark .bg-wellness {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        /* Soporte para Sidebar Colapsable */
        #main-sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-collapsed { width: 80px !important; }
        .sidebar-collapsed .nav-label, .sidebar-collapsed .sidebar-header-text { display: none; }
        .sidebar-collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar-collapsed .nav-item i { margin-right: 0; font-size: 1.5rem; }
        
        /* Corrección: Ocultar submenús cuando el sidebar está colapsado */
        .sidebar-collapsed .submenu { display: none !important; }

        <?php echo $extraHead ?? ''; ?>
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?> dark:text-gray-100 transition-colors duration-300">