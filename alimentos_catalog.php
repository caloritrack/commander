<?php
// Nombre del archivo: alimentos_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-25
// Versión: 1.5
// Descripción: Se reubicó el campo booleano 'es_lacteo' a la pestaña de Alérgenos. Además, se actualizó la lógica de la tabla para que este campo dispare la alerta visual de restricción dietética.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Alimentos | Portal de Héroes Caloritrack';
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
        
        /* Ocultar input number arrows */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentAlimentoId = null;
        
        // Variables para Equivalencias
        let currentEquivId = null;
        let cacheUnidades = [];

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

        // Sistema de Pestañas del Modal
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
                el.classList.add('text-gray-500', 'border-transparent');
            });
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const btn = document.getElementById('btn-tab-' + tabId);
            btn.classList.remove('text-gray-500', 'border-transparent');
            btn.classList.add('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
        }

        // --- Carga de Tabla (Lectura Indexada) ---
        async function loadAlimentos(page = 1) {
            const tbody = document.getElementById('alimentosTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            const searchVal = document.getElementById('searchInput').value.trim();
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            try {
                const res = await fetch(`ajax_alimentos.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchVal)}`);
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
                    }

                    let html = '';
                    if(data.length === 0) {
                        html = `<tr><td colspan="7" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-apple-logo text-4xl block mb-2 opacity-50"></i>No se encontraron alimentos.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            const publicBadge = item.es_publico 
                                ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Global</span>`
                                : `<span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded uppercase">Oculto</span>`;
                                
                            const certBadge = item.certificado_caloritrack
                                ? `<i class="ph-fill ph-seal-check text-calori-500 text-lg ml-1" title="Certificado CaloriTrack"></i>` : '';

                            // KR-FIX: Agregamos item.es_lacteo para que dispare el badge de alérgenos
                            const hasAllergens = item.contains_gluten || item.contains_egg || item.contains_tree_nut || item.contains_peanut || item.contains_soy || item.contains_shellfish || item.contains_fish || item.is_pork || item.es_lacteo;
                            
                            const alergenosBadge = hasAllergens 
                                ? `<i class="ph-fill ph-warning-circle text-orange-500 text-lg ml-1" title="Contiene alérgenos o restricciones (Revisar Ficha)"></i>` : '';

                            // Evaluamos unidad base (por defecto gramos)
                            const baseUnit = item.measurement_unit || 'g';

                            let imgSrc = '';
                            if (item.url_imagen && item.url_imagen.trim() !== '') {
                                imgSrc = item.url_imagen.startsWith('http') ? item.url_imagen : 'https://api.caloritrack.com/foodimages/food/' + item.url_imagen;
                            }
                            
                            const imgHtml = imgSrc 
                                ? `<img src="${imgSrc}" alt="IMG" class="w-full h-full object-cover" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\\\'ph ph-image-broken text-gray-400 text-2xl\\\'></i>';">` 
                                : `<i class="ph ph-image text-gray-400 text-2xl"></i>`;

                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">${item.id_alimento}</td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0 bg-gray-100 flex items-center justify-center">
                                            ${imgHtml}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">${item.nombre_es || 'Sin Nombre'}</p>
                                            ${certBadge} ${alergenosBadge}
                                        </div>
                                        <p class="text-xs text-gray-500 italic">${item.nombre_en || 'No translation'}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">${item.calorias_100g} <span class="text-[10px] font-normal">kcal / 100${baseUnit}</span></p>
                                        <div class="flex gap-1 text-[10px] text-gray-500 mt-0.5">
                                            <span title="Carbs">C:${item.carbohidratos}g</span>|
                                            <span title="Proteína">P:${item.proteinas}g</span>|
                                            <span title="Grasa">G:${item.grasas_totales}g</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">${item.marca || 'N/A'}</td>
                                    <td class="py-3 px-4 whitespace-nowrap">${publicBadge}</td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openFormModal(${item.id_alimento})" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Editar / Equivalencias"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(${item.id_alimento})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-red-500">Error de red.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        // --- Manejo del Formulario Principal (Alimento) ---
        async function openFormModal(id = null) {
            currentAlimentoId = id;
            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerText = id ? 'Editar Alimento' : 'Nuevo Alimento Maestro';
            document.getElementById('formAlimento').reset();
            switchTab('general'); // Regresa a la pestaña principal por defecto
            
            // Lógica UX de la pestaña Equivalencias
            if (id) {
                document.getElementById('msgEquivNew').classList.add('hidden');
                document.getElementById('btnShowAddEquiv').classList.remove('hidden');
                document.getElementById('tablaEquivContainer').classList.remove('hidden');
                loadUnidadesForSelect();
                loadEquivalencias(id);
            } else {
                document.getElementById('msgEquivNew').classList.remove('hidden');
                document.getElementById('btnShowAddEquiv').classList.add('hidden');
                document.getElementById('tablaEquivContainer').classList.add('hidden');
                hideEquivForm();
            }

            if(id) {
                try {
                    const res = await fetch(`ajax_alimentos.php?action=get&id=${id}`);
                    const json = await res.json();
                    if(json.success && json.data.data) {
                        const item = json.data.data;
                        for(let key in item) {
                            let input = document.getElementById('inp_' + key);
                            if(input) {
                                if(input.type === 'checkbox') input.checked = (item[key] == 1);
                                else input.value = item[key];
                            }
                        }
                    }
                } catch(e) {
                    showToast("Error al cargar los datos del alimento.", "error");
                    closeFormModal();
                }
            }
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
            currentAlimentoId = null;
        }

        async function saveAlimento(event) {
            event.preventDefault();
            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            let payload = {};
            document.querySelectorAll('#formAlimento input, #formAlimento select, #formAlimento textarea').forEach(el => {
                if(el.id && el.id.startsWith('inp_')) {
                    const key = el.id.replace('inp_', '');
                    if(el.type === 'checkbox') payload[key] = el.checked ? 1 : 0;
                    else if (el.type === 'number') payload[key] = parseFloat(el.value) || 0;
                    else payload[key] = el.value;
                }
            });

            const endpoint = currentAlimentoId ? `ajax_alimentos.php?action=update&id=${currentAlimentoId}` : `ajax_alimentos.php?action=create`;
            
            try {
                const res = await fetch(endpoint, {
                    method: currentAlimentoId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentAlimentoId ? "Alimento actualizado." : "Alimento creado exitosamente.", "success");
                    closeFormModal();
                    loadAlimentos(currentPage);
                } else {
                    showToast("Error API: " + (json.data?.message || json.message || 'Verifica los datos'), "error");
                }
            } catch(e) {
                showToast("Error de conexión al guardar.", "error");
            } finally {
                btn.innerHTML = 'Guardar Alimento';
                btn.disabled = false;
            }
        }

        // ==========================================
        //  LÓGICA DE GESTIÓN DE EQUIVALENCIAS
        // ==========================================
        async function loadUnidadesForSelect() {
            if (cacheUnidades.length > 0) return; // Si ya lo cargamos, lo reusamos (Performance)
            try {
                const res = await fetch('ajax_unidades.php?action=list');
                const json = await res.json();
                if (json.success && json.data) {
                    cacheUnidades = json.data.data || [];
                    const sel = document.getElementById('sel_unidad_equiv');
                    sel.innerHTML = '<option value="">Selecciona unidad...</option>';
                    cacheUnidades.forEach(u => {
                        sel.innerHTML += `<option value="${u.id_unidad}">${u.nombre_clave} (${u.simbolo})</option>`;
                    });
                }
            } catch(e) { console.error('Error al poblar listado de unidades', e); }
        }

        async function loadEquivalencias(idAlimento) {
            const tbody = document.getElementById('tbodyEquivalencias');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><i class="ph ph-spinner animate-spin text-calori-500 text-xl"></i></td></tr>';
            hideEquivForm();

            try {
                const res = await fetch(`ajax_equivalencias.php?action=list&id_alimento=${idAlimento}`);
                const json = await res.json();
                
                if (json.success) {
                    const equivs = json.data || [];
                    let html = '';
                    if(equivs.length === 0) {
                        html = '<tr><td colspan="4" class="text-center py-8 text-sm text-gray-500 font-medium">Este alimento no tiene medidas asignadas.</td></tr>';
                    } else {
                        equivs.forEach(eq => {
                            html += `
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="py-3 px-4 text-sm font-bold text-gray-700 dark:text-gray-300 capitalize">${eq.nombre_clave}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-calori-50 dark:bg-calori-900/30 text-calori-600 dark:text-calori-400 font-bold rounded-lg border border-calori-100 dark:border-calori-800 text-xs">${eq.simbolo}</span>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-400 font-mono">${eq.gramos_equivalentes} g/ml</td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" onclick="editEquivalencia(${eq.id_equivalencia}, ${eq.id_unidad}, ${eq.gramos_equivalentes})" class="p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Editar"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button type="button" onclick="deleteEquivalencia(${eq.id_equivalencia})" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch(e) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-sm text-red-500">Error leyendo de Hermes.</td></tr>';
            }
        }

        function showEquivForm() {
            currentEquivId = null;
            document.getElementById('formEquivalencia').reset();
            document.getElementById('sel_unidad_equiv').disabled = false; // Se puede seleccionar unidad porque es nuevo
            document.getElementById('formEquivalenciaContainer').classList.remove('hidden');
            document.getElementById('btnShowAddEquiv').classList.add('hidden');
        }

        function editEquivalencia(idEquiv, idUnidad, gramos) {
            currentEquivId = idEquiv;
            document.getElementById('sel_unidad_equiv').value = idUnidad;
            document.getElementById('sel_unidad_equiv').disabled = true; // Hermes dicta que no se cambia el ID en PUT
            document.getElementById('inp_gramos_equiv').value = gramos;
            document.getElementById('formEquivalenciaContainer').classList.remove('hidden');
            document.getElementById('btnShowAddEquiv').classList.add('hidden');
        }

        function hideEquivForm() {
            document.getElementById('formEquivalenciaContainer').classList.add('hidden');
            document.getElementById('btnShowAddEquiv').classList.remove('hidden');
        }

        async function saveEquivalencia(event) {
            event.preventDefault();
            const btn = document.getElementById('btnSaveEquiv');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i>';
            btn.disabled = true;

            const idUnidad = document.getElementById('sel_unidad_equiv').value;
            const gramos = document.getElementById('inp_gramos_equiv').value;

            const payload = currentEquivId 
                ? { gramos_equivalentes: parseFloat(gramos) } // Si edita, solo mandamos los gramos
                : { id_unidad: parseInt(idUnidad), gramos_equivalentes: parseFloat(gramos) }; // Si es nuevo, mandamos unidad y gramos

            const endpoint = currentEquivId 
                ? `ajax_equivalencias.php?action=update&id_alimento=${currentAlimentoId}&id_equivalencia=${currentEquivId}` 
                : `ajax_equivalencias.php?action=create&id_alimento=${currentAlimentoId}`;

            try {
                const res = await fetch(endpoint, {
                    method: currentEquivId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentEquivId ? "Equivalencia actualizada." : "Equivalencia guardada.", "success");
                    loadEquivalencias(currentAlimentoId); // Recargamos sub-tabla
                } else if (json.http_code === 409) {
                    showToast("Atención: Esa unidad ya está asignada a este alimento.", "warning");
                } else {
                    showToast("Error: " + (json.data?.message || 'Revisa los datos enviados.'), "error");
                }
            } catch (error) {
                showToast("Error de red al guardar equivalencia.", "error");
            } finally {
                btn.innerHTML = 'Guardar';
                btn.disabled = false;
            }
        }

        async function deleteEquivalencia(idEquiv) {
            if (!confirm('¿Estás seguro de eliminar esta medida del alimento?')) return;
            try {
                const res = await fetch(`ajax_equivalencias.php?action=delete&id_alimento=${currentAlimentoId}&id_equivalencia=${idEquiv}`, { method: 'DELETE' });
                const json = await res.json();
                
                if (json.success || json.http_code === 200) {
                    showToast("Medida eliminada.", "success");
                    loadEquivalencias(currentAlimentoId);
                } else {
                    showToast("No se pudo eliminar la medida.", "error");
                }
            } catch (err) {
                showToast("Error de red.", "error");
            }
        }
        // ==========================================


        // --- Eliminar Alimento (Protección Relacional) ---
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
                const res = await fetch(`ajax_alimentos.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Alimento borrado permanentemente.", "success");
                    closeDeleteModal();
                    loadAlimentos(currentPage);
                } else if (json.http_code === 409) {
                    closeDeleteModal();
                    document.getElementById('modalConflict').classList.remove('hidden');
                } else {
                    showToast("No se pudo eliminar el registro.", "error");
                    closeDeleteModal();
                }
            } catch (err) {
                showToast("Error de red.", "error");
                closeDeleteModal();
            } finally {
                btn.innerHTML = 'Sí, Eliminar';
                btn.disabled = false;
            }
        }

        function closeConflictModal() { document.getElementById('modalConflict').classList.add('hidden'); }
        function searchAction(e) { if(e.key === 'Enter') loadAlimentos(1); }
        function changePage(delta) {
            const newP = currentPage + delta;
            if(newP >= 1 && newP <= totalPages) loadAlimentos(newP);
        }

        document.addEventListener('DOMContentLoaded', () => loadAlimentos(1));
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
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo Maestro</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Gestión global de alimentos, nutrientes y anti-nutrientes (Bilingüe).</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="searchInput" onkeydown="searchAction(event)" placeholder="Buscar alimento o marca..." class="w-full bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white shadow-sm">
                    </div>
                    <button onclick="loadAlimentos(1)" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-plus-circle text-lg"></i>
                        Nuevo Alimento
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Registros Globales: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Buscando en la base de datos nutricional...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Img</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre (ES / EN)</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Porción Base (100g/ml)</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Marca</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Visibilidad</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="alimentosTableBody" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
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
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl relative">
            
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center shrink-0">
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"><i class="ph-fill ph-apple-logo text-calori-500 mr-2"></i> Nuevo Alimento</h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-800 px-6 pt-2 shrink-0 overflow-x-auto scrollbar-hide">
                <button type="button" id="btn-tab-general" onclick="switchTab('general')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 text-calori-600 border-calori-600 whitespace-nowrap">General / Bilingüe</button>
                <button type="button" id="btn-tab-macros" onclick="switchTab('macros')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap">Macro & Info</button>
                <button type="button" id="btn-tab-vitaminas" onclick="switchTab('vitaminas')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap">Vitaminas</button>
                <button type="button" id="btn-tab-minerales" onclick="switchTab('minerales')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap">Minerales</button>
                <button type="button" id="btn-tab-anti" onclick="switchTab('anti')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap">Antinutrientes</button>
                <button type="button" id="btn-tab-alergenos" onclick="switchTab('alergenos')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><i class="ph-fill ph-warning-circle mr-1"></i>Alérgenos</button>
                <button type="button" id="btn-tab-equiv" onclick="switchTab('equiv')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 whitespace-nowrap"><i class="ph ph-scales mr-1"></i>Equivalencias</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900/50 relative">
                <div id="tab-equiv" class="tab-content hidden animate-fade-in space-y-6">
                    <div class="bg-blue-50/30 dark:bg-blue-900/10 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h4 class="font-black text-blue-900 dark:text-blue-400 flex items-center"><i class="ph-fill ph-scales text-xl mr-2"></i> Medidas y Porciones</h4>
                                <p class="text-xs text-blue-600/70 dark:text-blue-400/70 mt-1">Asigna unidades de medida y su conversión en gramos.</p>
                            </div>
                            <button type="button" id="btnShowAddEquiv" onclick="showEquivForm()" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 shadow-lg shadow-blue-600/30 transition-all hidden">
                                <i class="ph ph-plus-circle mr-1"></i> Nueva Medida
                            </button>
                        </div>

                        <div id="msgEquivNew" class="p-6 bg-white dark:bg-darkbase-800 rounded-xl text-center border border-dashed border-blue-300 dark:border-blue-700">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-3"><i class="ph ph-lock-key text-xl"></i></div>
                            <h5 class="text-sm font-bold text-gray-800 dark:text-gray-200">Sección Bloqueada</h5>
                            <p class="text-xs text-gray-500 mt-1">Guarda la información general del alimento maestro primero para poder asignarle equivalencias en esta sección.</p>
                        </div>

                        <div id="formEquivalenciaContainer" class="hidden bg-white dark:bg-darkbase-800 p-5 rounded-xl shadow-sm border border-blue-200 dark:border-blue-800 mb-6">
                            <form id="formEquivalencia" onsubmit="saveEquivalencia(event)" class="flex flex-col md:flex-row md:items-end gap-4">
                                <div class="flex-1">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Unidad de Medida</label>
                                    <select id="sel_unidad_equiv" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white transition-colors">
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Equivalencia en Gramos (g)</label>
                                    <input type="number" step="0.01" id="inp_gramos_equiv" placeholder="Ej. 15.50" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white transition-colors">
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="hideEquivForm()" class="px-5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold rounded-lg text-sm hover:bg-gray-200 transition-colors">Cancelar</button>
                                    <button type="submit" id="btnSaveEquiv" class="px-5 py-2 bg-blue-600 text-white font-bold rounded-lg text-sm hover:bg-blue-700 shadow-lg shadow-blue-600/30 transition-all flex items-center">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div id="tablaEquivContainer" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 hidden bg-white dark:bg-darkbase-800 shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unidad Maestro</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Símbolo</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Conversión (g)</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyEquivalencias" class="divide-y divide-gray-100 dark:divide-gray-800">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <form id="formAlimento" onsubmit="saveAlimento(event)">
                    
                    <div id="tab-general" class="tab-content space-y-6 animate-fade-in">
                        <div class="bg-white dark:bg-darkbase-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-3"><span class="text-xl">🇲🇽</span><h4 class="font-bold text-gray-900 dark:text-white">Datos en Español (es-MX)</h4></div>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre</label>
                                    <input type="text" id="inp_nombre_es" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Características (Beneficios UX)</label>
                                    <textarea id="inp_caracteristicas_es" rows="2" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-3"><span class="text-xl">🇺🇸</span><h4 class="font-bold text-gray-900 dark:text-white">Datos en Inglés (en-US)</h4></div>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                                    <input type="text" id="inp_nombre_en" required class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Characteristics</label>
                                    <textarea id="inp_caracteristicas_en" rows="2" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">URL Imagen (CDN) / Ruta</label>
                                <input type="text" id="inp_url_imagen" placeholder="URL o ruta de la imagen..." class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Alt Text Imagen</label>
                                <input type="text" id="inp_alt_text_imagen" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Marca (Opcional)</label>
                                <input type="text" id="inp_marca" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-calori-600 dark:text-calori-500 uppercase mb-1">Unidad Base</label>
                                <select id="inp_measurement_unit" required class="w-full bg-white dark:bg-darkbase-800 border border-calori-200 dark:border-calori-800 rounded-lg px-3 py-2 text-sm outline-none dark:text-white font-bold text-calori-700 dark:text-calori-400 focus:ring-2 focus:ring-calori-500">
                                    <option value="g">Gramos (g)</option>
                                    <option value="ml">Mililitros (ml)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-900/30">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_es_publico" class="w-4 h-4 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Público (Global)</span></label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_permitido" class="w-4 h-4 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Permitido en Dieta</span></label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_certificado_caloritrack" class="w-4 h-4 text-calori-600 rounded"><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Sello Certificado</span></label>
                        </div>
                    </div>

                    <div id="tab-macros" class="tab-content hidden animate-fade-in space-y-6">
                        <div class="bg-orange-50/50 dark:bg-orange-900/10 p-4 rounded-xl border border-orange-100 dark:border-orange-900/30">
                            <h4 class="font-black text-orange-600 dark:text-orange-400 mb-3 border-b border-orange-200 dark:border-orange-800/50 pb-2">Valores calculados por base de 100 (g o ml)</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Calorías (kcal)</label><input type="number" step="0.01" id="inp_calorias_100g" required class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm font-bold outline-none dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Carbohidratos (g)</label><input type="number" step="0.01" id="inp_carbohidratos" required class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Proteínas (g)</label><input type="number" step="0.01" id="inp_proteinas" required class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Grasas Totales (g)</label><input type="number" step="0.01" id="inp_grasas_totales" required class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                                
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Macro Principal</label>
                                    <select id="inp_macro_principal" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm font-bold text-calori-600 outline-none">
                                        <option value="Proteina">Proteína</option>
                                        <option value="Carbohidrato">Carbohidrato</option>
                                        <option value="Grasa">Grasa</option>
                                    </select>
                                </div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Azúcar (g)</label><input type="number" step="0.01" id="inp_azucar" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Fibra (g)</label><input type="number" step="0.01" id="inp_fibra" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Índice Glicémico</label><input type="number" step="0.01" id="inp_indice_glicemico" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            </div>
                        </div>

                        <div class="bg-yellow-50/50 dark:bg-yellow-900/10 p-4 rounded-xl border border-yellow-100 dark:border-yellow-900/30">
                            <h4 class="font-bold text-yellow-600 dark:text-yellow-400 mb-3 border-b border-yellow-200 dark:border-yellow-800/50 pb-2 text-sm">Grasas Específicas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                                <div><label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Saturadas (g)</label><input type="number" step="0.01" id="inp_grasas_saturadas" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Poliinsaturadas</label><input type="number" step="0.01" id="inp_grasas_poliinsaturadas" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Monoinsaturadas</label><input type="number" step="0.01" id="inp_grasas_monoinsaturadas" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Trans (g)</label><input type="number" step="0.01" id="inp_grasas_trans" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 text-sm outline-none dark:text-white"></div>
                                <div><label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Colesterol (mg)</label><input type="number" step="0.01" id="inp_colesterol_mg" value="0.00" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 text-sm outline-none dark:text-white"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 p-4 bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_es_procesado" class="w-4 h-4 rounded text-orange-500"><span class="text-sm font-medium dark:text-white">Procesado</span></label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_es_ultraprocesado" class="w-4 h-4 rounded text-red-500"><span class="text-sm font-medium dark:text-white text-red-600 dark:text-red-400 font-bold">Ultraprocesado</span></label>
                        </div>
                    </div>

                    <div id="tab-vitaminas" class="tab-content hidden animate-fade-in">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit A (mcg)</label><input type="number" step="0.01" id="inp_vitamina_a_mcg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit D (mcg)</label><input type="number" step="0.01" id="inp_vitamina_d_mcg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit E (mg)</label><input type="number" step="0.01" id="inp_vitamina_e_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit K (mcg)</label><input type="number" step="0.01" id="inp_vitamina_k_mcg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit C (mg)</label><input type="number" step="0.01" id="inp_vitamina_c_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B1 Tiamina (mg)</label><input type="number" step="0.01" id="inp_vitamina_b1_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B2 Ribofla. (mg)</label><input type="number" step="0.01" id="inp_vitamina_b2_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B3 Niacina (mg)</label><input type="number" step="0.01" id="inp_vitamina_b3_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B6 Piri. (mg)</label><input type="number" step="0.01" id="inp_vitamina_b6_mg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B9 Folato (mcg)</label><input type="number" step="0.01" id="inp_vitamina_b9_mcg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Vit B12 (mcg)</label><input type="number" step="0.01" id="inp_vitamina_b12_mcg" value="0.00" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1.5 text-sm outline-none dark:text-white"></div>
                        </div>
                    </div>

                    <div id="tab-minerales" class="tab-content hidden animate-fade-in">
                        <div class="grid grid-cols-3 md:grid-cols-5 gap-4 p-4 bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Calcio (mg)</label><input type="number" step="0.01" id="inp_calcio_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Fósforo (mg)</label><input type="number" step="0.01" id="inp_fosforo_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Magnesio (mg)</label><input type="number" step="0.01" id="inp_magnesio_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sodio (mg)</label><input type="number" step="0.01" id="inp_sodio_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Potasio (mg)</label><input type="number" step="0.01" id="inp_potasio_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Cloro (mg)</label><input type="number" step="0.01" id="inp_cloro_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Azufre (mg)</label><input type="number" step="0.01" id="inp_azufre_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Hierro (mg)</label><input type="number" step="0.01" id="inp_hierro_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Zinc (mg)</label><input type="number" step="0.01" id="inp_zinc_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Yodo (mcg)</label><input type="number" step="0.01" id="inp_yodo_mcg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Selenio (mcg)</label><input type="number" step="0.01" id="inp_selenio_mcg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Cobre (mg)</label><input type="number" step="0.01" id="inp_cobre_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Flúor (mg)</label><input type="number" step="0.01" id="inp_fluor_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                        </div>
                    </div>

                    <div id="tab-anti" class="tab-content hidden animate-fade-in">
                        <div class="bg-red-50/50 dark:bg-red-900/10 p-4 rounded-xl border border-red-100 dark:border-red-900/30">
                            <h4 class="font-bold text-red-600 dark:text-red-400 mb-3 border-b border-red-200 dark:border-red-800/50 pb-2 text-sm"><i class="ph-fill ph-shield-warning mr-1"></i> Radar de Antinutrientes y Toxinas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><label class="block text-[10px] font-bold text-red-800 dark:text-red-300 uppercase mb-1">Oxalatos (mg)</label><input type="number" step="0.01" id="inp_oxalatos_mg" value="0.00" class="w-full border border-red-200 dark:border-red-800 bg-white dark:bg-darkbase-900 rounded-md px-2 py-1.5 text-sm dark:text-white outline-none focus:ring-1 focus:ring-red-500"></div>
                                <div><label class="block text-[10px] font-bold text-red-800 dark:text-red-300 uppercase mb-1">Ácido Fítico (mg)</label><input type="number" step="0.01" id="inp_acido_fitico_mg" value="0.00" class="w-full border border-red-200 dark:border-red-800 bg-white dark:bg-darkbase-900 rounded-md px-2 py-1.5 text-sm dark:text-white outline-none focus:ring-1 focus:ring-red-500"></div>
                                <div><label class="block text-[10px] font-bold text-red-800 dark:text-red-300 uppercase mb-1">Lectinas (mg)</label><input type="number" step="0.01" id="inp_lectinas_mg" value="0.00" class="w-full border border-red-200 dark:border-red-800 bg-white dark:bg-darkbase-900 rounded-md px-2 py-1.5 text-sm dark:text-white outline-none focus:ring-1 focus:ring-red-500"></div>
                                <div><label class="block text-[10px] font-bold text-red-800 dark:text-red-300 uppercase mb-1">Taninos (mg)</label><input type="number" step="0.01" id="inp_taninos_mg" value="0.00" class="w-full border border-red-200 dark:border-red-800 bg-white dark:bg-darkbase-900 rounded-md px-2 py-1.5 text-sm dark:text-white outline-none focus:ring-1 focus:ring-red-500"></div>
                                
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Nitratos (mg)</label><input type="number" step="0.01" id="inp_nitratos_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Inhib. Proteasa (mg)</label><input type="number" step="0.01" id="inp_inhibidores_proteasa_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Bociógenos (mg)</label><input type="number" step="0.01" id="inp_goitrogenos_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Gluc. Cianogénicos</label><input type="number" step="0.01" id="inp_glucosidos_cianogenicos_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Glicoalcaloides (mg)</label><input type="number" step="0.01" id="inp_glicoalcaloides_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Saponinas (mg)</label><input type="number" step="0.01" id="inp_saponinas_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Micotoxinas (mcg)</label><input type="number" step="0.01" id="inp_micotoxinas_mcg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                                <div><label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Aminas Biógenas</label><input type="number" step="0.01" id="inp_aminas_biogenas_mg" value="0.00" class="w-full border rounded-md px-2 py-1 text-sm bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-alergenos" class="tab-content hidden animate-fade-in">
                        <div class="bg-orange-50/50 dark:bg-orange-900/10 p-5 rounded-xl border border-orange-200 dark:border-orange-800/50">
                            <h4 class="font-bold text-orange-700 dark:text-orange-400 mb-4 border-b border-orange-200 dark:border-orange-800/50 pb-2"><i class="ph-fill ph-warning-circle mr-1"></i> Marcadores de Alergias y Dietas</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_gluten" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Contiene Gluten</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_es_lacteo" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Contiene Lácteos</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_egg" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Contiene Huevo</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_tree_nut" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Frutos Secos</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_peanut" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Cacahuate</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_soy" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Contiene Soya</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_shellfish" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Mariscos/Crustáceos</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_contains_fish" class="w-5 h-5 text-orange-600 rounded"><span class="text-sm font-medium dark:text-white">Pescado</span></label>
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_is_pork" class="w-5 h-5 text-red-600 rounded"><span class="text-sm font-bold text-red-600 dark:text-red-400">Es Cerdo (Pork)</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm -mx-6 px-6 pb-2">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">Cerrar Tablero</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Alimento
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
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar del Catálogo?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esta acción borrará físicamente el alimento y todas sus traducciones asociadas.</p>
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
            <h3 class="text-xl font-black text-gray-900 dark:text-white mt-4 mb-2 uppercase tracking-wide">Restricción de Integridad</h3>
            <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-xl border border-orange-200 dark:border-orange-800 mb-6">
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400">No puedes eliminar este alimento de forma directa.</p>
                <p class="text-xs text-orange-600/80 mt-2">La base de datos reporta que este alimento ya fue asignado a uno o más platillos o registros históricos de usuarios (Restricción de Llave Foránea activa).</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>