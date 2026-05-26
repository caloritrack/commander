<?php
// Nombre del archivo: planes_v2_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-25
// Versión: 1.3
// Descripción: Interfaz gráfica (CRUD) para los nuevos Planes V2. Se corrigió la generación del payload para enviar strings vacíos ("") en lugar de nulos (null) en textos opcionales, empatando con la validación estricta de la API de Hermes. Se mejoró la captura de valores numéricos en ceros.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Planes V2 | Portal de Héroes Caloritrack';
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
        let dietasCatalogo = []; 
        let currentDietasIds = []; 

        // Diccionario visual para los ENUMs del objetivo
        const enumObjectiveMap = {
            'perder_peso': 'Pérdida de Peso',
            'mantener_peso': 'Mantener Peso',
            'ganar_peso': 'Ganar Peso',
            'recomposicion': 'Recomposición',
            'rendimiento': 'Rendimiento',
            'salud_hormonal': 'Salud Hormonal',
            'antiflamatorio': 'Antiinflamatorio',
            'masa_muscular': 'Masa Muscular'
        };

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
                el.classList.remove('text-calori-600', 'border-calori-600', 'text-blue-600', 'border-blue-600', 'text-purple-600', 'border-purple-600', 'dark:text-calori-400', 'dark:border-calori-400', 'dark:text-blue-400', 'dark:border-blue-400', 'dark:text-purple-400', 'dark:border-purple-400');
                el.classList.add('text-gray-500', 'border-transparent');
            });
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const btn = document.getElementById('btn-tab-' + tabId);
            btn.classList.remove('text-gray-500', 'border-transparent');
            
            if(tabId === 'dietas') {
                btn.classList.add('text-blue-600', 'border-blue-600', 'dark:text-blue-400', 'dark:border-blue-400');
            } else if(tabId === 'ciclado') {
                btn.classList.add('text-purple-600', 'border-purple-600', 'dark:text-purple-400', 'dark:border-purple-400');
            } else {
                btn.classList.add('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
            }
        }

        async function loadDietasForSelect() {
            try {
                const res = await fetch(`ajax_dietas.php?action=list&limit=1000`);
                const json = await res.json();
                const sel = document.getElementById('dietaSelectDropdown');
                
                sel.innerHTML = '<option value="">Selecciona una dieta de la base...</option>';
                if (json.success && json.data && json.data.data) {
                    dietasCatalogo = json.data.data;
                    dietasCatalogo.forEach(dieta => {
                        const nombreVisible = dieta.nombre_es || dieta.nombre_clave;
                        sel.innerHTML += `<option value="${dieta.id_dieta}">${nombreVisible} (ID: ${dieta.id_dieta})</option>`;
                    });
                }
            } catch(e) {
                console.error("Error al cargar las dietas", e);
            }
        }

        function renderDietasTable() {
            const tbody = document.getElementById('tbodyDietasPlan');
            let html = '';

            if(currentDietasIds.length === 0) {
                html = '<tr><td colspan="3" class="text-center py-6 text-sm text-gray-500 font-medium">No has asignado ninguna dieta compatible.</td></tr>';
            } else {
                currentDietasIds.forEach((idDieta, index) => {
                    const dietaEncontrada = dietasCatalogo.find(d => d.id_dieta == idDieta);
                    const nombreVisible = dietaEncontrada ? (dietaEncontrada.nombre_es || dietaEncontrada.nombre_clave) : `Dieta ID: ${idDieta}`;

                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="py-3 px-4 w-14 whitespace-nowrap">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center border border-blue-200 dark:border-blue-800">
                                    <i class="ph ph-heartbeat text-blue-500 dark:text-blue-400 text-xl"></i>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">${nombreVisible}</p>
                                <p class="text-[10px] text-gray-400 font-mono">ID: ${idDieta}</p>
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
            const idDieta = parseInt(selectEl.value);
            
            if(!idDieta) {
                showToast("Por favor selecciona una dieta.", "warning");
                return;
            }

            if(currentDietasIds.includes(idDieta)) {
                showToast("Esta dieta ya está vinculada.", "warning");
                return;
            }

            currentDietasIds.push(idDieta);
            selectEl.value = '';
            renderDietasTable();
            showToast("Dieta añadida.", "success");
        }

        function removeDietaFromPlan(index) {
            currentDietasIds.splice(index, 1);
            renderDietasTable();
        }

        function toggleCicladoFields() {
            const isChecked = document.getElementById('inp_caloric_cycling_active').checked;
            const fieldsContainer = document.getElementById('cicladoFieldsContainer');
            if(isChecked) {
                fieldsContainer.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                fieldsContainer.classList.add('opacity-50', 'pointer-events-none');
            }
        }

        async function loadPlanes(page = 1) {
            const tbody = document.getElementById('planesTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            const searchVal = document.getElementById('searchInput').value.trim();
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            
            try {
                const res = await fetch(`ajax_planes_v2.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchVal)}`);
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
                        html = `<tr><td colspan="7" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-calendar-check text-4xl block mb-2 opacity-50"></i>No se encontraron planes V2.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            const activeBadge = item.is_active == 1 
                                ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Activo</span>`
                                : `<span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded uppercase">Inactivo</span>`;
                            
                            const diffColor = item.difficulty_level === 'Avanzado' ? 'text-red-500' : (item.difficulty_level === 'Intermedio' ? 'text-orange-500' : 'text-green-500');
                            
                            let objetivoLabel = enumObjectiveMap[item.objective] || item.objective || 'Sin objetivo';
                            
                            const cicladoBadge = item.caloric_cycling_active == 1 
                                ? `<i class="ph-fill ph-arrows-clockwise text-purple-500 ml-1" title="Ciclado Calórico Activo"></i>` : '';

                            const dietasArr = item.dietas_compatibles ? item.dietas_compatibles.split(',').map(d => d.trim()) : [];
                            let dietasHtml = '';
                            if (dietasArr.length > 0) {
                                dietasArr.forEach(dId => {
                                    dietasHtml += `<span class="inline-flex items-center text-[10px] text-blue-600 dark:text-blue-400 font-bold mb-1 mr-1 border border-blue-200 dark:border-blue-800 rounded px-1.5 py-0.5 bg-blue-50 dark:bg-blue-900/20"><i class="ph-fill ph-heartbeat mr-1"></i>ID: ${dId}</span>`;
                                });
                            } else {
                                dietasHtml = '<span class="text-[10px] text-red-500 font-bold uppercase border border-red-200 bg-red-50 rounded px-1.5 py-0.5">Sin dietas</span>';
                            }

                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">V2-${item.id_plan_catalogo}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">${item.display_name_es || item.key_name}</p>
                                            ${cicladoBadge}
                                        </div>
                                        <p class="text-[10px] text-gray-500 font-mono uppercase">${item.key_name}</p>
                                    </td>
                                    <td class="py-3 px-4 max-w-[200px]">
                                        <div class="flex flex-wrap mb-1">${dietasHtml}</div>
                                        <p class="text-[11px] text-gray-500 line-clamp-1 border-t border-gray-100 dark:border-gray-700 pt-1 mt-1">${objetivoLabel}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex gap-2 text-xs">
                                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded" title="Ajuste Calórico"><i class="ph ph-fire mr-1"></i>${item.caloric_adjustment_perc}%</span>
                                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded" title="Días del plan"><i class="ph ph-calendar mr-1"></i>${item.duration_days}d</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm font-bold ${diffColor}">${item.difficulty_level || 'N/A'}</td>
                                    <td class="py-3 px-4 whitespace-nowrap">${activeBadge}</td>
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
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">Error de red al cargar.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        async function openFormModal(id = null) {
            currentPlanId = id;
            currentDietasIds = [];
            renderDietasTable();

            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = id 
                ? '<i class="ph-fill ph-rocket-launch text-calori-500 mr-2"></i> Editar Plan Algorítmico V2' 
                : '<i class="ph-fill ph-rocket-launch text-calori-500 mr-2"></i> Nuevo Plan Algorítmico V2';
            
            document.getElementById('formPlan').reset();
            document.getElementById('dietaSelectDropdown').value = '';
            toggleCicladoFields();
            
            if(id) {
                try {
                    const res = await fetch(`ajax_planes_v2.php?action=get&id=${id}`);
                    const json = await res.json();
                    
                    if(json.success && json.data.data) {
                        const item = json.data.data;
                        document.getElementById('inp_key_name').value = item.key_name || '';
                        document.getElementById('inp_objective').value = item.objective || '';
                        document.getElementById('inp_difficulty_level').value = item.difficulty_level || 'Principiante';
                        document.getElementById('inp_is_active').checked = (item.is_active == 1);
                        
                        document.getElementById('inp_duration_days').value = item.duration_days || 7;
                        document.getElementById('inp_meals_per_day').value = item.meals_per_day || 3;
                        document.getElementById('inp_recommended_fasting_hours').value = item.recommended_fasting_hours || 0;
                        
                        document.getElementById('inp_caloric_adjustment_perc').value = item.caloric_adjustment_perc || '0';
                        document.getElementById('inp_caloric_cycling_active').checked = (item.caloric_cycling_active == 1);
                        document.getElementById('inp_objective_phase_days').value = item.objective_phase_days || 0;
                        document.getElementById('inp_rest_phase_days').value = item.rest_phase_days || 0;
                        document.getElementById('inp_rest_caloric_adjustment_perc').value = item.rest_caloric_adjustment_perc || '0';
                        
                        document.getElementById('inp_display_name_es').value = item.display_name_es || '';
                        document.getElementById('inp_description_es').value = item.description_es || '';
                        document.getElementById('inp_display_name_en').value = item.display_name_en || '';
                        document.getElementById('inp_description_en').value = item.description_en || '';
                        
                        if(item.dietas && Array.isArray(item.dietas)) {
                            currentDietasIds = [...item.dietas];
                        }
                        renderDietasTable();
                        toggleCicladoFields();
                    }
                } catch(e) {
                    showToast("Error al cargar los datos.", "error");
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
            const keyName = document.getElementById('inp_key_name').value.trim();
            if(!keyName) {
                switchTab('general');
                showToast("El key_name es obligatorio.", "warning");
                return;
            }

            const objetivoVal = document.getElementById('inp_objective').value;
            if(!objetivoVal) {
                switchTab('general');
                showToast("El objetivo del plan es obligatorio.", "warning");
                return;
            }

            if(currentDietasIds.length === 0) {
                switchTab('dietas');
                showToast("Debes asignar al menos una dieta al plan.", "warning");
                return;
            }

            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            // Función ultra-segura para capturar números evitando que "0" se evalúe como falsy
            const getFloat = (id, def) => { const v = parseFloat(document.getElementById(id).value); return isNaN(v) ? def : v; };
            const getInt = (id, def) => { const v = parseInt(document.getElementById(id).value, 10); return isNaN(v) ? def : v; };

            const payload = {
                key_name: keyName,
                objective: objetivoVal,
                caloric_adjustment_perc: getFloat('inp_caloric_adjustment_perc', 0),
                caloric_cycling_active: document.getElementById('inp_caloric_cycling_active').checked ? 1 : 0,
                objective_phase_days: getInt('inp_objective_phase_days', 0),
                rest_phase_days: getInt('inp_rest_phase_days', 0),
                rest_caloric_adjustment_perc: getFloat('inp_rest_caloric_adjustment_perc', 0),
                difficulty_level: document.getElementById('inp_difficulty_level').value,
                duration_days: getInt('inp_duration_days', 28),
                meals_per_day: getInt('inp_meals_per_day', 4),
                recommended_fasting_hours: getInt('inp_recommended_fasting_hours', 12),
                is_active: document.getElementById('inp_is_active').checked ? 1 : 0,
                // KR-FIX: Dejamos el string intacto (vacío si no hay nada), NO enviamos 'null'
                display_name_es: document.getElementById('inp_display_name_es').value.trim(),
                description_es: document.getElementById('inp_description_es').value.trim(),
                display_name_en: document.getElementById('inp_display_name_en').value.trim(),
                description_en: document.getElementById('inp_description_en').value.trim(),
                dietas: currentDietasIds
            };

            const endpoint = currentPlanId ? `ajax_planes_v2.php?action=update&id=${currentPlanId}` : `ajax_planes_v2.php?action=create`;
            
            try {
                const res = await fetch(endpoint, {
                    method: currentPlanId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentPlanId ? "Plan V2 actualizado." : "Plan V2 creado exitosamente.", "success");
                    closeFormModal();
                    loadPlanes(currentPage);
                } else if(json.http_code === 409) {
                    showToast("Atención: El key_name ya existe en la base de datos.", "warning");
                } else {
                    let msg = json.data?.error || json.message || 'Revisa los campos obligatorios.';
                    showToast("Error API: " + msg, "error");
                }
            } catch(e) {
                showToast("Error de red.", "error");
            } finally {
                btn.innerHTML = 'Guardar Plan V2';
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
                const res = await fetch(`ajax_planes_v2.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Plan V2 eliminado correctamente.", "success");
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

        function closeConflictModal() { document.getElementById('modalConflict').classList.add('hidden'); }
        function searchAction(e) { if(e.key === 'Enter') loadPlanes(1); }
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
                    <div class="flex items-center gap-2">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Planes V2</h1>
                        <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-lg uppercase">Algorítmicos</span>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Estructuración inteligente con soporte para ciclado calórico y traducciones paralelas.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="searchInput" onkeydown="searchAction(event)" placeholder="Buscar por key o nombre..." class="w-full bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white shadow-sm">
                    </div>
                    <button onclick="loadPlanes(1)" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-rocket-launch text-lg"></i>
                        Nuevo Plan V2
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Planes V2: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Buscando nueva arquitectura...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre del Plan</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dietas y Objetivo</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ajustes Base</th>
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
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"></h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-800 px-6 pt-2 shrink-0 overflow-x-auto scrollbar-hide">
                <button type="button" id="btn-tab-general" onclick="switchTab('general')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 text-calori-600 border-calori-600 whitespace-nowrap">Configuración Core</button>
                <button type="button" id="btn-tab-ciclado" onclick="switchTab('ciclado')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-purple-700 dark:hover:text-purple-300 whitespace-nowrap"><i class="ph ph-arrows-clockwise mr-1"></i>Ciclo Calórico</button>
                <button type="button" id="btn-tab-dietas" onclick="switchTab('dietas')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-blue-700 dark:hover:text-blue-300 whitespace-nowrap"><i class="ph ph-heartbeat mr-1"></i>Dietas Asignadas</button>
                <button type="button" id="btn-tab-es" onclick="switchTab('es')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇲🇽</span> Textos ES</button>
                <button type="button" id="btn-tab-en" onclick="switchTab('en')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇺🇸</span> Textos EN</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900/50 relative">
                
                <form id="formPlan" onsubmit="savePlan(event)" novalidate>
                    
                    <div id="tab-general" class="tab-content animate-fade-in space-y-6">
                        
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Información Base de Algoritmo</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">key_name (Identificador)</label>
                                    <input type="text" id="inp_key_name" required placeholder="ej. plan_deficit_moderado_v2" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Objetivo del Plan</label>
                                    <select id="inp_objective" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-bold">
                                        <option value="">Selecciona un objetivo...</option>
                                        <option value="perder_peso">Pérdida de Peso</option>
                                        <option value="mantener_peso">Mantener Peso</option>
                                        <option value="ganar_peso">Ganar Peso</option>
                                        <option value="recomposicion">Recomposición Corporal</option>
                                        <option value="rendimiento">Rendimiento Físico</option>
                                        <option value="salud_hormonal">Salud Hormonal</option>
                                        <option value="antiflamatorio">Antiinflamatorio</option>
                                        <option value="masa_muscular">Masa Muscular</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dificultad</label>
                                    <select id="inp_difficulty_level" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-bold">
                                        <option value="Principiante">Principiante</option>
                                        <option value="Intermedio">Intermedio</option>
                                        <option value="Avanzado">Avanzado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Tiempos y Comidas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Duración (Días)</label>
                                    <input type="number" id="inp_duration_days" value="28" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Comidas / Día</label>
                                    <input type="number" id="inp_meals_per_day" value="4" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hrs Ayuno Rec.</label>
                                    <input type="number" id="inp_recommended_fasting_hours" value="12" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono text-center">
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-green-50/50 dark:bg-green-900/10 rounded-xl border border-green-100 dark:border-green-900/30">
                            <label class="flex items-center gap-2 cursor-pointer w-fit"><input type="checkbox" id="inp_is_active" class="w-5 h-5 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Plan V2 Activo y Disponible Inmediatamente</span></label>
                        </div>
                    </div>

                    <div id="tab-ciclado" class="tab-content hidden animate-fade-in space-y-6">
                        
                        <div class="bg-purple-50/30 dark:bg-purple-900/10 p-5 rounded-xl border border-purple-200 dark:border-purple-800/50">
                            <h4 class="font-black text-purple-800 dark:text-purple-300 mb-4 flex items-center"><i class="ph-fill ph-fire mr-2"></i> Ajuste Calórico Principal</h4>
                            <p class="text-xs text-purple-600 dark:text-purple-400 mb-4">El porcentaje a restar o sumar del TDEE base del usuario. Usa negativos para déficit.</p>
                            
                            <div class="w-1/2">
                                <label class="block text-xs font-bold text-purple-700 dark:text-purple-400 uppercase mb-1">Ajuste % (Base/Fase Objetivo)</label>
                                <input type="number" step="0.01" id="inp_caloric_adjustment_perc" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-purple-200 dark:border-purple-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none dark:text-white font-bold">
                            </div>
                        </div>

                        <div class="p-4 bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer w-fit" onchange="toggleCicladoFields()">
                                <input type="checkbox" id="inp_caloric_cycling_active" class="w-5 h-5 text-purple-600 rounded">
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Habilitar Ciclado Calórico Avanzado (Días trampa / Descanso)</span>
                            </label>
                        </div>

                        <div id="cicladoFieldsContainer" class="bg-gray-50 dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 opacity-50 pointer-events-none transition-opacity">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">Configuración de las Fases del Ciclo</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Días Fase Objetivo</label>
                                    <input type="number" id="inp_objective_phase_days" value="5" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none dark:text-white font-mono text-center" title="Días consecutivos aplicando el ajuste principal.">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Días Fase Descanso</label>
                                    <input type="number" id="inp_rest_phase_days" value="2" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none dark:text-white font-mono text-center" title="Días consecutivos de descanso (ej. fin de semana).">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ajuste Fase Descanso (%)</label>
                                    <input type="number" step="0.01" id="inp_rest_caloric_adjustment_perc" value="0.00" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none dark:text-white font-mono text-center" title="Normalmente 0 (Mantenimiento) en días de descanso.">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-dietas" class="tab-content hidden animate-fade-in space-y-6">
                        <div class="bg-blue-50/30 dark:bg-blue-900/10 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                            <h4 class="font-black text-blue-900 dark:text-blue-400 flex items-center mb-1"><i class="ph-fill ph-magnifying-glass text-xl mr-2"></i> Asignación de Dietas (Array de Enteros)</h4>
                            <p class="text-xs text-blue-600/70 dark:text-blue-400/70 mb-4">Selecciona las dietas del catálogo para integrarlas en el algoritmo V2.</p>
                            
                            <div class="flex flex-col md:flex-row gap-3">
                                <select id="dietaSelectDropdown" class="flex-1 bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                                    <option value="">Cargando catálogo...</option>
                                </select>
                                <button type="button" onclick="addDietaToPlan()" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-md transition-all whitespace-nowrap">
                                    <i class="ph ph-plus font-bold mr-1"></i> Añadir ID
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
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Comercial (display_name_es)</label>
                                    <input type="text" id="inp_display_name_es" required placeholder="Plan Déficit Moderado" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción del Plan (description_es)</label>
                                    <textarea id="inp_description_es" rows="4" placeholder="Plan estructurado para reducir grasa de manera balanceada..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-en" class="tab-content hidden animate-fade-in">
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇺🇸</span><h4 class="font-bold text-gray-900 dark:text-white">Textos Comerciales en Inglés</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Commercial Name (display_name_en)</label>
                                    <input type="text" id="inp_display_name_en" required placeholder="Moderate Deficit Plan" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Plan Description (description_en)</label>
                                    <textarea id="inp_description_en" rows="4" placeholder="Structured plan to reduce fat in a balanced way..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm -mx-6 px-6 pb-2">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Plan V2
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
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Plan V2?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esta acción borrará de forma permanente el plan y todas sus traducciones. ¿Proceder?</p>
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
                <p class="text-xs text-orange-600/80 mt-2">La base de datos reporta que está siendo utilizado por usuarios activos en la App. La protección referencial impidió el borrado.</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>