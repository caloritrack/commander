<?php
// Nombre del archivo: dashboard.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-09
// Versión: 1.20
// Descripción: Panel de Analíticas Globales. Se implementó un algoritmo de ordenamiento (sort) dinámico para las gráficas de Idiomas y Regiones, garantizando que los datos se muestren de mayor a menor volumen (con el mayor hasta arriba) para facilitar la lectura analítica. El resto de la funcionalidad (mapas, fechas, cookies y UI) permanece intacta.

// 0. Protección de ruta (¡Debe ser lo primero!)
require_once __DIR__ . '/includes/auth_protect.php';

// --- LÓGICA DE FECHAS DINÁMICAS Y COOKIES DE SESIÓN ---
$sessionHash = md5($_SESSION['user_token'] ?? 'default_session');
$cookieStartName = 'dash_start_' . $sessionHash;
$cookieEndName = 'dash_end_' . $sessionHash;

$defaultEndDate = date('Y-m-d'); 
$defaultStartDate = date('Y-m-d', strtotime('-7 days')); 

$inputStartDate = $_COOKIE[$cookieStartName] ?? $defaultStartDate;
$inputEndDate = $_COOKIE[$cookieEndName] ?? $defaultEndDate;
// -----------------------------------------------------------

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

// JS Específico: Chart.js, Google Charts y Lógica Asíncrona
ob_start();
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script>
        // Registrar plugin de etiquetas de datos globalmente
        Chart.register(ChartDataLabels);

        // Inicializamos Google Charts
        let googleChartsReady = false;
        google.charts.load('current', { 'packages': ['geochart'] });
        google.charts.setOnLoadCallback(() => {
            googleChartsReady = true;
        });

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

        // Variables globales para mantener las instancias de las gráficas
        let trendsChartInstance = null;
        let mealPlansChartInstance = null;
        let genderChartInstance = null;
        let imcChartInstance = null;
        let ageChartInstance = null;
        let languageChartInstance = null;
        let regionChartInstance = null;
        let lastTimezonesData = null; // Guardamos los datos de timezone por si google charts carga un microsegundo después

        // Plugin personalizado para dibujar el Total en el centro de las Donas
        const centerTextPlugin = {
            id: 'centerText',
            afterDraw: (chart) => {
                if (chart.config.type !== 'doughnut') return;
                const { ctx, chartArea: { top, width, height } } = chart;
                ctx.save();
                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                
                ctx.font = 'bold 10px sans-serif';
                ctx.fillStyle = '#94a3b8'; 
                ctx.textAlign = 'center';
                const centerX = chart.getDatasetMeta(0).data.length > 0 ? chart.getDatasetMeta(0).data[0].x : width / 2;
                
                ctx.fillText('TOTAL', centerX, top + (height / 2) - 10);
                
                ctx.font = 'bold 20px sans-serif';
                ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#ffffff' : '#1e293b';
                ctx.fillText(total.toLocaleString(), centerX, top + (height / 2) + 15);
                ctx.restore();
            }
        };

        // Inicialización de Gráficas de Donas
        function initDoughnutChart(ctxId) {
            return new Chart(document.getElementById(ctxId).getContext('2d'), {
                type: 'doughnut',
                plugins: [centerTextPlugin],
                data: { labels: [], datasets: [{ data: [], backgroundColor: [], borderWidth: 2, borderColor: '#ffffff' }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    plugins: {
                        legend: { 
                            position: 'right', 
                            align: 'center',
                            labels: { 
                                boxWidth: 12, 
                                padding: 15,
                                color: '#9ca3af', 
                                font: { size: 11, weight: 'bold' },
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map(function(label, i) {
                                            const meta = chart.getDatasetMeta(0);
                                            const style = meta.controller.getStyle(i);
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                            
                                            return {
                                                text: `${label}: ${value} (${percentage}%)`,
                                                fillStyle: style.backgroundColor,
                                                strokeStyle: style.borderColor,
                                                lineWidth: style.borderWidth,
                                                hidden: isNaN(value) || meta.data[i].hidden,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            } 
                        },
                        datalabels: { display: false },
                        tooltip: { enabled: true }
                    },
                    cutout: '75%'
                }
            });
        }

        // Inicialización de Gráficas de Barras
        function initBarChart(ctxId, isHorizontal = false) {
            return new Chart(document.getElementById(ctxId).getContext('2d'), {
                type: 'bar',
                data: { labels: [], datasets: [{ data: [], backgroundColor: '#22c55e', borderRadius: 4 }] },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'end',
                            color: '#94a3b8',
                            font: { size: 10, weight: 'bold' },
                            formatter: (val) => val.toLocaleString()
                        }
                    },
                    scales: {
                        y: { grid: { display: false }, ticks: { color: '#9ca3af', font: {size: 10} } },
                        x: { grid: { display: false }, ticks: { color: '#9ca3af', font: {size: 10} } }
                    }
                }
            });
        }

        // Dibuja los Mapas de Calor Parseando Timezones a Regiones ISO
        function drawMaps(timezones) {
            if (!googleChartsReady) {
                // Si google charts aún no carga, intentamos en 500ms
                setTimeout(() => drawMaps(timezones), 500);
                return;
            }

            let worldData = [['Country', 'Usuarios']];
            let mxData = [['Estado', 'Usuarios']];
            let arData = [['Provincia', 'Usuarios']];

            let countryCounts = {};
            let mxCounts = {};
            let arCounts = {};

            // Mapeo inteligente de Timezones a Códigos ISO-3166-2
            timezones.forEach(t => {
                let tz = t.timezone;
                let qty = t.total;

                // MÉXICO
                if (tz.includes('Mexico') || tz.includes('Cancun') || tz.includes('Monterrey') || tz.includes('Mazatlan') || tz.includes('Merida') || tz.includes('Tijuana') || tz.includes('Chihuahua') || tz.includes('Hermosillo')) {
                    countryCounts['MX'] = (countryCounts['MX'] || 0) + qty;
                    
                    if (tz.includes('Cancun')) mxCounts['MX-ROO'] = (mxCounts['MX-ROO'] || 0) + qty; // Quintana Roo
                    else if (tz.includes('Merida')) mxCounts['MX-YUC'] = (mxCounts['MX-YUC'] || 0) + qty; // Yucatán
                    else if (tz.includes('Monterrey')) mxCounts['MX-NLE'] = (mxCounts['MX-NLE'] || 0) + qty; // Nuevo León
                    else if (tz.includes('Mazatlan')) mxCounts['MX-SIN'] = (mxCounts['MX-SIN'] || 0) + qty; // Sinaloa
                    else if (tz.includes('Tijuana')) mxCounts['MX-BCN'] = (mxCounts['MX-BCN'] || 0) + qty; // Baja California
                    else if (tz.includes('Hermosillo')) mxCounts['MX-SON'] = (mxCounts['MX-SON'] || 0) + qty; // Sonora
                    else if (tz.includes('Chihuahua')) mxCounts['MX-CHH'] = (mxCounts['MX-CHH'] || 0) + qty; // Chihuahua
                    else mxCounts['MX-DIF'] = (mxCounts['MX-DIF'] || 0) + qty; // Google GeoCharts usa MX-DIF (Distrito Federal) para la CDMX
                }
                // ARGENTINA
                else if (tz.includes('Argentina')) {
                    countryCounts['AR'] = (countryCounts['AR'] || 0) + qty;
                    
                    if (tz.includes('Buenos_Aires')) arCounts['AR-B'] = (arCounts['AR-B'] || 0) + qty; // Prov. Buenos Aires
                    else if (tz.includes('Cordoba')) arCounts['AR-X'] = (arCounts['AR-X'] || 0) + qty; // Córdoba
                    else if (tz.includes('Mendoza')) arCounts['AR-M'] = (arCounts['AR-M'] || 0) + qty; // Mendoza
                    else arCounts['AR-C'] = (arCounts['AR-C'] || 0) + qty; // Por defecto CABA
                }
                // USA y OTROS
                else if (tz.includes('Los_Angeles') || tz.includes('New_York') || tz.includes('Chicago') || tz.includes('Denver')) {
                    countryCounts['US'] = (countryCounts['US'] || 0) + qty;
                }
                else if (tz.includes('Madrid')) countryCounts['ES'] = (countryCounts['ES'] || 0) + qty;
                else if (tz.includes('Paris')) countryCounts['FR'] = (countryCounts['FR'] || 0) + qty;
                else if (tz.includes('Dublin')) countryCounts['IE'] = (countryCounts['IE'] || 0) + qty;
                else if (tz.includes('London')) countryCounts['GB'] = (countryCounts['GB'] || 0) + qty;
            });

            // Convertimos objetos a Arrays para Google Charts
            Object.keys(countryCounts).forEach(k => worldData.push([k, countryCounts[k]]));
            
            // Mapas formateados: Le damos el valor y el texto legible para que el Tooltip sea elegante
            Object.keys(mxCounts).forEach(k => {
                let stateName = k;
                if (k === 'MX-DIF') stateName = 'Ciudad de México';
                if (k === 'MX-ROO') stateName = 'Quintana Roo';
                if (k === 'MX-YUC') stateName = 'Yucatán';
                if (k === 'MX-NLE') stateName = 'Nuevo León';
                if (k === 'MX-SIN') stateName = 'Sinaloa';
                if (k === 'MX-BCN') stateName = 'Baja California';
                if (k === 'MX-SON') stateName = 'Sonora';
                if (k === 'MX-CHH') stateName = 'Chihuahua';
                mxData.push([{v: k, f: stateName}, mxCounts[k]]);
            });

            Object.keys(arCounts).forEach(k => {
                let provName = k;
                if (k === 'AR-B') provName = 'Provincia de Buenos Aires';
                if (k === 'AR-X') provName = 'Córdoba';
                if (k === 'AR-M') provName = 'Mendoza';
                if (k === 'AR-C') provName = 'Ciudad Autónoma de Buenos Aires';
                arData.push([{v: k, f: provName}, arCounts[k]]);
            });

            let isDark = document.documentElement.classList.contains('dark');
            let commonOptions = {
                colorAxis: {colors: ['#bbf7d0', '#22c55e', '#15803d']}, // Tonos de Caloritrack
                backgroundColor: 'transparent',
                datalessRegionColor: isDark ? '#334155' : '#f1f5f9',
                defaultColor: isDark ? '#475569' : '#e2e8f0',
                legend: 'none'
            };

            // Pintamos Mundo
            if (document.getElementById('worldMap')) {
                let worldChart = new google.visualization.GeoChart(document.getElementById('worldMap'));
                worldChart.draw(google.visualization.arrayToDataTable(worldData), commonOptions);
            }

            // Pintamos México
            if (document.getElementById('mxMap')) {
                let mxChart = new google.visualization.GeoChart(document.getElementById('mxMap'));
                mxChart.draw(google.visualization.arrayToDataTable(mxData), Object.assign({}, commonOptions, {region: 'MX', resolution: 'provinces'}));
            }

            // Pintamos Argentina
            if (document.getElementById('arMap')) {
                let arChart = new google.visualization.GeoChart(document.getElementById('arMap'));
                arChart.draw(google.visualization.arrayToDataTable(arData), Object.assign({}, commonOptions, {region: 'AR', resolution: 'provinces'}));
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            trendsChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Usuarios Únicos',
                        data: [],
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
                    plugins: { legend: { display: false }, datalabels: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
                }
            });

            mealPlansChartInstance = initDoughnutChart('mealPlansChart');
            genderChartInstance = initDoughnutChart('genderChart');
            imcChartInstance = initDoughnutChart('imcChart');
            ageChartInstance = initBarChart('ageChart', false);
            languageChartInstance = initBarChart('languageChart', true);
            regionChartInstance = initBarChart('regionChart', true);

            updateAnalytics();
        });

        async function updateAnalytics() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const btn = document.getElementById('refreshBtn');
            const icon = btn.querySelector('i');
            
            document.cookie = "<?php echo $cookieStartName; ?>=" + startDate + "; path=/; SameSite=Strict";
            document.cookie = "<?php echo $cookieEndName; ?>=" + endDate + "; path=/; SameSite=Strict";
            
            if(icon) icon.classList.add('animate-spin');
            if(btn) btn.classList.add('opacity-80', 'cursor-not-allowed');

            try {
                // KPIs
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

                // Trends
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

                // Distribution
                const resDist = await fetch(`ajax_analytics.php?action=distribution&groupBy=screen_name&startDate=${startDate}&endDate=${endDate}`);
                const jsonDist = await resDist.json();
                if(jsonDist.success && jsonDist.data && jsonDist.data.data) {
                    let fastingCount = 0, kaiCount = 0, nutritionCount = 0, wellnessCount = 0;
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

                // Subscriptions
                const resSubs = await fetch(`ajax_analytics.php?action=subscriptions`);
                const jsonSubs = await resSubs.json();
                const subsList = document.getElementById('subscriptionsList');
                if(jsonSubs.success && jsonSubs.data) {
                    let dataPlanes = jsonSubs.data.data !== undefined ? jsonSubs.data.data : jsonSubs.data;
                    const config = {
                        'Free': { color: 'text-gray-500 dark:text-gray-400', bg: 'bg-gray-100 dark:bg-gray-800', icon: 'ph-leaf', label: 'Plan Básico' },
                        'Familiar': { color: 'text-purple-500', bg: 'bg-purple-50 dark:bg-purple-900/30', icon: 'ph-users', label: 'Suscripción Grupal' },
                        'Premium': { color: 'text-calori-600', bg: 'bg-calori-50 dark:bg-calori-900/30', icon: 'ph-crown', label: 'Máximo Potencial' }
                    };
                    let html = '';
                    Object.keys(dataPlanes).forEach(key => {
                        if (key === 'success' || key === 'data' || key === 'pagination') return;
                        const style = config[key] || { color: 'text-green-500', bg: 'bg-green-50', icon: 'ph-star', label: 'Plan' };
                        html += `
                            <div class="flex items-center justify-between p-4 bg-white/40 dark:bg-darkbase-900/40 border border-white/20 dark:border-gray-800 rounded-2xl transition-all hover:translate-x-1 shadow-sm mb-3">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl ${style.bg} flex items-center justify-center ${style.color} shadow-inner"><i class="ph ${style.icon} text-2xl"></i></div>
                                    <div><h4 class="font-bold text-gray-900 dark:text-white leading-tight">${key}</h4><p class="text-[10px] font-black uppercase tracking-widest opacity-40">${style.label}</p></div>
                                </div>
                                <div class="text-right"><span class="text-2xl font-black text-gray-900 dark:text-white">${Number(dataPlanes[key]).toLocaleString()}</span></div>
                            </div>`;
                    });
                    subsList.innerHTML = html || '<p class="text-sm text-gray-500">Sin membresías por mostrar.</p>';
                }

                // Meal Plans
                const resMeal = await fetch(`ajax_analytics.php?action=meal_plans`);
                const jsonMeal = await resMeal.json();
                if(jsonMeal.success && jsonMeal.data && mealPlansChartInstance) {
                    const dataMeal = jsonMeal.data.data || jsonMeal.data;
                    const keys = Object.keys(dataMeal).filter(k => k !== 'success' && k !== 'data');
                    mealPlansChartInstance.data.labels = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1));
                    mealPlansChartInstance.data.datasets[0].data = keys.map(k => dataMeal[k]);
                    mealPlansChartInstance.data.datasets[0].backgroundColor = ['#22c55e', '#ef4444', '#f59e0b', '#64748b'];
                    mealPlansChartInstance.update();
                }

                // Demographics & Maps
                const resDemo = await fetch(`ajax_analytics.php?action=demographics`);
                const jsonDemo = await resDemo.json();
                if(jsonDemo.success && jsonDemo.data) {
                    const demo = jsonDemo.data.data || jsonDemo.data;
                    
                    if(demo.generos && genderChartInstance) {
                        genderChartInstance.data.labels = demo.generos.map(g => g.genero);
                        genderChartInstance.data.datasets[0].data = demo.generos.map(g => g.total);
                        genderChartInstance.data.datasets[0].backgroundColor = ['#3b82f6', '#ec4899', '#a855f7'];
                        genderChartInstance.update();
                    }
                    if(demo.imc && imcChartInstance) {
                        const imcKeys = Object.keys(demo.imc);
                        imcChartInstance.data.labels = imcKeys;
                        imcChartInstance.data.datasets[0].data = imcKeys.map(k => demo.imc[k]);
                        imcChartInstance.data.datasets[0].backgroundColor = ['#60a5fa', '#22c55e', '#f97316', '#ef4444'];
                        imcChartInstance.update();
                    }
                    if(demo.edades && ageChartInstance) {
                        ageChartInstance.data.labels = Object.keys(demo.edades);
                        ageChartInstance.data.datasets[0].data = Object.keys(demo.edades).map(k => demo.edades[k]);
                        ageChartInstance.data.datasets[0].backgroundColor = '#8b5cf6';
                        ageChartInstance.update();
                    }
                    
                    // Novedad: Ordenamiento dinámico (Sort descendente) para Idiomas
                    if(demo.lenguajes && languageChartInstance) {
                        let sortedLenguajes = [...demo.lenguajes].sort((a, b) => b.total - a.total);
                        languageChartInstance.data.labels = sortedLenguajes.map(l => {
                            let p = l.lenguaje_preferido.split('_');
                            return `${p[0].toUpperCase()} ${p.length > 1 ? '('+p[1].toUpperCase()+')' : ''}`;
                        });
                        languageChartInstance.data.datasets[0].data = sortedLenguajes.map(l => l.total);
                        languageChartInstance.data.datasets[0].backgroundColor = '#14b8a6';
                        languageChartInstance.update();
                    }
                    
                    // Novedad: Ordenamiento dinámico (Sort descendente) para Regiones
                    if(demo.timezones && regionChartInstance) {
                        let sortedTimezones = [...demo.timezones].sort((a, b) => b.total - a.total);
                        regionChartInstance.data.labels = sortedTimezones.map(t => {
                            let p = t.timezone.split('/');
                            return `${(p[p.length-1] || '').replace(/_/g, ' ')} (${p[0] || ''})`;
                        });
                        regionChartInstance.data.datasets[0].data = sortedTimezones.map(t => t.total);
                        regionChartInstance.data.datasets[0].backgroundColor = '#f59e0b';
                        regionChartInstance.update();

                        // Llamamos a dibujar los mapas con la data cruda de timezones
                        drawMaps(demo.timezones);
                    }
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
                        <input type="date" id="startDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="<?php echo htmlspecialchars($inputStartDate); ?>">
                    </div>
                    <div class="w-px h-8 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-gray-400 uppercase ml-2 mb-0.5">Fin</span>
                        <input type="date" id="endDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white" value="<?php echo htmlspecialchars($inputEndDate); ?>">
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

            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 px-1">Radiografía de la Comunidad</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Planes Alimenticios</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Activos vs Cancelados</p></div>
                            <i class="ph ph-apple-logo text-xl text-green-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="mealPlansChart"></canvas></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Distribución de Género</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Demografía</p></div>
                            <i class="ph ph-gender-intersex text-xl text-pink-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="genderChart"></canvas></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Salud e IMC</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Categorías de Peso</p></div>
                            <i class="ph ph-heartbeat text-xl text-red-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="imcChart"></canvas></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Edades</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Grupos Generacionales</p></div>
                            <i class="ph ph-calendar-blank text-xl text-purple-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="ageChart"></canvas></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Idiomas</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Configuración de Dispositivo</p></div>
                            <i class="ph ph-translate text-xl text-teal-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="languageChart"></canvas></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <div><h3 class="text-md font-bold text-gray-900 dark:text-white">Regiones y Zonas</h3><p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Timezones Activos</p></div>
                            <i class="ph ph-globe-hemisphere-west text-xl text-orange-500"></i>
                        </div>
                        <div class="h-56 w-full relative"><canvas id="regionChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 px-1">Distribución Geográfica (Densidad de Usuarios)</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col overflow-hidden">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white">El Mundo</h3>
                            <i class="ph ph-globe text-xl text-blue-500"></i>
                        </div>
                        <div id="worldMap" class="h-64 w-full"></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col overflow-hidden">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white">México</h3>
                            <i class="ph ph-map-pin text-xl text-green-600"></i>
                        </div>
                        <div id="mxMap" class="h-64 w-full"></div>
                    </div>
                    <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col overflow-hidden">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-md font-bold text-gray-900 dark:text-white">Argentina</h3>
                            <i class="ph ph-map-pin text-xl text-blue-400"></i>
                        </div>
                        <div id="arMap" class="h-64 w-full"></div>
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