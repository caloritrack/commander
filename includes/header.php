<?php
// Nombre del archivo: includes/header.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.4
// Descripción: Cabecera global del portal. Se implementaron variables dinámicas ($pageTitle, $bodyClass, $extraHead) para soportar diferentes layouts (Login vs Dashboard) manteniendo la misma configuración base de Tailwind CSS y la paleta de colores corporativa.

$pageTitle = $pageTitle ?? 'Portal de Héroes | Caloritrack';
$bodyClass = $bodyClass ?? 'bg-wellness min-h-screen flex items-center justify-center p-4 font-sans text-gray-800 antialiased relative overflow-hidden';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
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
                        }
                    },
                    fontFamily: {
                        sans: ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        .bg-wellness {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #e0f2fe 100%);
        }
        /* Inyección de estilos dinámicos por vista */
        <?php echo $extraHead ?? ''; ?>
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">