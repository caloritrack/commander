<?php
// Nombre del archivo: unidades_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-23
// Versión: 1.0
// Descripción: Interfaz gráfica (CRUD) para el Catálogo Maestro de Unidades de Medida, con soporte completo de internacionalización y manejo de conflictos 409.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Unidades | Portal de Héroes Caloritrack';
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
        let currentUnidadId = null;

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

        // --- Carga de Tabla ---
        async function loadUnidades() {
            const tbody = document.getElementById('unidadesTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            try {
                const res = await fetch(`ajax_unidades.php?action=list`);
                const json = await res.json();
                
                if(json.success && json.data) {
                    const data = json.data.data || [];
                    
                    document.getElementById('totalCounter').innerText = data.length;

                    let html = '';
                    if(data.length === 0) {
                        html = `<tr><td colspan="6" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-scales text-4xl block mb-2 opacity-50"></i>No se encontraron unidades de medida.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-4 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">#${item.id_unidad}</td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-calori-50 dark:bg-calori-900/30 text-calori-600 dark:text-calori-400 flex items-center justify-center font-bold text-sm border border-calori-100 dark:border-calori-800 shrink-0">
                                                ${item.simbolo || '-'}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-900 dark:text-white capitalize">${item.nombre_clave}</p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">${item.nombre_singular_es}</p>
                                        <p class="text-xs text-gray-500 italic">${item.nombre_plural_es}</p>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">${item.nombre_singular_en}</p>
                                        <p class="text-xs text-gray-500 italic">${item.nombre_plural_en}</p>
                                    </td>
                                    
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openFormModal(${item.id_unidad})" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Editar"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(${item.id_unidad})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-red-500">Error de red.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        // --- Manejo del Formulario (Crear/Editar) ---
        async function openFormModal(id = null) {
            currentUnidadId = id;
            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = id 
                ? '<i class="ph-fill ph-scales text-calori-500 mr-2"></i> Editar Unidad' 
                : '<i class="ph-fill ph-scales text-calori-500 mr-2"></i> Nueva Unidad de Medida';
            document.getElementById('formUnidad').reset();

            if(id) {
                try {
                    const res = await fetch(`ajax_unidades.php?action=get&id=${id}`);
                    const json = await res.json();
                    if(json.success && json.data.data) {
                        const item = json.data.data;
                        document.getElementById('inp_nombre_clave').value = item.nombre_clave || '';
                        document.getElementById('inp_simbolo').value = item.simbolo || '';
                        document.getElementById('inp_nombre_singular_es').value = item.nombre_singular_es || '';
                        document.getElementById('inp_nombre_plural_es').value = item.nombre_plural_es || '';
                        document.getElementById('inp_nombre_singular_en').value = item.nombre_singular_en || '';
                        document.getElementById('inp_nombre_plural_en').value = item.nombre_plural_en || '';
                    }
                } catch(e) {
                    showToast("Error al cargar los datos de la unidad.", "error");
                    closeFormModal();
                }
            }
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
            currentUnidadId = null;
        }

        async function saveUnidad(event) {
            event.preventDefault();
            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            const payload = {
                nombre_clave: document.getElementById('inp_nombre_clave').value.trim(),
                simbolo: document.getElementById('inp_simbolo').value.trim(),
                nombre_singular_es: document.getElementById('inp_nombre_singular_es').value.trim(),
                nombre_plural_es: document.getElementById('inp_nombre_plural_es').value.trim(),
                nombre_singular_en: document.getElementById('inp_nombre_singular_en').value.trim(),
                nombre_plural_en: document.getElementById('inp_nombre_plural_en').value.trim()
            };

            const endpoint = currentUnidadId 
                ? `ajax_unidades.php?action=update&id=${currentUnidadId}` 
                : `ajax_unidades.php?action=create`;

            try {
                const res = await fetch(endpoint, {
                    method: currentUnidadId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                
                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentUnidadId ? "Unidad actualizada." : "Unidad creada exitosamente.", "success");
                    closeFormModal();
                    loadUnidades();
                } else {
                    showToast("Error API: " + (json.data?.message || json.message || 'Verifica los datos'), "error");
                }
            } catch(e) {
                showToast("Error de conexión al guardar.", "error");
            } finally {
                btn.innerHTML = 'Guardar Unidad';
                btn.disabled = false;
            }
        }

        // --- Eliminar Unidad (Protección Relacional) ---
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
                const res = await fetch(`ajax_unidades.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Unidad borrada permanentemente.", "success");
                    closeDeleteModal();
                    loadUnidades();
                } else if (json.http_code === 409) {
                    // API 409 Conflict: ER_ROW_IS_REFERENCED
                    closeDeleteModal();
                    document.getElementById('modalConflict').classList.remove('hidden');
                } else {
                    showToast("No se pudo eliminar la unidad.", "error");
                    closeDeleteModal();
                }
            } catch (err) {
                showToast("Error de red al intentar eliminar.", "error");
                closeDeleteModal();
            } finally {
                btn.innerHTML = 'Sí, Eliminar';
                btn.disabled = false;
            }
        }

        function closeConflictModal() { 
            document.getElementById('modalConflict').classList.add('hidden'); 
        }

        document.addEventListener('DOMContentLoaded', () => loadUnidades());
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
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Unidades</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Gestión maestro de unidades de medida (gramos, tazas, cucharadas).</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button onclick="loadUnidades()" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-plus-circle text-lg"></i>
                        Nueva Unidad
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Registros Globales: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Buscando en la base de datos de unidades...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Clave Técnica & Símbolo</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Español (MX)</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Inglés (US)</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="unidadesTableBody" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="formModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeFormModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-2xl flex flex-col rounded-3xl shadow-2xl relative overflow-hidden">
            
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center shrink-0 bg-gray-50 dark:bg-darkbase-900">
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"></h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-white dark:bg-darkbase-900">
                <form id="formUnidad" onsubmit="saveUnidad(event)" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Clave (Key Interna)</label>
                            <input type="text" id="inp_nombre_clave" required placeholder="ej. taza, gramo, cucharada" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Símbolo</label>
                            <input type="text" id="inp_simbolo" required placeholder="ej. tz, g, cda" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                        </div>
                    </div>

                    <div class="bg-blue-50/50 dark:bg-blue-900/10 p-4 rounded-xl border border-blue-100 dark:border-blue-900/30">
                        <div class="flex items-center gap-2 mb-3"><span class="text-xl">🇲🇽</span><h4 class="font-bold text-blue-900 dark:text-blue-400">Traducción Español</h4></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Singular (es-MX)</label>
                                <input type="text" id="inp_nombre_singular_es" required placeholder="ej. taza" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Plural (es-MX)</label>
                                <input type="text" id="inp_nombre_plural_es" required placeholder="ej. tazas" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                            </div>
                        </div>
                    </div>

                    <div class="bg-red-50/50 dark:bg-red-900/10 p-4 rounded-xl border border-red-100 dark:border-red-900/30">
                        <div class="flex items-center gap-2 mb-3"><span class="text-xl">🇺🇸</span><h4 class="font-bold text-red-900 dark:text-red-400">Traducción Inglés</h4></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Singular (en-US)</label>
                                <input type="text" id="inp_nombre_singular_en" required placeholder="ej. cup" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-red-500 outline-none dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Plural (en-US)</label>
                                <input type="text" id="inp_nombre_plural_en" required placeholder="ej. cups" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-red-500 outline-none dark:text-white">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Unidad
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
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Unidad?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esta acción borrará físicamente la unidad de medida.</p>
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
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400">No puedes eliminar esta unidad de medida.</p>
                <p class="text-xs text-orange-600/80 mt-2">La base de datos reporta que esta unidad ya está siendo utilizada en equivalencias de alimentos, recetas u otras tablas (Restricción 409 Activa).</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>