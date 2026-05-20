<?php
// Nombre del archivo: dashboard_growth.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-16
// Versión: 1.4
// Descripción: Se actualizó la llamada de la tarjeta "Top 10 Eventos" para usar la nueva acción 'top_events' del proxy. Se modificó el mapeo JSON para utilizar las llaves reales 'event_name' y 'total'.

// 0. Protección de ruta
require_once __DIR__ . '/includes/auth_protect.php';

// --- LÓGICA DE FECHAS DINÁMICAS Y COOKIES DE SESIÓN ---
$sessionHash = md5($_SESSION['user_token'] ?? 'default_session');
$cookieStartName = 'dash_growth_start_' . $sessionHash;
$cookieEndName = 'dash_growth_end_' . $sessionHash;

$defaultEndDate = date('Y-m-d'); 
$defaultStartDate = date('Y-m-d', strtotime('-30 days')); // Por default 30 días para ver retención

$inputStartDate = $_COOKIE[$cookieStartName] ?? $defaultStartDate;
$inputEndDate = $_COOKIE[$cookieEndName] ?? $defaultEndDate;
// -----------------------------------------------------------

// 1. Configuramos las variables para el header.php
$pageTitle = 'Retención & Growth | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

// CSS Específico
ob_start();
?>
    .submenu { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; max-height: 0; opacity: 0; overflow: hidden; }
    .submenu.open { max-height: 200px; opacity: 1; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
<?php
$extraHead = ob_get_clean();

// JS Específico: Chart.js
ob_start();
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
        Chart.register(ChartDataLabels);

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

        // Instancias de las gráficas
        let stickinessChartInst = null;
        let heatmapChartInst = null;
        let funnelChartInst = null;
        let churnChartInst = null;
        let daypartingChartInst = null;

        // Inicializador de Gráfica de Líneas
        function initLineChart(ctxId, colorStr, labelStr) {
            return new Chart(document.getElementById(ctxId).getContext('2d'), {
                type: 'line',
                data: { labels: [], datasets: [{ label: labelStr, data: [], borderColor: colorStr, backgroundColor: colorStr + '20', fill: true, tension: 0.4, borderWidth: 3 }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, datalabels: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
                }
            });
        }

        // Inicializador de Gráfica de Barras
        function initBarChart(ctxId, isHorizontal = false) {
            return new Chart(document.getElementById(ctxId).getContext('2d'), {
                type: 'bar',
                data: { labels: [], datasets: [{ data: [], backgroundColor: '#5BC85B', borderRadius: 4 }] },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'end', color: '#94a3b8', font: { size: 10, weight: 'bold' } } },
                    scales: { y: { grid: { display: false } }, x: { grid: { display: false } } }
                }
            });
        }

        // Inicializador de Dona
        function initDoughnutChart(ctxId) {
            return new Chart(document.getElementById(ctxId).getContext('2d'), {
                type: 'doughnut',
                data: { labels: [], datasets: [{ data: [], backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#8b5cf6'], borderWidth: 0 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '70%',
                    plugins: { legend: { position: 'right', labels: { color: '#9ca3af', font: { size: 11 } } }, datalabels: { display: false } }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            stickinessChartInst = initLineChart('stickinessChart', '#5BC85B', 'Usuarios Activos Diarios');
            heatmapChartInst = initLineChart('heatmapChart', '#ADD8E6', 'Sesiones por Día');
            funnelChartInst = initBarChart('funnelChart', true);
            churnChartInst = initDoughnutChart('churnChart');
            daypartingChartInst = initBarChart('daypartingChart', false);
            
            updateGrowthDashboard();
        });

        async function updateGrowthDashboard() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const btn = document.getElementById('refreshBtn');
            const icon = btn.querySelector('i');
            
            document.cookie = "<?php echo $cookieStartName; ?>=" + startDate + "; path=/; SameSite=Strict";
            document.cookie = "<?php echo $cookieEndName; ?>=" + endDate + "; path=/; SameSite=Strict";
            
            if(icon) icon.classList.add('animate-spin');
            if(btn) btn.classList.add('opacity-80', 'cursor-not-allowed');

            try {
                // 1. KPIs (Engagement Index)
                const resKpis = await fetch(`ajax_analytics.php?action=kpis&startDate=${startDate}&endDate=${endDate}`);
                const jsonKpis = await resKpis.json();
                if(jsonKpis.success && jsonKpis.data) {
                    const totalEvents = jsonKpis.data.total_events || 0;
                    const totalSessions = jsonKpis.data.total_sessions || 1; // Evitar division por 0
                    const engagementIndex = (totalEvents / totalSessions).toFixed(1);
                    
                    document.getElementById('val-engagement').innerText = engagementIndex;
                    
                    // Interpretación
                    let textEng = "Bajo compromiso";
                    if(engagementIndex > 10) textEng = "Compromiso Sano";
                    if(engagementIndex > 20) textEng = "Usuarios Fieles (Heavy Users)";
                    document.getElementById('label-engagement').innerText = textEng;
                }

                // 2. Gráfica de Stickiness (Líneas)
                const resStick = await fetch(`ajax_analytics.php?action=trends&metric=unique_users&interval=day&startDate=${startDate}&endDate=${endDate}`);
                const jsonStick = await resStick.json();
                if(jsonStick.success && jsonStick.data) {
                    stickinessChartInst.data.labels = jsonStick.data.data.map(i => i.date);
                    stickinessChartInst.data.datasets[0].data = jsonStick.data.data.map(i => i.value);
                    stickinessChartInst.update();
                }

                // 3. Mapa de Calor (Sesiones)
                const resHeat = await fetch(`ajax_analytics.php?action=trends&metric=sessions&interval=day&startDate=${startDate}&endDate=${endDate}`);
                const jsonHeat = await resHeat.json();
                if(jsonHeat.success && jsonHeat.data) {
                    heatmapChartInst.data.labels = jsonHeat.data.data.map(i => i.date);
                    heatmapChartInst.data.datasets[0].data = jsonHeat.data.data.map(i => i.value);
                    heatmapChartInst.update();
                }

                // 4. Embudo de Onboarding & Tasa de Conversión a Premium
                const resDist = await fetch(`ajax_analytics.php?action=distribution&groupBy=screen_name&startDate=${startDate}&endDate=${endDate}`);
                const jsonDist = await resDist.json();
                
                let paywallViews = 0;
                // Iniciamos los valores en 0 por si Hermes no los manda (evita que la barra desaparezca)
                let fValues = { 'WelcomeView': 0, 'OnboardingQuestionsView': 0, 'HomeView': 0, 'PaywallView': 0 };

                if(jsonDist.success && jsonDist.data && jsonDist.data.data) {
                    jsonDist.data.data.forEach(item => {
                        // Si el nombre de la pantalla nos interesa, actualizamos su valor a lo que mande la base de datos
                        if (fValues[item.name] !== undefined) fValues[item.name] = item.count;
                        if (item.name === 'PaywallView') paywallViews = item.count;
                    });
                }
                
                let funnelData = [
                    {label: 'Welcome', val: fValues['WelcomeView']},
                    {label: 'Preguntas', val: fValues['OnboardingQuestionsView']},
                    {label: 'Home', val: fValues['HomeView']},
                    {label: 'Paywall', val: fValues['PaywallView']}
                ];
                
                // Pintamos el embudo (con 0s incluidos si es que no hubo visitas)
                funnelChartInst.data.labels = funnelData.map(f => f.label);
                funnelChartInst.data.datasets[0].data = funnelData.map(f => f.val);
                funnelChartInst.data.datasets[0].backgroundColor = ['#ADD8E6', '#98FB98', '#5BC85B', '#FFB6C1'];
                funnelChartInst.update();

                // Compras reales vs Paywall para la Tasa de Conversión
                const resPurchases = await fetch(`ajax_analytics.php?action=trends_events&eventName=subscription_purchased&interval=day&startDate=${startDate}&endDate=${endDate}`);
                const jsonPurchases = await resPurchases.json();
                if(jsonPurchases.success && jsonPurchases.data) {
                    const totalPurchases = jsonPurchases.data.data.reduce((sum, item) => sum + parseInt(item.value), 0);
                    
                    let conversionRate = 0;
                    if(paywallViews > 0) {
                        conversionRate = ((totalPurchases / paywallViews) * 100).toFixed(2);
                    }
                    
                    document.getElementById('val-conversion').innerText = conversionRate + '%';
                    document.getElementById('val-paywall').innerText = paywallViews.toLocaleString();
                    document.getElementById('val-purchases').innerText = totalPurchases.toLocaleString();
                }

                // 5. Análisis de Churn Risk (account_deleted -> reason)
                const resChurn = await fetch(`ajax_analytics.php?action=metadata_distribution&eventName=account_deleted&metadataKey=reason&startDate=${startDate}&endDate=${endDate}`);
                const jsonChurn = await resChurn.json();
                if(jsonChurn.success && jsonChurn.data && jsonChurn.data.data) {
                    // Si el arreglo viene vacío, significa que NO hubo bajas con razones registradas
                    if(jsonChurn.data.data.length === 0) {
                        churnChartInst.data.labels = ['Sin bajas en este periodo'];
                        churnChartInst.data.datasets[0].data = [1]; // Valor falso solo para pintar el círculo
                        churnChartInst.data.datasets[0].backgroundColor = ['#e5e7eb']; // Color gris inactivo
                        churnChartInst.update();
                    } else {
                        churnChartInst.data.labels = jsonChurn.data.data.map(i => i.name);
                        churnChartInst.data.datasets[0].data = jsonChurn.data.data.map(i => i.count);
                        churnChartInst.data.datasets[0].backgroundColor = ['#ef4444', '#f97316', '#f59e0b', '#8b5cf6'];
                        churnChartInst.update();
                    }
                }

                // 6. Dayparting (Endpoint actualmente no disponible en Hermes, devuelve 404)
                const resDay = await fetch(`ajax_analytics.php?action=dayparting&startDate=${startDate}&endDate=${endDate}`);
                const jsonDay = await resDay.json();
                if(jsonDay.success && jsonDay.data) {
                    const keys = Object.keys(jsonDay.data);
                    daypartingChartInst.data.labels = keys;
                    daypartingChartInst.data.datasets[0].data = keys.map(k => jsonDay.data[k]);
                    daypartingChartInst.data.datasets[0].backgroundColor = '#FFD700';
                    daypartingChartInst.update();
                } else {
                    // Manejo del error 404 elegantemente
                    daypartingChartInst.data.labels = ['Sin Data'];
                    daypartingChartInst.data.datasets[0].data = [0];
                    daypartingChartInst.update();
                }

                // 7. Top 10 Eventos (Consumiendo el NUEVO endpoint de Hermes: /admin/analytics/events/top)
                const resTop = await fetch(`ajax_analytics.php?action=top_events&startDate=${startDate}&endDate=${endDate}`);
                const jsonTop = await resTop.json();
                const topList = document.getElementById('topEventsList');
                
                if(jsonTop.success && jsonTop.data && jsonTop.data.data) {
                    let eventsArray = jsonTop.data.data;
                    
                    // Ordenamos de mayor a menor y limitamos a 10 por seguridad, aunque Hermes ya lo mande ordenado
                    eventsArray.sort((a, b) => b.total - a.total);
                    let top10 = eventsArray.slice(0, 10);
                    
                    let htmlTop = '';
                    top10.forEach((ev, index) => {
                        htmlTop += `
                            <div class="flex items-center justify-between p-3 bg-white/40 dark:bg-darkbase-900/40 border border-white/20 dark:border-gray-800 rounded-xl shadow-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-800 mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-calori-50 dark:bg-calori-900/30 text-calori-600 flex items-center justify-center font-bold text-xs border border-calori-100 dark:border-calori-800 shrink-0">
                                        #${index + 1}
                                    </div>
                                    <span class="text-sm font-bold text-gray-700 dark:text-gray-300 font-mono truncate">${ev.event_name}</span>
                                </div>
                                <span class="text-sm font-black text-gray-900 dark:text-white ml-2">${Number(ev.total).toLocaleString()}</span>
                            </div>
                        `;
                    });
                    
                    topList.innerHTML = htmlTop || '<p class="text-sm text-gray-500 text-center mt-4">Sin datos en este periodo.</p>';
                } else {
                    topList.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-sm text-gray-400 font-medium">Error al cargar el Top de eventos.</p></div>';
                }

            } catch (error) {
                console.error("Error al procesar las analíticas de Growth:", error);
            } finally {
                if(icon) icon.classList.remove('animate-spin');
                if(btn) btn.classList.remove('opacity-80', 'cursor-not-allowed');
            }
        }
    </script>
<?php
$extraScripts = ob_get_clean();

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
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight transition-colors">Retención y Crecimiento</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1 transition-colors">Análisis profundo del ciclo de vida, engagement y churn.</p>
                </div>
                
                <div class="flex items-center gap-3 bg-white/50 dark:bg-darkbase-800/50 backdrop-blur-md p-2 rounded-2xl border border-white/40 dark:border-gray-700 shadow-sm transition-colors">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 mb-0.5">Inicio</span>
                        <input type="date" id="startDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="<?php echo htmlspecialchars($inputStartDate); ?>">
                    </div>
                    <div class="w-px h-8 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 mb-0.5">Fin</span>
                        <input type="date" id="endDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="<?php echo htmlspecialchars($inputEndDate); ?>">
                    </div>
                    <button id="refreshBtn" onclick="updateGrowthDashboard()" class="ml-2 p-2.5 bg-calori-600 text-white rounded-xl hover:bg-calori-700 transition-colors shadow-lg shadow-calori-600/20">
                        <i class="ph ph-arrows-clockwise font-bold"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm overflow-visible">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="group relative inline-block cursor-help z-20">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 border-b border-dashed border-gray-400 pb-0.5">Profundidad de Sesión (Engagement Index)</p>
                                <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                    <strong>Fórmula:</strong><br> Total de Eventos / Total de Sesiones
                                    <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                                </div>
                            </div>
                            <div class="flex items-end gap-3 mt-1">
                                <h3 id="val-engagement" class="text-4xl font-bold text-calori-600 dark:text-calori-500">--</h3>
                                <span class="text-sm font-medium text-gray-400 mb-1">Eventos / Sesión</span>
                            </div>
                            <p id="label-engagement" class="text-xs font-bold uppercase tracking-wider text-green-500 mt-2">Calculando...</p>
                        </div>
                        <div class="p-3 bg-calori-50 dark:bg-calori-900/20 text-calori-600 rounded-xl shrink-0">
                            <i class="ph ph-activity text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm overflow-visible">
                    <div class="flex justify-between items-start">
                        <div class="w-full">
                            <div class="group relative inline-block cursor-help z-20">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 border-b border-dashed border-gray-400 pb-0.5">Tasa de Conversión a Premium</p>
                                <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                    <strong>Fórmula:</strong><br> (Eventos 'subscription_purchased' / Visitas a 'PaywallView') * 100
                                    <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                                </div>
                            </div>
                            <div class="flex items-end gap-3 mb-2 mt-1">
                                <h3 id="val-conversion" class="text-4xl font-bold text-purple-600 dark:text-purple-500">--%</h3>
                            </div>
                            <div class="flex justify-between w-full text-xs text-gray-500 dark:text-gray-400 mt-2 bg-gray-50 dark:bg-gray-800/50 p-2 rounded-lg border border-gray-100 dark:border-gray-700">
                                <span><strong id="val-paywall" class="text-gray-800 dark:text-gray-200">--</strong> Visitas a Paywall</span>
                                <span><strong id="val-purchases" class="text-purple-600 dark:text-purple-400">--</strong> Compras Reales</span>
                            </div>
                        </div>
                        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 text-purple-600 rounded-xl ml-4 shrink-0">
                            <i class="ph ph-crown text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <div class="group relative inline-block cursor-help z-20">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Stickiness (Adherencia)</h2>
                            <p class="text-xs text-gray-500 mt-1">Tracción diaria de usuarios únicos.</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Endpoint /trends?metric=unique_users evaluado por día.
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                    </div>
                    <div class="h-64 w-full">
                        <canvas id="stickinessChart"></canvas>
                    </div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <div class="group relative inline-block cursor-help z-20">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Mapa de Calor de Uso</h2>
                            <p class="text-xs text-gray-500 mt-1">Distribución de sesiones a lo largo de los días.</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Endpoint /trends?metric=sessions evaluado por día.
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                    </div>
                    <div class="h-64 w-full">
                        <canvas id="heatmapChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <div class="group relative inline-block cursor-help z-20">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Embudo de Onboarding</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Identificación de Bloqueos</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Distribución de screen_name (Welcome, OnboardingQuestions, Home, Paywall).
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                        <i class="ph ph-funnel text-xl text-blue-500 shrink-0"></i>
                    </div>
                    <div class="h-56 w-full relative"><canvas id="funnelChart"></canvas></div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <div class="group relative inline-block cursor-help z-20">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Análisis de Abandono</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Razones de Baja (Churn)</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Conteo del evento 'account_deleted' agrupado por la llave metadata 'reason'.
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                        <i class="ph ph-user-minus text-xl text-red-500 shrink-0"></i>
                    </div>
                    <div class="h-56 w-full relative"><canvas id="churnChart"></canvas></div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <div class="group relative inline-block cursor-help z-20">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Franja Horaria</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Madrugadores vs Noctámbulos</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Sesiones agrupadas por la hora del día en que iniciaron (cruce de started_at y timezone).
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                        <i class="ph ph-clock text-xl text-yellow-500 shrink-0"></i>
                    </div>
                    <div class="h-56 w-full relative"><canvas id="daypartingChart"></canvas></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col h-[400px]">
                    <div class="flex justify-between items-center mb-4 shrink-0">
                        <div class="group relative inline-block cursor-help z-20">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white border-b border-dashed border-gray-400 pb-0.5 w-fit">Top 10 Eventos</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Las interacciones más recurrentes</p>
                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 font-medium pointer-events-none">
                                <strong>Fórmula:</strong><br> Endpoint /analytics/events/top (Conteo total ordenado descendente).
                                <div class="absolute top-full left-4 border-8 border-transparent border-t-gray-900 dark:border-t-gray-100"></div>
                            </div>
                        </div>
                        <div class="p-2 bg-calori-50 dark:bg-calori-900/30 text-calori-600 rounded-xl shrink-0">
                            <i class="ph ph-list-numbers text-xl"></i>
                        </div>
                    </div>
                    
                    <div id="topEventsList" class="flex-1 overflow-y-auto pr-2">
                        <div class="animate-pulse space-y-2">
                            <div class="h-12 bg-gray-100 dark:bg-gray-800 rounded-xl"></div>
                            <div class="h-12 bg-gray-100 dark:bg-gray-800 rounded-xl"></div>
                            <div class="h-12 bg-gray-100 dark:bg-gray-800 rounded-xl"></div>
                            <div class="h-12 bg-gray-100 dark:bg-gray-800 rounded-xl"></div>
                        </div>
                    </div>
                </div>
                <div class="hidden lg:block"></div>
            </div>

        </div>
    </main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>