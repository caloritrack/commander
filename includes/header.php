<?php
// Nombre del archivo: includes/header.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-06-01
// Versión: 1.8
// Descripción: Cabecera global. Se agregó un interceptor de Fetch global con Javascript para detectar expiración de sesión (401) y redirigir con un modal elegante.

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

    <script>
        // [KRILLIN] Cerbero Interceptor (Fetch + XHR + jQuery)
        // Función centralizada para disparar el modal con estilo
        function triggerSessionExpiredModal() {
            // Si el modal ya existe, no hacemos nada para evitar duplicados en la pantalla
            if (document.getElementById('session-expired-modal')) return;

            const overlay = document.createElement('div');
            overlay.id = 'session-expired-modal';
            overlay.className = 'fixed inset-0 z-[9999] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in';
            overlay.innerHTML = `
                <div class="bg-white dark:bg-darkbase-900 border border-red-500/50 w-full max-w-sm rounded-3xl p-8 shadow-2xl text-center flex flex-col items-center">
                    <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-2xl flex items-center justify-center mb-4">
                        <i class="ph ph-clock-countdown text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Sesión Terminada</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Tu conexión con la base de datos caducó por seguridad. Te mandaremos al login en un momento.</p>
                    <i class="ph ph-spinner animate-spin text-3xl text-calori-600"></i>
                </div>
            `;
            document.body.appendChild(overlay);
            
            // Mandamos al usuario al logout que se encargará de matar todo
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 2500);
        }

        // CABEZA 1: Interceptor para Fetch API
        const originalFetch = window.fetch;
        window.fetch = async function() {
            try {
                const response = await originalFetch.apply(this, arguments);
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    const clone = response.clone();
                    try {
                        const data = await clone.json();
                        if (data && (data.http_code === 401 || (data.debug && data.debug.http_code === 401))) {
                            triggerSessionExpiredModal();
                            return new Promise(() => {}); // Detenemos la vista
                        }
                    } catch (e) {}
                }
                return response;
            } catch (error) {
                throw error;
            }
        };

        // CABEZA 2: Interceptor para XMLHttpRequest (Esto atrapa Axios y llamadas nativas viejas)
        const originalXhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function() {
            this.addEventListener('load', function() {
                try {
                    const contentType = this.getResponseHeader("content-type");
                    if (contentType && contentType.includes("application/json")) {
                        const data = JSON.parse(this.responseText);
                        if (data && (data.http_code === 401 || (data.debug && data.debug.http_code === 401))) {
                            triggerSessionExpiredModal();
                        }
                    }
                } catch (e) {}
            });
            originalXhrOpen.apply(this, arguments);
        };

        // CABEZA 3: Respaldo para jQuery (Atrapa DataTables y $.ajax)
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ajaxComplete(function(event, xhr, settings) {
                    try {
                        // Verificamos si la respuesta es JSON antes de parsear
                        const contentType = xhr.getResponseHeader("content-type");
                        if (contentType && contentType.includes("application/json")) {
                            const data = JSON.parse(xhr.responseText);
                            if (data && (data.http_code === 401 || (data.debug && data.debug.http_code === 401))) {
                                triggerSessionExpiredModal();
                            }
                        }
                    } catch (e) {}
                });
            }
        });
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