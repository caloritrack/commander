<?php
// Nombre del archivo: planes_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-24
// Versión: 1.3
// Descripción: Interfaz gráfica (CRUD) para el Catálogo Maestro de Planes Alimenticios. Se refactorizó la arquitectura relacional para soportar de 1 a muchas Dietas por Plan. Se eliminó el dropdown individual de la pestaña General y se implementó una pestaña completa ("Dietas Asignadas") con lógica de arreglo temporal en JS para agrupar múltiples dietas. La tabla principal ahora itera y muestra visualmente todas las dietas vinculadas.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Planes | Portal de Héroes Caloritrack';
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
        
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentPlanId = null;
        let currentDietas = []; // Memoria táctica para almacenar múltiples dietas

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

        function showToast(message, type = 'success') {
            const toast = document.getElementById('globalToast');
            const toastIcon = document.getElementById('toastIcon');
            const toastText = document.getElementById('toastText');
            
            toast.classList.remove('translate-y-full', 'opacity-0', 'bg-green-500', 'bg-red-500', 'bg-orange-500');
            
            if(type === 'success') {
                toast.classList.add('bg-green-500');
                toastIcon.className = 'ph ph-check-circle text-white text-xl mr-2';
            } else if(type === 'warning') {
                toast.classList.add('bg-orange-500');
                toastIcon.className = 'ph ph-warning text-white text-xl mr-2';
            } else {
                toast.classList.add('bg-red-500');
                toastIcon.className = 'ph ph-x-circle text-white text-xl mr-2';
            }
            
            toastText.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.add('translate-y-full', 'opacity-0'); }, 4000);
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('text-calori-600', 'border-calori-600', 'text-blue-600', 'border-blue-600', 'dark:text-calori-400', 'dark:border-calori-400', 'dark:text-blue-400', 'dark:border-blue-400');
                el.classList.add('text-gray-500', 'border-transparent');
            });

            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const btn = document.getElementById('btn-tab-' + tabId);
            btn.classList.remove('text-gray-500', 'border-transparent');
            
            // Si es la pestaña de Dietas, le damos estilo azul para diferenciar. Si no, verde CaloriTrack.
            if(tabId === 'dietas') {
                btn.classList.add('text-blue-600', 'border-blue-600', 'dark:text-blue-400', 'dark:border-blue-400');
            } else {
                btn.classList.add('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
            }
        }

        // Cargar las dietas disponibles para el combobox
        async function loadDietasForSelect() {
            try {
                const res = await fetch(`ajax_dietas.php?action=list&limit=1000`);
                const json = await res.json();
                const sel = document.getElementById('dietaSelectDropdown');
                
                sel.innerHTML = '<option value="">Selecciona una dieta de la base...</option>';
                
                if (json.success && json.data && json.data.data) {
                    json.data.data.forEach(dieta => {
                        const nombreVisible = dieta.nombre_es || dieta.nombre_clave;
                        sel.innerHTML += `<option value="${dieta.id_dieta}" data-name="${nombreVisible}">${nombreVisible} (ID: ${dieta.id_dieta})</option>`;
                    });
                }
            } catch(e) {
                console.error("Error al cargar las dietas para el select", e);
            }
        }

        // Lógica de Renderizado de la Sub-Tabla de Dietas
        function renderDietasTable() {
            const tbody = document.getElementById('tbodyDietasPlan');
            let html = '';

            if(currentDietas.length === 0) {
                html = '<tr><td colspan="3" class="text-center py-6 text-sm text-gray-500 font-medium">No has asignado ninguna dieta a este plan todavía.</td></tr>';
            } else {
                currentDietas.forEach((d, index) => {
                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="py-3 px-4 w-14 whitespace-nowrap">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center border border-blue-200 dark:border-blue-800">
                                    <i class="ph ph-heartbeat text-blue-500 dark:text-blue-400 text-xl"></i>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">${d.nombre}</p>
                                <p class="text-[10px] text-gray-400 font-mono">ID: ${d.id_dieta}</p>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button type="button" onclick="removeDietaFromPlan(${index})" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Quitar">
                                    <i class="ph ph-x-circle text-lg"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            tbody.innerHTML = html;
        }

        function addDietaToPlan() {
            const selectEl = document.getElementById('dietaSelectDropdown');
            const idDieta = selectEl.value;
            const nombre = selectEl.options[selectEl.selectedIndex]?.getAttribute('data-name') || 'Dieta ' + idDieta;

            if(!idDieta) {
                showToast("Por favor selecciona una dieta de la lista.", "warning");
                return;
            }

            const exists = currentDietas.findIndex(d => d.id_dieta == idDieta);
            if(exists >= 0) {
                showToast("La dieta ya se encuentra vinculada a este plan.", "warning");
                return;
            }

            currentDietas.push({
                id_dieta: parseInt(idDieta),
                nombre: nombre
            });

            selectEl.value = '';
            renderDietasTable();
            showToast("Dieta asignada correctamente.", "success");
        }

        function removeDietaFromPlan(index) {
            currentDietas.splice(index, 1);
            renderDietasTable();
        }

        // Carga y pintado de la Tabla Principal
        async function loadPlanes(page = 1) {
            const tbody = document.getElementById('planesTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            const searchVal = document.getElementById('searchInput').value.trim();
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');

            try {
                const res = await fetch(`ajax_planes.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchVal)}`);
                const json = await res.json();
                
                if(json.success && json.data) {
                    const data = json.data.data || [];
                    const pag = json.data.paginacion;

                    if(pag) {
                        currentPage = pag.pagina_actual;
                        totalPages = pag.paginas_totales;
                        document.getElementById('pageIndicator').innerText = `Página ${currentPage} de ${totalPages}`;
                        document.getElementById('prevBtn').disabled = (currentPage === 1);
                        document.getElementById('nextBtn').disabled = (currentPage === totalPages);
                        document.getElementById('totalCounter').innerText = pag.total_registros || data.length;
                    } else {
                        document.getElementById('totalCounter').innerText = data.length;
                    }

                    let html = '';
                    if(data.length === 0) {
                        html = `<tr><td colspan="7" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-calendar-check text-4xl block mb-2 opacity-50"></i>No se encontraron planes alimenticios.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            const activeBadge = item.activo == 1 
                                ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Activo</span>`
                                : `<span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded uppercase">Inactivo</span>`;
                            
                            const diffColor = item.nivel_dificultad === 'Avanzado' ? 'text-red-500' : (item.nivel_dificultad === 'Intermedio' ? 'text-orange-500' : 'text-green-500');
                            
                            // Traducir objetivo
                            let objetivoLabel = item.objetivo;
                            if(item.objetivo === 'ganar_peso') objetivoLabel = 'Ganar Peso';
                            else if(item.objetivo === 'mantener_peso') objetivoLabel = 'Mantener Peso';
                            else if(item.objetivo === 'perder_peso') objetivoLabel = 'Perder Peso';
                            else if(item.objetivo === 'recomposicion') objetivoLabel = 'Recomposición Corporal';
                            else if(item.objetivo === 'rendimiento') objetivoLabel = 'Rendimiento Físico';
                            else if(item.objetivo === 'salud_hormonal') objetivoLabel = 'Salud Hormonal';
                            else if(item.objetivo === 'antiflamatorio') objetivoLabel = 'Antiinflamatorio';
                            else if(item.objetivo === 'masa_muscular') objetivoLabel = 'Ganar Masa Muscular';

                            // Renderizado Dinámico de Dietas
                            let dietasHtml = '';
                            if (item.dietas && item.dietas.length > 0) {
                                item.dietas.forEach(d => {
                                    dietasHtml += `<span class="inline-flex items-center text-[10px] text-blue-600 dark:text-blue-400 font-bold mb-1 mr-1 border border-blue-200 dark:border-blue-800 rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/20"><i class="ph-fill ph-heartbeat mr-1"></i>${d.nombre_es || d.nombre_clave || 'ID: ' + d.id_dieta}</span>`;
                                });
                            } else if (item.id_dieta) {
                                // Soporte legado por si venimos de la versión de dieta única
                                dietasHtml = `<span class="inline-flex items-center text-[10px] text-blue-600 dark:text-blue-400 font-bold mb-1 mr-1 border border-blue-200 dark:border-blue-800 rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/20"><i class="ph-fill ph-heartbeat mr-1"></i>${item.dieta_nombre_clave || 'ID: ' + item.id_dieta}</span>`;
                            } else {
                                dietasHtml = '<span class="text-[10px] text-red-500 font-bold uppercase border border-red-200 bg-red-50 rounded px-1.5 py-0.5">Sin dietas</span>';
                            }

                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">${item.id_plan_catalogo}</td>
                                    
                                    <td class="py-3 px-4">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">${item.nombre_es || item.nombre_clave}</p>
                                        <p class="text-[10px] text-gray-500 font-mono uppercase">${item.nombre_clave}</p>
                                    </td>
                                    
                                    <td class="py-3 px-4 max-w-[200px]">
                                        <div class="flex flex-wrap mb-1">
                                            ${dietasHtml}
                                        </div>
                                        <p class="text-[11px] text-gray-500 line-clamp-1 border-t border-gray-100 dark:border-gray-700 pt-1 mt-1">${objetivoLabel || 'Sin objetivo'}</p>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex gap-2 text-xs">
                                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded" title="Ajuste Calórico"><i class="ph ph-fire mr-1"></i>${item.ajuste_calorico_porc}%</span>
                                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded" title="Días del plan"><i class="ph ph-calendar mr-1"></i>${item.duracion_dias}d</span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-3 px-4 whitespace-nowrap text-sm font-bold ${diffColor}">
                                        ${item.nivel_dificultad}
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        ${activeBadge}
                                    </td>
                                    
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openFormModal(${item.id_plan_catalogo})" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Editar"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(${item.id_plan_catalogo})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">Error de red al cargar los planes.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        async function openFormModal(id = null) {
            currentPlanId = id;
            currentDietas = [];
            renderDietasTable();

            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = id 
                ? '<i class="ph-fill ph-calendar-check text-calori-500 mr-2"></i> Editar Plan Alimenticio' 
                : '<i class="ph-fill ph-calendar-check text-calori-500 mr-2"></i> Nuevo Plan Alimenticio';
            
            document.getElementById('formPlan').reset();
            document.getElementById('dietaSelectDropdown').value = '';
            
            if(id) {
                try {
                    const res = await fetch(`ajax_planes.php?action=get&id=${id}`);
                    const json = await res.json();
                    
                    if(json.success && json.data.data) {
                        const item = json.data.data;
                        
                        document.getElementById('inp_nombre_clave').value = item.nombre_clave || '';
                        document.getElementById('inp_objetivo').value = item.objetivo || '';
                        document.getElementById('inp_ajuste_calorico_porc').value = item.ajuste_calorico_porc || '0.00';
                        document.getElementById('inp_nivel_dificultad').value = item.nivel_dificultad || 'Principiante';
                        document.getElementById('inp_duracion_dias').value = item.duracion_dias || 7;
                        document.getElementById('inp_comidas_por_dia').value = item.comidas_por_dia || 3;
                        document.getElementById('inp_horas_ayuno_recomendadas').value = item.horas_ayuno_recomendadas || 0;
                        document.getElementById('inp_activo').checked = (item.activo == 1);
                        
                        document.getElementById('inp_nombre_es').value = item.nombre_es || '';
                        document.getElementById('inp_descripcion_es').value = item.descripcion_es || '';
                        document.getElementById('inp_nombre_en').value = item.nombre_en || '';
                        document.getElementById('inp_descripcion_en').value = item.descripcion_en || '';

                        // Carga de dietas
                        if(item.dietas && Array.isArray(item.dietas)) {
                            currentDietas = item.dietas.map(d => ({
                                id_dieta: d.id_dieta,
                                nombre: d.nombre_es || d.nombre_clave || 'Dieta ' + d.id_dieta
                            }));
                        } else if (item.id_dieta) { // Retro-compatibilidad si se carga un plan creado antes de este ajuste
                            currentDietas.push({
                                id_dieta: item.id_dieta,
                                nombre: item.dieta_nombre_clave || 'Dieta ' + item.id_dieta
                            });
                        }
                        renderDietasTable();
                    }
                } catch(e) {
                    showToast("Error al cargar los datos del plan.", "error");
                    closeFormModal();
                }
            }

            switchTab('general');
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
            currentPlanId = null;
        }

        async function savePlan(event) {
            event.preventDefault();
            
            const nombreClave = document.getElementById('inp_nombre_clave').value.trim();
            if(!nombreClave) {
                switchTab('general');
                showToast("El nombre clave es obligatorio.", "warning");
                return;
            }

            if(currentDietas.length === 0) {
                switchTab('dietas');
                showToast("Debes asignar al menos una dieta a este plan para poder funcionar.", "warning");
                return;
            }

            const objetivo = document.getElementById('inp_objetivo').value;
            if(!objetivo) {
                switchTab('general');
                showToast("Debes seleccionar un objetivo para este plan.", "warning");
                return;
            }

            const nombreEs = document.getElementById('inp_nombre_es').value.trim();
            if(!nombreEs) {
                switchTab('es');
                showToast("El nombre comercial en Español es obligatorio.", "warning");
                return;
            }

            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            const dietasPayload = currentDietas.map(d => ({
                id_dieta: parseInt(d.id_dieta)
            }));

            const payload = {
                nombre_clave: nombreClave,
                dietas: dietasPayload, // Array de relaciones Dieta-Plan
                objetivo: objetivo,
                ajuste_calorico_porc: parseFloat(document.getElementById('inp_ajuste_calorico_porc').value) || 0,
                nivel_dificultad: document.getElementById('inp_nivel_dificultad').value,
                duracion_dias: parseInt(document.getElementById('inp_duracion_dias').value) || 7,
                comidas_por_dia: parseInt(document.getElementById('inp_comidas_por_dia').value) || 3,
                horas_ayuno_recomendadas: parseInt(document.getElementById('inp_horas_ayuno_recomendadas').value) || 0,
                activo: document.getElementById('inp_activo').checked ? 1 : 0,
                nombre_es: nombreEs,
                descripcion_es: document.getElementById('inp_descripcion_es').value.trim(),
                nombre_en: document.getElementById('inp_nombre_en').value.trim(),
                descripcion_en: document.getElementById('inp_descripcion_en').value.trim()
            };

            const endpoint = currentPlanId ? `ajax_planes.php?action=update&id=${currentPlanId}` : `ajax_planes.php?action=create`;
            
            try {
                const res = await fetch(endpoint, {
                    method: currentPlanId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentPlanId ? "Plan actualizado exitosamente." : "Plan creado exitosamente.", "success");
                    closeFormModal();
                    loadPlanes(currentPage);
                } else {
                    let msg = json.data?.error || json.message || 'Revisa los datos.';
                    showToast("Error API: " + msg, "error");
                }
            } catch(e) {
                showToast("Error de conexión al guardar el plan.", "error");
            } finally {
                btn.innerHTML = 'Guardar Plan';
                btn.disabled = false;
            }
        }

        let deleteId = null;
        function confirmDelete(id) {
            deleteId = id;
            document.getElementById('modalDelete').classList.remove('hidden');
        }
        function closeDeleteModal() {
            deleteId = null;
            document.getElementById('modalDelete').classList.add('hidden');
        }

        async function executeDelete() {
            const btn = document.getElementById('btnConfirmDelete');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Eliminando...';
            btn.disabled = true;

            try {
                const res = await fetch(`ajax_planes.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Plan eliminado correctamente.", "success");
                    closeDeleteModal();
                    loadPlanes(currentPage);
                } else if (json.http_code === 409) {
                    closeDeleteModal();
                    document.getElementById('modalConflict').classList.remove('hidden');
                } else {
                    showToast("No se pudo eliminar el plan.", "error");
                    closeDeleteModal();
                }
            } catch (err) {
                showToast("Error de red al borrar.", "error");
                closeDeleteModal();
            } finally {
                btn.innerHTML = 'Sí, Eliminar';
                btn.disabled = false;
            }
        }

        function closeConflictModal() { 
            document.getElementById('modalConflict').classList.add('hidden');
        }
        
        function searchAction(e) { 
            if(e.key === 'Enter') loadPlanes(1); 
        }
        
        function changePage(delta) {
            const newP = currentPage + delta;
            if(newP >= 1 && newP <= totalPages) loadPlanes(newP);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadDietasForSelect();
            loadPlanes(1);
        });
    </script>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header.php';
?>

    <div class="absolute top-[-10%] left-[20%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[10%] w-[500px] h-[500px] bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none" style="animation-delay: 2s;"></div>

    <div id="globalToast" class="fixed top-8 left-1/2 transform -translate-x-1/2 z-[200] hidden items-center px-6 py-3 rounded-2xl shadow-2xl transition-all duration-300 translate-y-full opacity-0">
        <i id="toastIcon" class=""></i>
        <span id="toastText" class="text-white font-bold text-sm tracking-wide"></span>
    </div>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden z-10 relative">
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4 shrink-0">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Planes</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Estructuración y lógica de los planes alimenticios bilingües basados en 1 o múltiples Dietas.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="searchInput" onkeydown="searchAction(event)" placeholder="Buscar plan..." class="w-full bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white shadow-sm">
                    </div>
                    
                    <button onclick="loadPlanes(1)" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-plus-circle text-lg"></i>
                        Nuevo Plan
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Planes: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Conectando con la base de datos de Planes...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre del Plan</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dietas y Objetivo</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Metadatos</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dificultad</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="planesTableBody" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-white/50 dark:bg-darkbase-900/50 backdrop-blur-md">
                    <span id="pageIndicator" class="text-sm font-bold text-gray-600 dark:text-gray-400">Página 1 de 1</span>
                    <div class="flex items-center gap-3">
                        <button id="prevBtn" onclick="changePage(-1)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-bold disabled:opacity-40 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            <i class="ph ph-caret-left mr-1"></i> Anterior
                        </button>
                        <button id="nextBtn" onclick="changePage(1)" class="px-4 py-2 bg-calori-600 text-white rounded-xl text-sm font-bold disabled:opacity-40 hover:bg-calori-700 transition-colors shadow-lg shadow-calori-600/20">
                            Siguiente <i class="ph ph-caret-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="formModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeFormModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-4xl max-h-[92vh] flex flex-col rounded-3xl shadow-2xl relative">
            
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center shrink-0">
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"><i class="ph-fill ph-calendar-check text-calori-500 mr-2"></i> Nuevo Plan Alimenticio</h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-800 px-6 pt-2 shrink-0 overflow-x-auto scrollbar-hide">
                <button type="button" id="btn-tab-general" onclick="switchTab('general')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 text-calori-600 border-calori-600 whitespace-nowrap">Configuración Core</button>
                <button type="button" id="btn-tab-dietas" onclick="switchTab('dietas')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-blue-700 dark:hover:text-blue-300 whitespace-nowrap"><i class="ph ph-heartbeat mr-1"></i>Dietas Asignadas</button>
                <button type="button" id="btn-tab-es" onclick="switchTab('es')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇲🇽</span> Español</button>
                <button type="button" id="btn-tab-en" onclick="switchTab('en')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇺🇸</span> Inglés</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900/50 relative">
                
                <form id="formPlan" onsubmit="savePlan(event)" novalidate>
                    
                    <div id="tab-general" class="tab-content animate-fade-in space-y-6">
                        
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Información de Negocio</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Clave (Interno)</label>
                                    <input type="text" id="inp_nombre_clave" required placeholder="ej. KETO_ADVANCED_30" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono uppercase">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Objetivo del Plan</label>
                                    <select id="inp_objetivo" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-bold">
                                        <option value="">Selecciona un objetivo...</option>
                                        <option value="ganar_peso">Ganar Peso (Superávit)</option>
                                        <option value="mantener_peso">Mantener Peso (Mantenimiento)</option>
                                        <option value="perder_peso">Perder Peso (Déficit)</option>
                                        <option value="recomposicion">Recomposición Corporal</option>
                                        <option value="rendimiento">Rendimiento Deportivo</option>
                                        <option value="salud_hormonal">Salud Hormonal</option>
                                        <option value="antiflamatorio">Antiinflamatorio</option>
                                        <option value="masa_muscular">Ganar Masa Muscular</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Configuración Metabólica y Tiempos</h4>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ajuste Calórico (%)</label>
                                    <input type="number" step="0.01" id="inp_ajuste_calorico_porc" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dificultad</label>
                                    <select id="inp_nivel_dificultad" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-bold">
                                        <option value="Principiante">Principiante</option>
                                        <option value="Intermedio">Intermedio</option>
                                        <option value="Avanzado">Avanzado</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Duración (Días)</label>
                                    <input type="number" id="inp_duracion_dias" value="7" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Comidas / Día</label>
                                    <input type="number" id="inp_comidas_por_dia" value="3" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hrs Ayuno</label>
                                    <input type="number" id="inp_horas_ayuno_recomendadas" value="0" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-green-50/50 dark:bg-green-900/10 rounded-xl border border-green-100 dark:border-green-900/30">
                            <label class="flex items-center gap-2 cursor-pointer w-fit"><input type="checkbox" id="inp_activo" class="w-5 h-5 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Plan Activo y Disponible Inmediatamente</span></label>
                        </div>
                    </div>

                    <div id="tab-dietas" class="tab-content hidden animate-fade-in space-y-6">
                        <div class="bg-blue-50/30 dark:bg-blue-900/10 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                            <h4 class="font-black text-blue-900 dark:text-blue-400 flex items-center mb-1"><i class="ph-fill ph-magnifying-glass text-xl mr-2"></i> Asignación de Dietas</h4>
                            <p class="text-xs text-blue-600/70 dark:text-blue-400/70 mb-4">Selecciona y agrupa las dietas que darán variedad al plan (ej. combinar Keto con Ayuno en días alternos).</p>
                            
                            <div class="flex flex-col md:flex-row gap-3">
                                <select id="dietaSelectDropdown" class="flex-1 bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                                    <option value="">Cargando catálogo...</option>
                                </select>
                                
                                <button type="button" onclick="addDietaToPlan()" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-md transition-all whitespace-nowrap">
                                    <i class="ph ph-plus font-bold mr-1"></i> Añadir Dieta
                                </button>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-14"></th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dieta Asignada</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Remover</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDietasPlan" class="divide-y divide-gray-100 dark:divide-gray-800">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tab-es" class="tab-content hidden animate-fade-in">
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇲🇽</span><h4 class="font-bold text-gray-900 dark:text-white">Textos Comerciales en Español</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Comercial (es-MX)</label>
                                    <input type="text" id="inp_nombre_es" required placeholder="Plan de Volumen Avanzado G5" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción de la Metodología (es-MX)</label>
                                    <textarea id="inp_descripcion_es" rows="4" placeholder="Diseñado para atletas buscando superávit controlado..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-en" class="tab-content hidden animate-fade-in">
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇺🇸</span><h4 class="font-bold text-gray-900 dark:text-white">Textos Comerciales en Inglés</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Commercial Name (en-US)</label>
                                    <input type="text" id="inp_nombre_en" required placeholder="Advanced Bulking Plan G5" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Methodology Description (en-US)</label>
                                    <textarea id="inp_descripcion_en" rows="4" placeholder="Designed for athletes looking for a controlled surplus..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm -mx-6 px-6 pb-2">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modalDelete" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-white/50 dark:border-gray-700 w-full max-w-sm rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4"><i class="ph ph-trash text-3xl"></i></div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Plan?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esto borrará el plan y sus traducciones de manera permanente (ON DELETE CASCADE).</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 py-3 px-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 transition-colors">Cancelar</button>
                <button id="btnConfirmDelete" onclick="executeDelete()" class="flex-1 py-3 px-4 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 shadow-lg shadow-red-500/30 transition-all">Sí, Eliminar</button>
            </div>
        </div>
    </div>

    <div id="modalConflict" class="hidden fixed inset-0 z-[120] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-orange-900/40 backdrop-blur-sm" onclick="closeConflictModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border-2 border-orange-500 w-full max-w-md rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-orange-500 text-white rounded-full flex items-center justify-center shadow-lg border-4 border-white dark:border-darkbase-900"><i class="ph ph-link-break text-2xl"></i></div>
            <h3 class="text-xl font-black text-gray-900 dark:text-white mt-4 mb-2 uppercase tracking-wide">Restricción de BD</h3>
            <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-xl border border-orange-200 dark:border-orange-800 mb-6">
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400">No puedes eliminar este plan.</p>
                <p class="text-xs text-orange-600/80 mt-2">La base de datos reporta que está siendo utilizado por usuarios activos en la App.</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>