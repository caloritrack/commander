<?php
// Nombre del archivo: customers.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-09
// Versión: 2.3
// Descripción: Se modificó la columna de Nacimiento para mostrar la Edad calculada dinámicamente al día actual. Se reemplazó el valor numérico del IMC por su categoría médica (Bajo peso, Normal, Sobrepeso, Obesidad) calculada en tiempo real a partir del peso y la estatura, implementando colores semánticos para facilitar su lectura.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Reporte de Clientes | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

ob_start();
?>
    <style>
        .submenu { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; max-height: 0; opacity: 0; overflow: hidden; }
        .submenu.open { max-height: 200px; opacity: 1; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    <script>
        // Variable global para almacenar los datos y poder exportarlos
        let allCustomersData = [];
        let currentPage = 1;
        let totalPages = 1;

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

        // Formateador de fechas (con hora)
        function formatDate(dateString) {
            if(!dateString) return '<span class="text-gray-400">N/A</span>';
            const d = new Date(dateString);
            return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' });
        }

        // Función para calcular la Edad exacta a partir de la fecha de nacimiento
        function calculateAge(dateString) {
            if(!dateString) return '<span class="text-gray-400">-</span>';
            const dob = new Date(dateString);
            if (isNaN(dob)) return '<span class="text-gray-400">-</span>';
            const diff_ms = Date.now() - dob.getTime();
            const age_dt = new Date(diff_ms); 
            const age = Math.abs(age_dt.getUTCFullYear() - 1970);
            return `<span class="text-gray-700 dark:text-gray-300 font-medium">${age} años</span>`;
        }

        // Diccionarios para traducir códigos a nombres legibles
        const languageNames = {
            'es': 'Español',
            'en': 'Inglés',
            'fr': 'Francés',
            'pt': 'Portugués',
            'de': 'Alemán',
            'it': 'Italiano'
        };
        const countryNames = {
            'MX': 'México',
            'US': 'Estados Unidos',
            'AR': 'Argentina',
            'ES': 'España',
            'FR': 'Francia',
            'IE': 'Irlanda',
            'CO': 'Colombia',
            'CL': 'Chile',
            'PE': 'Perú'
        };

        // Función que separa y embellece el locale (ej: es_MX -> { lang: 'Español', country: '🇲🇽 México' })
        function parseLocale(locale) {
            if (!locale) return { lang: '<span class="text-gray-400">N/A</span>', country: '<span class="text-gray-400">N/A</span>' };
            try {
                const parts = locale.split('_');
                const langCode = parts[0].toLowerCase();
                const countryCode = parts.length > 1 ? parts[1].toUpperCase() : '';

                const langName = languageNames[langCode] || langCode;
                const countryName = countryNames[countryCode] || countryCode;
                let flagHtml = '';
                if (countryCode) {
                    const flag = String.fromCodePoint(...[...countryCode].map(c => c.charCodeAt(0) + 127397));
                    flagHtml = `<span class="mr-1.5 text-lg">${flag}</span> <span>${countryName}</span>`;
                } else {
                    flagHtml = `<span>${countryName}</span>`;
                }

                return { lang: langName, country: flagHtml };
            } catch (e) {
                console.error("Error parseando locale", e);
                return { lang: locale, country: '<span class="text-gray-400">N/A</span>' };
            }
        }

        // Función para exportar los datos a un archivo JSON
        function exportToJSON() {
            if (allCustomersData.length === 0) {
                alert("No hay datos para exportar todavía.");
                return;
            }
            
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(allCustomersData, null, 4));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", `caloritrack-customers-page-${currentPage}.json`);
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        }

        // Función para cambiar de página
        function changePage(delta) {
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                loadCustomers(newPage);
            }
        }

        async function loadCustomers(page = 1) {
            const tbody = document.getElementById('customersTableBody');
            const loader = document.getElementById('tableLoader');
            const counter = document.getElementById('totalCounter');
            const exportBtn = document.getElementById('exportBtn');
            const tableCont = document.getElementById('tableContainer');
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            exportBtn.classList.add('hidden');

            try {
                const res = await fetch(`ajax_analytics.php?action=customers&page=${page}`);
                const json = await res.json();
                
                if(json.success && json.data) {
                    const payload = json.data;
                    const users = payload.data || []; 
                    const pag = payload.pagination; 
                    const stats = payload.summary_stats;

                    allCustomersData = users; // Guardamos en la variable global
                    
                    if(pag) {
                        currentPage = pag.current_page;
                        totalPages = pag.total_pages;
                        document.getElementById('pageIndicator').innerText = `Página ${currentPage} de ${totalPages}`;
                        
                        document.getElementById('prevBtn').disabled = (currentPage === 1);
                        document.getElementById('nextBtn').disabled = (currentPage === totalPages);
                        counter.innerText = pag.total_records || users.length;
                    } else {
                        counter.innerText = users.length;
                    }

                    if(stats) {
                        document.getElementById('val-incompletos').innerText = stats.usuarios_registro_incompleto || 0;
                        document.getElementById('val-unica').innerText = stats.usuarios_sesion_unica || 0;
                    }
                    
                    // Mostramos el botón de exportar si hay datos
                    exportBtn.classList.remove('hidden');
                    let html = '';
                    
                    users.forEach(user => {
                        // Badge para la membresía
                        let planBadge = '<span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-md text-xs font-bold">N/A</span>';
                        if(user.nombre_membresia === 'Premium') {
                            planBadge = '<span class="px-2 py-1 bg-calori-100 dark:bg-calori-900/30 text-calori-600 dark:text-calori-400 rounded-md text-xs font-bold border border-calori-200 dark:border-calori-800"><i class="ph ph-crown mr-1"></i>Premium</span>';
                        } else if(user.nombre_membresia === 'Free') {
                            planBadge = '<span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-md text-xs font-bold border border-blue-100 dark:border-blue-800">Free</span>';
                        } else if(user.nombre_membresia === 'Familiar') {
                            planBadge = '<span class="px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-md text-xs font-bold border border-purple-200 dark:border-purple-800"><i class="ph ph-users mr-1"></i>Familiar</span>';
                        }

                        // Lógica UX para Plan Alimenticio
                        let dietBadge = '<span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 rounded-md text-xs font-bold border border-gray-200 dark:border-gray-700">Sin Plan</span>';
                        if(user.plan_alimentario_status === 'activo') {
                            dietBadge = `
                                <div class="flex flex-col gap-1">
                                    <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-md text-xs font-bold border border-green-200 dark:border-green-800 w-fit">Plan Activo</span>
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium italic">Fin: ${formatDate(user.plan_alimentario_fecha_fin)}</span>
                                </div>
                            `;
                        }

                        // Badge para el estado de cuenta
                        let statusBadge = '<span class="px-2 py-1 bg-green-50 text-green-600 rounded-full text-xs font-medium">Activo</span>';
                        if(user.estado_cuenta !== 'Activo') {
                            statusBadge = `<span class="px-2 py-1 bg-red-50 text-red-600 rounded-full text-xs font-medium">${user.estado_cuenta || 'Inactivo'}</span>`;
                        }

                        // --- LÓGICA DE UI/UX (Género y Actividad) ---
                        
                        let generoHtml = '<span class="text-gray-400">-</span>';
                        if (user.genero) {
                            const gStr = user.genero.toLowerCase();
                            let gClass = "bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700"; 
                            if (gStr === 'mujer' || gStr === 'femenino' || gStr === 'f') {
                                gClass = "bg-pink-100 dark:bg-pink-900/30 text-pink-600 dark:text-pink-400 border-pink-200 dark:border-pink-800";
                            } else if (gStr === 'hombre' || gStr === 'masculino' || gStr === 'm') {
                                gClass = "bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800";
                            }
                            generoHtml = `<span class="px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider border ${gClass}">${user.genero}</span>`;
                        }

                        let nivelActividadHtml = '<span class="text-gray-400">-</span>';
                        if (user.nivel_actividad) {
                            const aStr = user.nivel_actividad.toLowerCase();
                            let aClass = "bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700"; 
                            if (aStr.includes('sedentario')) {
                                aClass = "bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800";
                            } else if (aStr.includes('ligero') || aStr.includes('poco')) {
                                aClass = "bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 border-orange-200 dark:border-orange-800";
                            } else if (aStr.includes('moderado')) {
                                aClass = "bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800";
                            } else if (aStr.includes('muy activo') || aStr.includes('vigoroso')) {
                                aClass = "bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800";
                            } else if (aStr.includes('activo')) {
                                aClass = "bg-lime-100 dark:bg-lime-900/30 text-lime-600 dark:text-lime-400 border-lime-200 dark:border-lime-800";
                            }
                            nivelActividadHtml = `<span class="px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider border ${aClass}">${user.nivel_actividad}</span>`;
                        }

                        // Cálculo de Edad
                        const edadHtml = user.fecha_nacimiento ? calculateAge(user.fecha_nacimiento) : '<span class="text-gray-400">-</span>';

                        // Cálculo Inteligente de IMC y Categorización
                        let imcValueHtml = '<span class="text-gray-400 font-bold">-</span>';
                        let imcNumCalc = 0;
                        
                        if (user.peso && user.altura_cm) {
                            const pesoVal = parseFloat(user.peso);
                            const alturaM = parseFloat(user.altura_cm) / 100;
                            if (alturaM > 0) {
                                imcNumCalc = pesoVal / (alturaM * alturaM);
                            }
                        } else if (user.imc) {
                            imcNumCalc = parseFloat(user.imc);
                        }

                        if (imcNumCalc > 0) {
                            let imcText = '';
                            let imcClass = '';
                            
                            if (imcNumCalc < 18.5) {
                                imcText = 'Bajo peso';
                                imcClass = 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800';
                            } else if (imcNumCalc >= 18.5 && imcNumCalc < 25) {
                                imcText = 'Normal';
                                imcClass = 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800';
                            } else if (imcNumCalc >= 25 && imcNumCalc < 30) {
                                imcText = 'Sobrepeso';
                                imcClass = 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 border-orange-200 dark:border-orange-800';
                            } else {
                                imcText = 'Obesidad';
                                imcClass = 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800';
                            }
                            
                            imcValueHtml = `<span class="px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider border ${imcClass} cursor-help" title="IMC Exacto: ${imcNumCalc.toFixed(1)}">${imcText}</span>`;
                        }

                        // Métricas Base
                        const nombre = user.nombre_completo || 'Héroe Anónimo';
                        const peso = user.peso ? `${user.peso} kg` : '-';
                        const altura = user.altura_cm ? `${user.altura_cm} cm` : '-';
                        const localeInfo = parseLocale(user.lenguaje_preferido);
                        const subInicio = formatDate(user.subscription_started_at);
                        const subFin = formatDate(user.subscription_expires_at);

                        // Identificar si el registro está incompleto para pintar toda la fila gris
                        const isRegistroIncompleto = !user.genero && !user.fecha_nacimiento && !user.lenguaje_preferido;
                        const rowCssClass = isRegistroIncompleto 
                            ? "border-b border-gray-200 dark:border-gray-700 bg-gray-100/80 dark:bg-gray-800/80 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors opacity-80" 
                            : "border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors";

                        html += `
                            <tr class="${rowCssClass}">
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-calori-100 dark:bg-calori-900 flex items-center justify-center text-calori-600 dark:text-calori-400 font-bold shrink-0">
                                            ${nombre.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">${nombre}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">${user.email}</p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-center">${generoHtml}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-center">${edadHtml}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 flex items-center font-medium">
                                    ${localeInfo.country}
                                </td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    ${localeInfo.lang}
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="flex gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="bg-white dark:bg-gray-900 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 shadow-sm">${peso}</span>
                                        <span class="bg-white dark:bg-gray-900 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 shadow-sm">${altura}</span>
                                    </div>
                                </td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-center">
                                    ${imcValueHtml}
                                </td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-center">
                                    ${nivelActividadHtml}
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">${planBadge}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-300">Inicio:</strong> ${subInicio}</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-300">Fin:</strong> ${subFin}</span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">${dietBadge}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">${statusBadge}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${formatDate(user.fecha_registro)}</td>
                                
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${formatDate(user.ultima_sesion)}</td>
                            </tr>
                        `;
                    });
                    
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = `<tr><td colspan="14" class="text-center py-8 text-red-500">No se pudieron cargar los datos.</td></tr>`;
                }
            } catch (error) {
                console.error("Error al cargar clientes:", error);
                tbody.innerHTML = `<tr><td colspan="14" class="text-center py-8 text-red-500">Error de conexión.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', () => loadCustomers(1));
    </script>
<?php
$extraHead = ob_get_clean();

require_once __DIR__ . '/includes/header.php';
?>

    <div class="absolute top-[-10%] left-[20%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[10%] w-[500px] h-[500px] bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none" style="animation-delay: 2s;"></div>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden z-10 relative">
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <div class="mb-8 flex flex-col lg:flex-row lg:items-end justify-between gap-4 shrink-0 relative z-50">
                
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Reporte de Clientes</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Directorio maestro de héroes registrados en CaloriTrack.</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        
                        <div class="bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 px-4 py-2 rounded-xl backdrop-blur-sm shadow-sm flex items-center gap-3">
                            <i class="ph ph-users text-green-600 dark:text-green-400 text-xl"></i>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total</span>
                                <span id="totalCounter" class="text-xl font-black text-gray-800 dark:text-white leading-none mt-0.5">--</span>
                            </div>
                        </div>

                        <div class="group relative bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 px-4 py-2 rounded-xl backdrop-blur-sm shadow-sm flex items-center gap-3 cursor-help">
                            <i class="ph ph-user-minus text-orange-500 text-xl"></i>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Incompletos</span>
                                <span id="val-incompletos" class="text-xl font-black text-gray-800 dark:text-white leading-none mt-0.5">--</span>
                            </div>
                            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-3 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100] font-medium pointer-events-none">
                                Clientes que iniciaron el registro pero no lo terminaron.
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 border-8 border-transparent border-b-gray-900 dark:border-b-gray-100"></div>
                            </div>
                        </div>

                        <div class="group relative bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 px-4 py-2 rounded-xl backdrop-blur-sm shadow-sm flex items-center gap-3 cursor-help">
                            <i class="ph ph-user-focus text-blue-500 text-xl"></i>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Sesión Única</span>
                                <span id="val-unica" class="text-xl font-black text-gray-800 dark:text-white leading-none mt-0.5">--</span>
                            </div>
                            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-3 w-64 p-3 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-xs text-center rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-[100] font-medium pointer-events-none">
                                Clientes que hicieron registro completo pero no han vuelto a hacer login en la aplicación desde ese mismo día.
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 border-8 border-transparent border-b-gray-900 dark:border-b-gray-100"></div>
                            </div>
                        </div>

                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="exportBtn" onclick="exportToJSON()" class="hidden flex items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-download-simple"></i>
                        Exportar JSON
                    </button>
                </div>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Desencriptando base de datos de Hermes...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col relative z-10">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usuario</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Género</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Edad</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">País</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Idioma</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Métricas</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">IMC</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Actividad</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Membresía</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Suscripción</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan Alimenticio</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Registro</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Último Login</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                            </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-white/50 dark:bg-darkbase-900/50 backdrop-blur-md">
                    <span id="pageIndicator" class="text-sm font-bold text-gray-600 dark:text-gray-400">Página 1 de 1</span>
                    <div class="flex items-center gap-3">
                        <button id="prevBtn" onclick="changePage(-1)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            <i class="ph ph-caret-left mr-1"></i> Anterior
                        </button>
                        <button id="nextBtn" onclick="changePage(1)" class="px-4 py-2 bg-calori-600 text-white rounded-xl text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed hover:bg-calori-700 transition-colors shadow-lg shadow-calori-600/20">
                            Siguiente <i class="ph ph-caret-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>