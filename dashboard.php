<?php
// Nombre del archivo: dashboard.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-09
// Versión: 1.12
// Descripción: Panel de Analíticas Globales. Se reestructuró la cuadrícula inferior a 3 columnas para añadir la tarjeta de Membresías Activas dinámica y dejar la gráfica en 2 columnas. Conserva el 100% de la funcionalidad base. Se implementó un parche de seguridad en el parseo del JSON para evitar iterar sobre metadatos (success/data) y extraer correctamente los planes (Free, Familiar, Premium).

// 0. Protección de ruta (¡Debe ser lo primero!)
require_once __DIR__ . '/includes/auth_protect.php';

// 1. Configuramos las variables para el header.php
$pageTitle = 'Analíticas Globales | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

// CSS Específico del Dashboard y Analíticas
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
        max-height: 200px;
        opacity: 1;
    }
    /* Scrollbar estilizado */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Estilos para inputs de fecha */
    .date-filter-input {
        @apply bg-white/60 border border-gray-200 rounded-xl px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all;
    }
<?php
$extraHead = ob_get_clean();

// JS Específico: Chart.js y Lógica de Filtros Asíncrona
ob_start();
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Función para alternar submenús
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

        // Variable global para mantener la instancia de la gráfica y poder actualizarla
        let trendsChartInstance = null;

        // Inicialización de Gráfica de Tendencias (Fase 1)
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            trendsChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                  labels: [], // Se llenará dinámicamente
                    datasets: [{
                        label: 'Usuarios Únicos',
                        data: [], // Se llenará dinámicamente
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#16a34a',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { display: false } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Cargar datos reales al iniciar la pantalla
            updateAnalytics();
        });

        // Función Maestra para consumir nuestro proxy y popular la vista
        async function updateAnalytics() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const btn = document.getElementById('refreshBtn');
            const icon = btn.querySelector('i');

            // Animación de carga visual
            if(icon) icon.classList.add('animate-spin');
            if(btn) btn.classList.add('opacity-80', 'cursor-not-allowed');

            try {
                // 1. Extraer KPIs Globales
                const resKpis = await fetch(`ajax_analytics.php?action=kpis&startDate=${startDate}&endDate=${endDate}`);
                const jsonKpis = await resKpis.json();
                
                if(jsonKpis.success && jsonKpis.data) {
                    document.getElementById('val-dau').innerText = jsonKpis.data.unique_users.toLocaleString();
                    document.getElementById('val-sessions').innerText = jsonKpis.data.total_sessions.toLocaleString();
                    document.getElementById('val-events').innerText = jsonKpis.data.total_events.toLocaleString();
                    
                    const avgMinutesRaw = parseFloat(jsonKpis.data.avg_session_duration_minutes);
                    if (!isNaN(avgMinutesRaw)) {
                        const mins = Math.floor(avgMinutesRaw);
                        const secs = Math.round((avgMinutesRaw - mins) * 60);
                        document.getElementById('val-duration').innerText = mins + 'm ' + secs + 's';
                    } else {
                        document.getElementById('val-duration').innerText = '0m 0s';
                    }
                }

                // 2. Extraer Tendencias Temporales (Gráfica)
                const resTrends = await fetch(`ajax_analytics.php?action=trends&metric=unique_users&interval=day&startDate=${startDate}&endDate=${endDate}`);
                const jsonTrends = await resTrends.json();
                
                if(jsonTrends.success && jsonTrends.data) {
                    const labels = jsonTrends.data.data.map(item => item.date);
                    const values = jsonTrends.data.data.map(item => item.value);
                    
                    if(trendsChartInstance) {
                        trendsChartInstance.data.labels = labels;
                        trendsChartInstance.data.datasets[0].data = values;
                        trendsChartInstance.update();
                    }
                }

                // 3. Extraer Distribución de Pantallas (Navegación)
                const resDist = await fetch(`ajax_analytics.php?action=distribution&groupBy=screen_name&startDate=${startDate}&endDate=${endDate}`);
                const jsonDist = await resDist.json();

                if(jsonDist.success && jsonDist.data && jsonDist.data.data) {
                    let fastingCount = 0;
                    let kaiCount = 0;
                    let nutritionCount = 0;
                    let wellnessCount = 0;

                    jsonDist.data.data.forEach(item => {
                        if (item.name === 'FastingView') fastingCount = item.count;
                        if (item.name === 'AICoachView') kaiCount = item.count;
                        if (item.name === 'NutritionView') nutritionCount = item.count;
                        if (item.name === 'WellnessView') wellnessCount = item.count;
                    });

                    document.getElementById('val-nav-fasting').innerText = fastingCount.toLocaleString();
                    document.getElementById('val-nav-kai').innerText = kaiCount.toLocaleString();
                    document.getElementById('val-nav-nutrition').innerText = nutritionCount.toLocaleString();
                    document.getElementById('val-nav-wellness').innerText = wellnessCount.toLocaleString();
                }

                // 4. Extraer Membresías Activas (Nuevo endpoint)
                const resSubs = await fetch(`ajax_analytics.php?action=subscriptions`);
                const jsonSubs = await resSubs.json();
                const subsList = document.getElementById('subscriptionsList');
                
                if(jsonSubs.success && jsonSubs.data) {
                    // CORRECCIÓN DEFINITIVA: Lógica a prueba de balas para extraer el JSON
                    let dataPlanes = {};
                    
                    if (jsonSubs.data.success !== undefined && jsonSubs.data.data !== undefined) {
                        // Caso 1: Doble envoltura (Proxy envuelve la respuesta de Hermes)
                        dataPlanes = jsonSubs.data.data;
                    } else {
                        // Caso 2: Envoltura simple (Proxy manda la respuesta directa de Hermes)
                        dataPlanes = jsonSubs.data;
                    }
                    
                    // Configuración visual estricta para Free, Familiar y Premium
                    const config = {
                        'Free': { color: 'text-gray-500 dark:text-gray-400', bg: 'bg-gray-100 dark:bg-gray-800', icon: 'ph-leaf', label: 'Plan Básico' },
                        'Familiar': { color: 'text-purple-500', bg: 'bg-purple-50 dark:bg-purple-900/30', icon: 'ph-users', label: 'Suscripción Grupal' },
                        'Premium': { color: 'text-calori-600', bg: 'bg-calori-50 dark:bg-calori-900/30', icon: 'ph-crown', label: 'Máximo Potencial' }
                    };

                    let html = '';
                    
                    // Aseguramos iterar sobre las llaves correctas
                    Object.keys(dataPlanes).forEach(key => {
                        // SEGURO ANTI-FALLAS: Ignorar si la llave se llama success, data o pagination
                        if (key === 'success' || key === 'data' || key === 'pagination') return;

                        const style = config[key] || { color: 'text-green-500', bg: 'bg-green-50', icon: 'ph-star', label: 'Plan' };
                        html += `
                            <div class="flex items-center justify-between p-4 bg-white/40 dark:bg-darkbase-900/40 border border-white/20 dark:border-gray-800 rounded-2xl transition-all hover:translate-x-1 shadow-sm mb-3">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl ${style.bg} flex items-center justify-center ${style.color} shadow-inner">
                                        <i class="ph ${style.icon} text-2xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 dark:text-white leading-tight">${key}</h4>
                                        <p class="text-[10px] font-black uppercase tracking-widest opacity-40">${style.label}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-black text-gray-900 dark:text-white">${Number(dataPlanes[key]).toLocaleString()}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    if(html === '') html = '<p class="text-sm text-gray-500">Sin membresías por mostrar.</p>';
                    subsList.innerHTML = html;
                } else {
                    subsList.innerHTML = '<p class="text-sm text-red-500">Error al cargar datos.</p>';
                }

            } catch (error) {
                console.error("Error al procesar las analíticas:", error);
            } finally {
                if(icon) icon.classList.remove('animate-spin');
                if(btn) btn.classList.remove('opacity-80', 'cursor-not-allowed');
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
            
            <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight transition-colors">Analíticas de Salud</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1 transition-colors">Monitoreo en tiempo real del ecosistema CaloriTrack.</p>
                </div>
                
                <div class="flex items-center gap-3 bg-white/50 dark:bg-darkbase-800/50 backdrop-blur-md p-2 rounded-2xl border border-white/40 dark:border-gray-700 shadow-sm transition-colors">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 mb-0.5">Inicio</span>
                        <input type="date" id="startDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="2026-05-01">
                    </div>
                
                    <div class="w-px h-8 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 mb-0.5">Fin</span>
                        <input type="date" id="endDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="2026-05-08">
                    </div>
                    <button id="refreshBtn" onclick="updateAnalytics()" class="ml-2 p-2.5 bg-calori-600 text-white rounded-xl hover:bg-calori-700 transition-colors shadow-lg shadow-calori-600/20">
                        <i class="ph ph-arrows-clockwise font-bold"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Usuarios Activos (DAU)</p>
                            <h3 id="val-dau" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                        </div>
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg">
                            <i class="ph ph-users-three text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Sesiones Totales</p>
                            <h3 id="val-sessions" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                        </div>
                        <div class="p-2 bg-calori-50 dark:bg-calori-900/20 text-calori-600 dark:text-calori-400 rounded-lg">
                            <i class="ph ph-fingerprint text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Interacciones (Eventos)</p>
                            <h3 id="val-events" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                        </div>
                        <div class="p-2 bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded-lg">
                            <i class="ph ph-cursor-click text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Promedio de Sesión</p>
                            <h3 id="val-duration" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                        </div>
                        <div class="p-2 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 rounded-lg">
                            <i class="ph ph-clock text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 px-1">Radar de Adopción (Visitas por Pantalla)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Zona de Ayuno</p>
                                <h3 id="val-nav-fasting" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                            </div>
                            <div class="p-2 bg-calori-50 dark:bg-calori-900/20 text-calori-600 dark:text-calori-400 rounded-lg">
                                <i class="ph ph-timer text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Conexión con KAI (IA)</p>
                                <h3 id="val-nav-kai" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                            </div>
                            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded-lg">
                                <i class="ph ph-brain text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Radar Nutricional</p>
                                <h3 id="val-nav-nutrition" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                            </div>
                            <div class="p-2 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-lg">
                                <i class="ph ph-apple-logo text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Oasis Wellness</p>
                                <h3 id="val-nav-wellness" class="text-3xl font-bold text-gray-900 dark:text-white">--</h3>
                            </div>
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg">
                                <i class="ph ph-drop text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tendencias de Adopción</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Evolución cronológica de usuarios únicos y sesiones.</p>
                        </div>
                    </div>
                    <div class="h-80 w-full">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>

                <div class="lg:col-span-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Membresías</h2>
                            <p class="text-xs text-gray-500 font-medium">Distribución por plan actual</p>
                        </div>
                        <div class="p-3 bg-calori-50 dark:bg-calori-900/30 text-calori-600 rounded-xl">
                            <i class="ph ph-identification-card text-2xl"></i>
                        </div>
                    </div>
                    
                    <div id="subscriptionsList" class="space-y-4 flex-1 overflow-y-auto pr-2">
                        <div class="animate-pulse space-y-4">
                            <div class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
                            <div class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
                            <div class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>