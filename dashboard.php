<?php
// Nombre del archivo: dashboard.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.1
// Descripción: Vista principal del Centro de Mando blindada con el middleware de protección de sesiones (auth_protect).

// 0. Protección de ruta (¡Debe ser lo primero!)
require_once __DIR__ . '/includes/auth_protect.php';

// 1. Configuramos las variables para el header.php
$pageTitle = 'Dashboard | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

// CSS Específico del Dashboard
ob_start();
?>
    /* Transición suave para los submenús */
    .submenu {
        transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
        max-height: 0;
        opacity: 0;
        overflow: hidden;
    }
    .submenu.open {
        max-height: 200px; /* Suficiente para el contenido */
        opacity: 1;
    }
    /* Scrollbar estilizado para el panel lateral */
    ::-webkit-scrollbar {
        width: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
<?php
$extraHead = ob_get_clean();

// JS Específico del Dashboard
ob_start();
?>
    <script>
        // Función simple para alternar los submenús
        function toggleSubmenu(submenuId, iconId) {
            const submenu = document.getElementById(submenuId);
            const icon = document.getElementById(iconId);
            
            if (submenu.classList.contains('open')) {
                submenu.classList.remove('open');
                icon.classList.remove('rotate-180');
            } else {
                submenu.classList.add('open');
                icon.classList.add('rotate-180');
            }
        }
    </script>
<?php
$extraScripts = ob_get_clean();

// 2. Incluimos Header Global
require_once __DIR__ . '/includes/header.php';
?>

    <div class="absolute top-[-10%] left-[20%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[10%] w-[500px] h-[500px] bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none" style="animation-delay: 2s;"></div>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden z-10 relative">
        
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-y-auto p-8">
            
            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Bienvenido de vuelta, Arturo.</h1>
                    <p class="text-gray-500 mt-1">Aquí tienes el resumen del ecosistema Caloritrack de hoy.</p>
                </div>
                <button class="flex items-center px-4 py-2 bg-calori-600 text-white text-sm font-semibold rounded-xl hover:bg-calori-700 transition-colors shadow-sm">
                    <i class="ph ph-plus mr-2"></i> Nuevo Reporte
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white/70 backdrop-blur-lg border border-white/40 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Usuarios Activos (Diarios)</p>
                            <h3 class="text-3xl font-bold text-gray-900">24,592</h3>
                        </div>
                        <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <i class="ph ph-users-three text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm">
                        <i class="ph ph-trend-up text-green-500 mr-1"></i>
                        <span class="text-green-600 font-medium">12.5%</span>
                        <span class="text-gray-400 ml-2">vs semana anterior</span>
                    </div>
                </div>

                <div class="bg-white/70 backdrop-blur-lg border border-white/40 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Sincronizaciones Apple Health</p>
                            <h3 class="text-3xl font-bold text-gray-900">18,204</h3>
                        </div>
                        <div class="p-2 bg-calori-50 text-calori-600 rounded-lg">
                            <i class="ph ph-heartbeat text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm">
                        <i class="ph ph-trend-up text-green-500 mr-1"></i>
                        <span class="text-green-600 font-medium">8.2%</span>
                        <span class="text-gray-400 ml-2">hoy</span>
                    </div>
                </div>

                <div class="bg-white/70 backdrop-blur-lg border border-white/40 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Estado Kai (IA)</p>
                            <h3 class="text-3xl font-bold text-gray-900">Óptimo</h3>
                        </div>
                        <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                            <i class="ph ph-brain text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm">
                        <span class="flex w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-gray-500">Correlaciones activas</span>
                    </div>
                </div>

                <div class="bg-white/70 backdrop-blur-lg border border-white/40 p-6 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Uptime de Servidores</p>
                            <h3 class="text-3xl font-bold text-gray-900">99.99%</h3>
                        </div>
                        <div class="p-2 bg-gray-100 text-gray-600 rounded-lg">
                            <i class="ph ph-hard-drives text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm">
                        <span class="text-gray-500">Heimdall & Hermes reportando</span>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white/70 backdrop-blur-lg border border-white/40 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-lg font-bold text-gray-900">Adopción de Pilares de Salud</h2>
                        <button class="text-gray-400 hover:text-gray-600"><i class="ph ph-dots-three text-xl"></i></button>
                    </div>
                    <div class="h-64 w-full border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                        <i class="ph ph-chart-line-up text-4xl mb-2 text-calori-300"></i>
                        <p class="text-sm font-medium">Área reservada para gráfico de datos interactivo</p>
                        <p class="text-xs text-gray-400 mt-1">(Ej. Librería Chart.js o D3)</p>
                    </div>
                </div>

                <div class="bg-white/70 backdrop-blur-lg border border-white/40 rounded-2xl p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">Actividad del Equipo</h2>
                    <div class="space-y-6">
                        <div class="flex">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs">OR</div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Orion <span class="font-normal text-gray-500">subió una nueva build de iOS.</span></p>
                                <p class="text-xs text-gray-400 mt-1">Hace 2 horas</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-xs">GE</div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Genesis <span class="font-normal text-gray-500">aprobó arquitectura del módulo de sueño.</span></p>
                                <p class="text-xs text-gray-400 mt-1">Hace 4 horas</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-xs">EM</div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Empath <span class="font-normal text-gray-500">actualizó componentes UI.</span></p>
                                <p class="text-xs text-gray-400 mt-1">Hace 5 horas</p>
                            </div>
                        </div>
                    </div>
                    <button class="w-full mt-6 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Ver todo el registro
                    </button>
                </div>
            </div>

        </div>
    </main>

<?php
// 5. Incluimos Footer Global
require_once __DIR__ . '/includes/footer.php';
?>