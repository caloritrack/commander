<?php
// Nombre del archivo: sessions_report.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha y Hora: 2026-11-16 11:50:00 CST
// Versión: 1.1 (Blindaje contra DOM XSS con escapeHtml y bandera Secure en cookies de filtro)
// Descripción: Reporte granular de sesiones de usuarios. Implementa filtros de fecha y expone telemetría detallada (dispositivo, duración, eventos disparados y pantalla de salida) sanitizando todos los datos externos.

require_once __DIR__ . '/includes/auth_protect.php';

// --- LÓGICA DE FECHAS (POR DEFECTO HOY) ---
$sessionHash = md5($_SESSION['user_token'] ?? 'default_session');
$cookieStartName = 'dash_sessions_start_' . $sessionHash;
$cookieEndName = 'dash_sessions_end_' . $sessionHash;

// Arturo, aquí seteamos "HOY" por default en ambos lados para ver la radiografía del día actual.
$defaultDate = date('Y-m-d'); 

$inputStartDate = $_COOKIE[$cookieStartName] ?? $defaultDate;
$inputEndDate = $_COOKIE[$cookieEndName] ?? $defaultDate;

$pageTitle = 'Detalle de Sesiones | Portal de Héroes Caloritrack';
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
        let allSessionsData = [];
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

        // Formatea la hora a un formato legible
        function formatDateTime(dateString) {
            if(!dateString) return '<span class="text-gray-400">Desconocido</span>';
            const d = new Date(dateString);
            if (isNaN(d)) return '<span class="text-gray-400">Desconocido</span>';
            return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute:'2-digit', second:'2-digit' }) + ' <span class="text-[10px] text-gray-400 block">' + d.toLocaleDateString('es-MX') + '</span>';
        }

        // Formatea la duración de segundos a Mins y Segs
        function formatDuration(secondsRaw) {
            if(!secondsRaw && secondsRaw !== 0) return '<span class="text-gray-400">-</span>';
            const totalSecs = parseInt(secondsRaw, 10);
            if(isNaN(totalSecs)) return '<span class="text-gray-400">-</span>';
            if(totalSecs < 60) return `<span class="text-gray-600 dark:text-gray-400 font-medium">${totalSecs}s</span>`;
            const mins = Math.floor(totalSecs / 60);
            const secs = totalSecs % 60;
            return `<span class="text-gray-800 dark:text-gray-200 font-bold">${mins}m ${secs}s</span>`;
        }

        function exportToJSON() {
            if (allSessionsData.length === 0) {
                alert("No hay datos de sesiones para exportar.");
                return;
            }
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(allSessionsData, null, 4));
            const downloadNode = document.createElement('a');
            downloadNode.setAttribute("href", dataStr);
            downloadNode.setAttribute("download", `caloritrack-sesiones-hoy.json`);
            document.body.appendChild(downloadNode);
            downloadNode.click();
            downloadNode.remove();
        }

        function changePage(delta) {
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                loadSessions(newPage);
            }
        }

        async function loadSessions(page = 1) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const btn = document.getElementById('refreshBtn');
            const btnIcon = btn.querySelector('i');

            // Guardamos las cookies de fechas con SameSite=Strict y Secure
            document.cookie = "<?php echo $cookieStartName; ?>=" + encodeURIComponent(startDate) + "; path=/; SameSite=Strict; Secure";
            document.cookie = "<?php echo $cookieEndName; ?>=" + encodeURIComponent(endDate) + "; path=/; SameSite=Strict; Secure";

            const tbody = document.getElementById('sessionsTableBody');
            const loader = document.getElementById('tableLoader');
            const exportBtn = document.getElementById('exportBtn');
            const tableCont = document.getElementById('tableContainer');
            
            if(btnIcon) btnIcon.classList.add('animate-spin');
            btn.classList.add('opacity-80', 'cursor-not-allowed');

            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            exportBtn.classList.add('hidden');

            try {
                // Aquí llamamos al endpoint proxy que configuramos en ajax_analytics.php
                const res = await fetch(`ajax_analytics.php?action=sessions_detail&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}&page=${page}`);
                const json = await res.json();
                
                if(json.success && json.data) {
                    const payload = json.data;
                    const sessions = payload.data || []; 
                    const pag = payload.pagination; 

                    allSessionsData = sessions;
                    
                    if(pag) {
                        currentPage = pag.current_page || 1;
                        totalPages = pag.total_pages || 1;
                        document.getElementById('pageIndicator').innerText = `Página ${currentPage} de ${totalPages}`;
                        
                        document.getElementById('prevBtn').disabled = (currentPage === 1);
                        document.getElementById('nextBtn').disabled = (currentPage === totalPages);
                        document.getElementById('totalCounter').innerText = pag.total_records || sessions.length;
                    } else {
                        document.getElementById('totalCounter').innerText = sessions.length;
                    }
                    
                    exportBtn.classList.remove('hidden');
                    let html = '';
                    
                    if(sessions.length === 0) {
                        html = `<tr><td colspan="8" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-ghost text-4xl block mb-2 opacity-50"></i>No hay sesiones registradas en este rango de fechas.</td></tr>`;
                    } else {
                        sessions.forEach(session => {
                            // Avatar y Datos del Héroe Sanitizados contra XSS
                            const rawUserName = String(session.user_name || 'Héroe Anónimo');
                            const avatarLetter = escapeHtml(rawUserName.charAt(0).toUpperCase());
                            const userName = escapeHtml(rawUserName);
                            const userEmail = escapeHtml(session.user_email || 'Sin registro');

                            // Dispositivo (iOS o Web u Otro) Sanitizado
                            let deviceIcon = '<i class="ph ph-device-mobile text-gray-400"></i>';
                            let deviceColor = 'text-gray-500 bg-gray-50 border-gray-200';
                            let osLabel = escapeHtml(session.os_version || 'Desconocido');
                            let deviceModel = escapeHtml(session.device_model || 'Celular');
                            
                            if(session.platform && String(session.platform).toLowerCase().includes('ios')) {
                                deviceIcon = '<i class="ph ph-apple-logo text-gray-900 dark:text-white"></i>';
                                deviceColor = 'text-gray-900 bg-gray-100 dark:bg-gray-800 dark:text-gray-100 border-gray-300 dark:border-gray-600';
                            }

                            // Pantalla Final Sanitizada
                            const lastScreen = escapeHtml(session.last_screen || 'Desconocida');
                            let screenBadge = `<span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded text-xs font-mono truncate max-w-[150px] inline-block" title="${lastScreen}">${lastScreen}</span>`;

                            // Ubicación Sanitizada
                            const rawLocation = session.timezone ? String(session.timezone).split('/').pop().replace(/_/g, ' ') : 'Desconocida';
                            const locationStr = escapeHtml(rawLocation);
                            const totalEvents = parseInt(session.total_events, 10) || 0;

                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-calori-100 dark:bg-calori-900/50 flex items-center justify-center text-calori-600 dark:text-calori-400 font-bold shrink-0 border border-calori-200 dark:border-calori-800">
                                                ${avatarLetter}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white">${userName}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">${userEmail}</p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">
                                        ${formatDateTime(session.started_at)}
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-center">
                                        ${formatDuration(session.duration_seconds)}
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap text-center">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border ${deviceColor} text-xs font-medium">
                                            ${deviceIcon} ${deviceModel}
                                            <span class="opacity-50 ml-1">(${osLabel})</span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                                        <i class="ph ph-map-pin text-gray-400"></i> ${locationStr}
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap text-center">
                                        <span class="px-3 py-1 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 font-bold rounded-lg border border-purple-100 dark:border-purple-800 text-xs">
                                            ${totalEvents}
                                        </span>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        ${screenBadge}
                                    </td>

                                    <td class="py-4 px-4 whitespace-nowrap text-center">
                                        <a href="#" class="text-blue-500 hover:text-blue-600 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-flex" title="Ver Timeline (Proximamente)">
                                            <i class="ph ph-eye text-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    
                    tbody.innerHTML = html;
                } else {
                    // Si el backend aún no nos manda la data (Genesis todavía no sube el endpoint a Hermes)
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-12">
                        <div class="bg-orange-50 dark:bg-orange-900/20 p-6 rounded-2xl border border-orange-200 dark:border-orange-800 inline-block">
                            <i class="ph ph-warning text-4xl text-orange-500 mb-3 block"></i>
                            <p class="text-sm text-orange-800 dark:text-orange-300 font-bold">El endpoint de Hermes aún no devuelve los datos desglosados.</p>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">Avísale a Genesis que el proxy ya está apuntando a /admin/analytics/sessions/detail</p>
                        </div>
                    </td></tr>`;
                }
            } catch (error) {
                console.error("Error al cargar las sesiones:", error);
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-red-500">Error de conexión con Hermes. Revisa el proxy.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
                
                if(btnIcon) btnIcon.classList.remove('animate-spin');
                btn.classList.remove('opacity-80', 'cursor-not-allowed');
            }
        }

        // Cargamos al iniciar la vista
        document.addEventListener('DOMContentLoaded', () => loadSessions(1));
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
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Detalle de Sesiones</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Inspección granular de accesos, dispositivos y telemetría por usuario.</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 px-4 py-2 rounded-xl backdrop-blur-sm shadow-sm flex items-center gap-3">
                            <i class="ph ph-fingerprint text-calori-600 dark:text-calori-400 text-xl"></i>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Registros</span>
                                <span id="totalCounter" class="text-xl font-black text-gray-800 dark:text-white leading-none mt-0.5">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 p-2 rounded-xl backdrop-blur-sm shadow-sm">
                    <div class="flex flex-col pl-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase mb-0.5">Inicio</span>
                        <input type="date" id="startDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white outline-none" value="<?php echo htmlspecialchars($inputStartDate); ?>">
                    </div>
                    <div class="w-px h-8 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col pl-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase mb-0.5">Fin</span>
                        <input type="date" id="endDate" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer dark:text-white outline-none" value="<?php echo htmlspecialchars($inputEndDate); ?>">
                    </div>
                    <button id="refreshBtn" onclick="loadSessions(1)" class="ml-2 p-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-md">
                        <i class="ph ph-magnifying-glass font-bold"></i>
                    </button>
                    
                    <button id="exportBtn" onclick="exportToJSON()" class="hidden ml-1 p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Exportar JSON">
                        <i class="ph ph-download-simple font-bold"></i>
                    </button>
                </div>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Leyendo logs y telemetría en tiempo real...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col relative z-10">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Héroe</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hora de Inicio</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Duración</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Dispositivo</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ubicación Aprox.</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Eventos Disp.</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Última Pantalla</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="sessionsTableBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <!-- Inyección JS -->
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