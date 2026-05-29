<?php
// Nombre del archivo: dietas_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-25
// Versión: 1.6
// Descripción: Interfaz gráfica (CRUD) para el Catálogo de Dietas. Se ajustó el payload de "platillos" para enviar un arreglo de enteros a Hermes y se hizo a prueba de balas la recepción de IDs.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Dietas | Portal de Héroes Caloritrack';
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
        let currentDietaId = null;
        let currentPlatillos = [];

        // Variables para el Catálogo Visual
        let allVisualPlatillos = [];
        let visualSelectedPlatillos = [];

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
                el.classList.remove('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
                el.classList.add('text-gray-500', 'border-transparent');
            });
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const btn = document.getElementById('btn-tab-' + tabId);
            
            if(tabId === 'platillos') {
                btn.classList.remove('text-gray-500', 'border-transparent');
                btn.classList.add('text-blue-500', 'border-blue-500', 'dark:text-blue-400', 'dark:border-blue-400');
            } else {
                btn.classList.remove('text-gray-500', 'border-transparent', 'text-blue-500', 'border-blue-500', 'dark:text-blue-400', 'dark:border-blue-400');
                btn.classList.add('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
            }
        }

        function resolvePlatilloImageUrl(rawUrl) {
            if (!rawUrl || rawUrl.trim() === '') return '';
            rawUrl = rawUrl.trim();
            if (rawUrl.startsWith('http')) return rawUrl;
            
            if (rawUrl.startsWith('/')) {
                rawUrl = rawUrl.substring(1);
            }
            
            if (rawUrl.includes('foodimages/platillos/')) {
                return 'https://api.caloritrack.com/' + rawUrl;
            } else if (rawUrl.includes('foodimages/')) {
                return 'https://api.caloritrack.com/' + rawUrl;
            } else {
                return 'https://api.caloritrack.com/foodimages/platillos/' + rawUrl;
            }
        }

        async function loadDietas(page = 1) {
            const tbody = document.getElementById('dietasTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            const searchVal = document.getElementById('searchInput').value.trim();
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');
            try {
                const res = await fetch(`ajax_dietas.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchVal)}`);
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
                        html = `<tr><td colspan="6" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-heartbeat text-4xl block mb-2 opacity-50"></i>No se encontraron dietas.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            const activeBadge = item.activa == 1 
                                ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Activa</span>`
                                : `<span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded uppercase">Inactiva</span>`;
                                
                        
                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">${item.id_dieta}</td>
                                    <td class="py-3 px-4">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">${item.nombre_es || item.nombre_clave}</p>
                                        <p class="text-xs text-gray-500 font-mono">${item.nombre_clave}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex gap-2 text-xs">
                                            <span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded" title="Proteínas STD"><i class="ph ph-barbell mr-1"></i>${item.porc_proteinas_std}%</span>
                                            <span class="px-2 py-1 bg-yellow-50 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 rounded" title="Grasas STD"><i class="ph ph-drop mr-1"></i>${item.porc_grasas_std}%</span>
                                            <span class="px-2 py-1 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded" title="Carbohidratos STD"><i class="ph ph-grains mr-1"></i>${item.porc_carbohidratos_std}%</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        ${item.nombre_en || 'N/A'}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        ${activeBadge}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openFormModal(${item.id_dieta})" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Editar"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(${item.id_dieta})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-red-500">Error de red al cargar dietas.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        async function openFormModal(id = null) {
            currentDietaId = id;
            currentPlatillos = []; 
            renderPlatillosTable();

            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = id 
                ? '<i class="ph-fill ph-heartbeat text-calori-500 mr-2"></i> Editar Dieta' 
                : '<i class="ph-fill ph-heartbeat text-calori-500 mr-2"></i> Nueva Dieta';
            document.getElementById('formDieta').reset();
            document.getElementById('platilloSelectDropdown').innerHTML = '<option value="">Primero busca un platillo...</option>';
            
            if(id) {
                try {
                    const [resDieta, resPlatillos] = await Promise.all([
                        fetch(`ajax_dietas.php?action=get&id=${id}`),
                        fetch(`ajax_dietas.php?action=get_platillos&id=${id}`)
                    ]);
                    const jsonDieta = await resDieta.json();
                    const jsonPlatillos = await resPlatillos.json();
                    let item = {};

                    if(jsonDieta.success && jsonDieta.data && jsonDieta.data.data) {
                        item = jsonDieta.data.data;
                        document.getElementById('inp_nombre_clave').value = item.nombre_clave || '';
                        document.getElementById('inp_activa').checked = (item.activa == 1);
                        
                        document.getElementById('inp_nombre_es').value = item.nombre_es || '';
                        document.getElementById('inp_descripcion_es').value = item.descripcion_es || '';
                        document.getElementById('inp_nombre_en').value = item.nombre_en || '';
                        document.getElementById('inp_descripcion_en').value = item.descripcion_en || '';
                        
                        document.getElementById('inp_porc_proteinas_std').value = item.porc_proteinas_std || 0;
                        document.getElementById('inp_porc_grasas_std').value = item.porc_grasas_std || 0;
                        document.getElementById('inp_porc_carbohidratos_std').value = item.porc_carbohidratos_std || 0;
                        
                        document.getElementById('inp_porc_proteinas_act').value = item.porc_proteinas_act || 0;
                        document.getElementById('inp_porc_grasas_act').value = item.porc_grasas_act || 0;
                        document.getElementById('inp_porc_carbohidratos_act').value = item.porc_carbohidratos_act || 0;
                    }

                    let arrPlatillos = [];
                    if (jsonPlatillos && jsonPlatillos.data) {
                        if (Array.isArray(jsonPlatillos.data.data)) {
                            arrPlatillos = jsonPlatillos.data.data;
                        } else if (Array.isArray(jsonPlatillos.data)) {
                            arrPlatillos = jsonPlatillos.data;
                        }
                    }
                    
                    if (arrPlatillos.length === 0 && item.platillos && Array.isArray(item.platillos)) {
                        arrPlatillos = item.platillos;
                    }
                    
                    currentPlatillos = arrPlatillos.map(p => {
                        // KRILLIN FIX: A prueba de balas. Si la API regresa un arreglo de puros IDs [58, 61]
                        if (typeof p === 'number' || typeof p === 'string') {
                            return {
                                id_platillo: parseInt(p),
                                nombre: 'Platillo #' + p,
                                url_imagen: ''
                            };
                        }
                        // Si la API regresa un arreglo de objetos complejos
                        return {
                            id_platillo: p.id_platillo,
                            nombre: p.nombre_es || p.nombre_clave || 'Platillo #' + p.id_platillo,
                            url_imagen: p.url_imagen || '' 
                        };
                    });

                    renderPlatillosTable();

                    currentPlatillos.forEach((plat, idx) => {
                        if (!plat.url_imagen || plat.url_imagen === '') {
                            fetch(`ajax_platillos.php?action=get&id=${plat.id_platillo}`)
                                .then(r => r.json())
                                .then(j => {
                                    if(j.success && j.data && j.data.data && j.data.data.url_imagen) {
                                        currentPlatillos[idx].url_imagen = j.data.data.url_imagen;
                                        // Actualizamos también el nombre si era solo un ID
                                        if (currentPlatillos[idx].nombre.startsWith('Platillo #')) {
                                            currentPlatillos[idx].nombre = j.data.data.nombre_es || j.data.data.nombre_clave;
                                        }
                                        renderPlatillosTable();
                                    }
                                }).catch(e => console.log('Silencioso: No se pudo cargar img platillo.'));
                        }
                    });
                } catch(e) {
                    console.error("Error abriendo modal:", e);
                    showToast("Error al cargar los datos de la dieta.", "error");
                    closeFormModal();
                }
            }

            switchTab('general');
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
            currentDietaId = null;
        }

        async function saveDieta(event) {
            event.preventDefault();
            
            const pStd = parseFloat(document.getElementById('inp_porc_proteinas_std').value) || 0;
            const gStd = parseFloat(document.getElementById('inp_porc_grasas_std').value) || 0;
            const cStd = parseFloat(document.getElementById('inp_porc_carbohidratos_std').value) || 0;
            if(Math.abs((pStd + gStd + cStd) - 100) > 0.1) {
                showToast("Atención: Los macros Estándar no suman 100%.", "warning");
            }

            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            // KRILLIN FIX: Extraemos SÓLO los IDs como arreglo plano de enteros para Hermes
            const platillosLimpio = currentPlatillos.map(p => parseInt(p.id_platillo));

            const payload = {
                nombre_clave: document.getElementById('inp_nombre_clave').value.trim(),
                activa: document.getElementById('inp_activa').checked ? 1 : 0,
                nombre_es: document.getElementById('inp_nombre_es').value.trim(),
                descripcion_es: document.getElementById('inp_descripcion_es').value.trim(),
                nombre_en: document.getElementById('inp_nombre_en').value.trim(),
                descripcion_en: document.getElementById('inp_descripcion_en').value.trim(),
                porc_proteinas_std: pStd,
                porc_grasas_std: gStd,
                porc_carbohidratos_std: cStd,
                porc_proteinas_act: parseFloat(document.getElementById('inp_porc_proteinas_act').value) || 0,
                porc_grasas_act: parseFloat(document.getElementById('inp_porc_grasas_act').value) || 0,
                porc_carbohidratos_act: parseFloat(document.getElementById('inp_porc_carbohidratos_act').value) || 0,
                platillos: platillosLimpio
            };

            const endpoint = currentDietaId ? `ajax_dietas.php?action=update&id=${currentDietaId}` : `ajax_dietas.php?action=create`;
            
            try {
                const res = await fetch(endpoint, {
                    method: currentDietaId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const json = await res.json();

                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentDietaId ? "Dieta actualizada exitosamente." : "Dieta creada exitosamente.", "success");
                    closeFormModal();
                    loadDietas(currentPage);
                } else {
                    showToast("Error API: " + (json.data?.error || json.message || 'Revisa los datos.'), "error");
                }
            } catch(e) {
                showToast("Error de conexión al guardar.", "error");
            } finally {
                btn.innerHTML = 'Guardar Dieta';
                btn.disabled = false;
            }
        }

        // --- Funciones del Buscador Individual Rápido ---
        async function searchPlatilloForDieta() {
            const searchVal = document.getElementById('platilloSearchInput').value.trim();
            const resultsSel = document.getElementById('platilloSelectDropdown');

            if(searchVal.length < 2) {
                showToast("Escribe al menos 2 letras para buscar un platillo.", "warning");
                return;
            }

            resultsSel.innerHTML = '<option value="">Buscando...</option>';
            try {
                const res = await fetch(`ajax_platillos.php?action=list&limit=20&search=${encodeURIComponent(searchVal)}`);
                const json = await res.json();

                if(json.success && json.data && json.data.data.length > 0) {
                    resultsSel.innerHTML = '<option value="">Selecciona un platillo...</option>';
                    json.data.data.forEach(plat => {
                        resultsSel.innerHTML += `<option value="${plat.id_platillo}" data-img="${plat.url_imagen || ''}">${plat.nombre_es || plat.nombre_clave} (ID: ${plat.id_platillo})</option>`;
                    });
                } else {
                    resultsSel.innerHTML = '<option value="">No se encontraron resultados.</option>';
                }
            } catch(e) {
                resultsSel.innerHTML = '<option value="">Error en búsqueda.</option>';
            }
        }

        function addPlatilloToDieta() {
            const selectEl = document.getElementById('platilloSelectDropdown');
            const idPlatillo = selectEl.value;
            const nombrePlatillo = selectEl.options[selectEl.selectedIndex]?.text.replace(/ \(ID: \d+\)$/, '') || 'Platillo ' + idPlatillo;
            const imgUrl = selectEl.options[selectEl.selectedIndex]?.getAttribute('data-img') || '';

            if(!idPlatillo) {
                showToast("Por favor selecciona un platillo de la lista.", "warning");
                return;
            }

            const exists = currentPlatillos.findIndex(p => p.id_platillo == idPlatillo);
            if(exists >= 0) {
                showToast("El platillo ya se encuentra en esta dieta.", "warning");
                return;
            }

            currentPlatillos.push({
                id_platillo: parseInt(idPlatillo),
                nombre: nombrePlatillo,
                url_imagen: imgUrl
            });
            
            document.getElementById('platilloSearchInput').value = '';
            document.getElementById('platilloSelectDropdown').innerHTML = '<option value="">Primero busca un platillo...</option>';

            renderPlatillosTable();
            showToast("Platillo asignado.", "success");
        }

        function removePlatilloFromDieta(index) {
            currentPlatillos.splice(index, 1);
            renderPlatillosTable();
        }

        function renderPlatillosTable() {
            const tbody = document.getElementById('tbodyPlatillosDieta');
            let html = '';

            if(currentPlatillos.length === 0) {
                html = '<tr><td colspan="3" class="text-center py-6 text-sm text-gray-500 font-medium">Aún no hay platillos asignados a esta dieta.</td></tr>';
            } else {
                currentPlatillos.forEach((p, index) => {
                    const imgSrc = resolvePlatilloImageUrl(p.url_imagen);
                    const imgHtml = imgSrc
                        ? `<img src="${imgSrc}" alt="IMG" class="w-10 h-10 object-cover rounded-lg shadow-sm border border-gray-200 dark:border-gray-700" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=&quot;w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-700&quot;><i class=&quot;ph ph-cooking-pot text-gray-400 text-xl&quot;></i></div>';">`
                        : `<div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-700"><i class="ph ph-cooking-pot text-gray-400 text-xl"></i></div>`;

                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="py-3 px-4 w-14 whitespace-nowrap">
                                ${imgHtml}
                            </td>
                            <td class="py-3 px-4">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">${p.nombre}</p>
                                <p class="text-[10px] text-gray-400 font-mono">ID: ${p.id_platillo}</p>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button type="button" onclick="removePlatilloFromDieta(${index})" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Quitar">
                                    <i class="ph ph-x-circle text-lg"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            tbody.innerHTML = html;
        }

        // === FUNCIONALIDAD CATÁLOGO VISUAL (SELECCIÓN MÚLTIPLE) ===
        async function openVisualCatalog() {
            document.getElementById('visualCatalogModal').classList.remove('hidden');
            document.getElementById('visualCatalogGrid').innerHTML = '';
            document.getElementById('visualSearchInput').value = '';
            visualSelectedPlatillos = [];
            updateVisualSelectedCount();

            const loader = document.getElementById('visualCatalogLoader');
            loader.classList.remove('hidden');
            try {
                // Pedimos un listado amplio para que el usuario pueda explorar (hasta 200)
                const res = await fetch(`ajax_platillos.php?action=list&limit=200`);
                const json = await res.json();
                
                if(json.success && json.data && json.data.data) {
                    allVisualPlatillos = json.data.data;
                    renderVisualCatalogGrid(allVisualPlatillos);
                } else {
                    document.getElementById('visualCatalogGrid').innerHTML = '<p class="col-span-full text-center text-gray-500 py-8">No se encontraron platillos en el catálogo.</p>';
                }
            } catch(e) {
                document.getElementById('visualCatalogGrid').innerHTML = '<p class="col-span-full text-center text-red-500 py-8">Error de red al cargar el catálogo visual.</p>';
            } finally {
                loader.classList.add('hidden');
            }
        }

        function renderVisualCatalogGrid(platillos) {
            const grid = document.getElementById('visualCatalogGrid');
            let html = '';
            
            platillos.forEach(p => {
                const isSelected = visualSelectedPlatillos.some(sel => sel.id_platillo == p.id_platillo);
                const checkClass = isSelected ? 'bg-calori-500 border-calori-500 text-white' : 'bg-white border-gray-300 dark:bg-gray-800 dark:border-gray-600 transparent';
                const imgSrc = resolvePlatilloImageUrl(p.url_imagen);
                
                const imgHtml = imgSrc 
                    ? `<img src="${imgSrc}" class="w-20 h-20 object-cover rounded-xl border border-gray-200 dark:border-gray-700 shrink-0" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0\\'><i class=\\'ph ph-cooking-pot text-2xl text-gray-400\\'></i></div>';">`
                    : `<div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0"><i class="ph ph-cooking-pot text-2xl text-gray-400"></i></div>`;
                
                // Usamos descripcion_es o ingredientes si la API los trae, si no, un texto estándar.
                const descripcion = p.descripcion_es || p.ingredientes || p.nombre_en || 'Sin detalles adicionales.';
                const safeNombre = (p.nombre_es || p.nombre_clave || '').replace(/'/g, "\\'");
                const safeUrl = (p.url_imagen || '').replace(/'/g, "\\'");

                html += `
                    <div onclick="toggleVisualSelection(${p.id_platillo}, '${safeNombre}', '${safeUrl}')" 
                         class="cursor-pointer group flex gap-4 p-4 bg-white dark:bg-darkbase-800 rounded-2xl border ${isSelected ? 'border-calori-500 ring-1 ring-calori-500 bg-calori-50/30 dark:bg-calori-900/10' : 'border-gray-200 dark:border-gray-700 hover:border-calori-300 dark:hover:border-calori-700'} shadow-sm transition-all">
                        
                        <div class="flex items-center shrink-0">
                            <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors ${checkClass}">
                                <i class="ph ph-check text-sm ${isSelected ? 'opacity-100' : 'opacity-0'}"></i>
                            </div>
                        </div>

                        ${imgHtml}

                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white truncate" title="${p.nombre_es || p.nombre_clave}">${p.nombre_es || p.nombre_clave}</h5>
                            <p class="text-[10px] text-calori-600 dark:text-calori-400 font-mono mb-1">ID: ${p.id_platillo}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2 leading-tight">${descripcion}</p>
                        </div>
                    </div>
                `;
            });
            
            if(html === '') html = '<p class="col-span-full text-center text-gray-500 py-8">No hay resultados que coincidan con tu búsqueda.</p>';
            grid.innerHTML = html;
        }

        function filterVisualCatalog() {
            const query = document.getElementById('visualSearchInput').value.toLowerCase();
            const filtered = allVisualPlatillos.filter(p => {
                const nombre = (p.nombre_es || p.nombre_clave || '').toLowerCase();
                const desc = (p.descripcion_es || p.ingredientes || p.nombre_en || '').toLowerCase();
                return nombre.includes(query) || desc.includes(query) || p.id_platillo.toString().includes(query);
            });
            renderVisualCatalogGrid(filtered);
        }

        function toggleVisualSelection(id, nombre, url_imagen) {
            const idx = visualSelectedPlatillos.findIndex(s => s.id_platillo == id);
            if(idx >= 0) {
                visualSelectedPlatillos.splice(idx, 1); // Deseleccionar
            } else {
                visualSelectedPlatillos.push({ id_platillo: id, nombre: nombre, url_imagen: url_imagen }); // Seleccionar
            }
            updateVisualSelectedCount();
            filterVisualCatalog(); // Refrescar vista
        }

        function updateVisualSelectedCount() {
            document.getElementById('visualSelectedCount').innerText = visualSelectedPlatillos.length;
        }

        function confirmVisualSelection() {
            if(visualSelectedPlatillos.length === 0) {
                showToast("No has seleccionado ningún platillo.", "warning");
                return;
            }

            let addedCount = 0;
            visualSelectedPlatillos.forEach(sel => {
                // Verificar si ya existe en la dieta para no duplicar
                if(!currentPlatillos.some(p => p.id_platillo == sel.id_platillo)) {
                    currentPlatillos.push({
                        id_platillo: parseInt(sel.id_platillo),
                        nombre: sel.nombre,
                        url_imagen: sel.url_imagen
                    });
                    addedCount++;
                }
            });

            renderPlatillosTable();
            closeVisualCatalog();
            
            if(addedCount > 0) {
                showToast(`Se agregaron ${addedCount} platillos a la dieta.`, "success");
            } else {
                showToast("Los platillos seleccionados ya estaban asignados.", "warning");
            }
        }

        function closeVisualCatalog() {
            document.getElementById('visualCatalogModal').classList.add('hidden');
            visualSelectedPlatillos = [];
        }

        // --- Fin Funciones Catálogo Visual ---

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
                const res = await fetch(`ajax_dietas.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Dieta eliminada correctamente.", "success");
                    closeDeleteModal();
                    loadDietas(currentPage);
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
        function searchAction(e) { if(e.key === 'Enter') loadDietas(1); }
        function changePage(delta) {
            const newP = currentPage + delta;
            if(newP >= 1 && newP <= totalPages) loadDietas(newP);
        }

        document.addEventListener('DOMContentLoaded', () => loadDietas(1));
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
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Dietas</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Gestión de perfiles dietéticos base, macros y traducciones.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="searchInput" onkeydown="searchAction(event)" placeholder="Buscar dieta..." class="w-full bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white shadow-sm">
                    </div>
                    
                    <button onclick="loadDietas(1)" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-plus-circle text-lg"></i>
                        Nueva Dieta
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Dietas: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Cargando catálogo maestro...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre Clave / Español</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Macros Base (STD)</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre Inglés</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="dietasTableBody" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
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
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-5xl max-h-[92vh] flex flex-col rounded-3xl shadow-2xl relative">
            
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center shrink-0">
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"><i class="ph-fill ph-heartbeat text-calori-500 mr-2"></i> Nueva Dieta</h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-800 px-6 pt-2 shrink-0 overflow-x-auto scrollbar-hide">
                <button type="button" id="btn-tab-general" onclick="switchTab('general')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 text-calori-600 border-calori-600 whitespace-nowrap">General y Textos</button>
                <button type="button" id="btn-tab-macros" onclick="switchTab('macros')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap">Gestión de Macros</button>
                <button type="button" id="btn-tab-platillos" onclick="switchTab('platillos')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-blue-700 dark:hover:text-blue-300 whitespace-nowrap"><i class="ph ph-cooking-pot mr-1"></i>Platillos Asignados</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900/50 relative">
                
                <form id="formDieta" onsubmit="saveDieta(event)">
                    
                    <div id="tab-general" class="tab-content animate-fade-in space-y-6">
                        
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Información Base</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Clave (Interno)</label>
                                    <input type="text" id="inp_nombre_clave" required placeholder="ej. KETO_STANDARD" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono uppercase">
                                </div>
                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_activa" class="w-5 h-5 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Dieta Activa y Visible</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇲🇽</span><h4 class="font-bold text-gray-900 dark:text-white">Textos en Español</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Público (es-MX)</label>
                                    <input type="text" id="inp_nombre_es" required placeholder="Dieta Cetogénica" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Descripción Breve (es-MX)</label>
                                    <textarea id="inp_descripcion_es" rows="2" placeholder="Dieta alta en grasas y baja en carbohidratos..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇺🇸</span><h4 class="font-bold text-gray-900 dark:text-white">Textos en Inglés</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Public Name (en-US)</label>
                                    <input type="text" id="inp_nombre_en" required placeholder="Ketogenic Diet" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Short Description (en-US)</label>
                                    <textarea id="inp_descripcion_en" rows="2" placeholder="High-fat, low-carb diet..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div id="tab-macros" class="tab-content hidden animate-fade-in space-y-6">
                        
                        <div class="bg-blue-50/50 dark:bg-blue-900/10 p-5 rounded-xl border border-blue-200 dark:border-blue-800/50">
                            <h4 class="font-black text-blue-800 dark:text-blue-300 mb-4 flex items-center"><i class="ph-fill ph-target mr-2"></i> Perfil Estándar (_std)</h4>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mb-4">Porcentajes base recomendados para usuarios con un nivel de actividad regular o sedentario.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-blue-700 dark:text-blue-400 uppercase mb-1">% Proteínas</label>
                                    <input type="number" step="0.01" id="inp_porc_proteinas_std" required placeholder="ej. 20.00" class="w-full bg-white dark:bg-darkbase-800 border border-blue-200 dark:border-blue-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-blue-700 dark:text-blue-400 uppercase mb-1">% Grasas</label>
                                    <input type="number" step="0.01" id="inp_porc_grasas_std" required placeholder="ej. 75.00" class="w-full bg-white dark:bg-darkbase-800 border border-blue-200 dark:border-blue-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-blue-700 dark:text-blue-400 uppercase mb-1">% Carbohidratos</label>
                                    <input type="number" step="0.01" id="inp_porc_carbohidratos_std" required placeholder="ej. 5.00" class="w-full bg-white dark:bg-darkbase-800 border border-blue-200 dark:border-blue-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white font-bold">
                                </div>
                            </div>
                        </div>

                        <div class="bg-orange-50/50 dark:bg-orange-900/10 p-5 rounded-xl border border-orange-200 dark:border-orange-800/50">
                            <h4 class="font-black text-orange-800 dark:text-orange-300 mb-4 flex items-center"><i class="ph-fill ph-fire mr-2"></i> Perfil Activo (_act)</h4>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mb-4">Variación de los macros orientada a usuarios con desgaste físico alto (atletas, hipertrofia).</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-orange-700 dark:text-orange-400 uppercase mb-1">% Proteínas</label>
                                    <input type="number" step="0.01" id="inp_porc_proteinas_act" required placeholder="ej. 25.00" class="w-full bg-white dark:bg-darkbase-800 border border-orange-200 dark:border-orange-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none dark:text-white font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-orange-700 dark:text-orange-400 uppercase mb-1">% Grasas</label>
                                    <input type="number" step="0.01" id="inp_porc_grasas_act" required placeholder="ej. 70.00" class="w-full bg-white dark:bg-darkbase-800 border border-orange-200 dark:border-orange-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none dark:text-white font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-orange-700 dark:text-orange-400 uppercase mb-1">% Carbohidratos</label>
                                    <input type="number" step="0.01" id="inp_porc_carbohidratos_act" required placeholder="ej. 5.00" class="w-full bg-white dark:bg-darkbase-800 border border-orange-200 dark:border-orange-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none dark:text-white font-bold">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div id="tab-platillos" class="tab-content hidden animate-fade-in space-y-6">
                        
                        <div class="bg-blue-50/30 dark:bg-blue-900/10 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                            <h4 class="font-black text-blue-900 dark:text-blue-400 flex items-center mb-1"><i class="ph-fill ph-magnifying-glass text-xl mr-2"></i> Asignación de Platillos</h4>
                            <p class="text-xs text-blue-600/70 dark:text-blue-400/70 mb-4">Puedes buscar por nombre exacto o abrir el catálogo visual para seleccionar varios en lote.</p>
                            
                            <div class="flex flex-col md:flex-row gap-3">
                                <button type="button" onclick="openVisualCatalog()" class="px-5 py-2 bg-calori-600 text-white rounded-lg text-sm font-bold hover:bg-calori-700 shadow-md transition-all whitespace-nowrap flex items-center justify-center">
                                    <i class="ph ph-squares-four mr-2 text-lg"></i> Catálogo Visual
                                </button>
                                
                                <div class="hidden md:block w-px bg-blue-200 dark:bg-blue-800 mx-1"></div>

                                <div class="flex-1 relative">
                                    <input type="text" id="platilloSearchInput" placeholder="Búsqueda rápida..." class="w-full bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white pr-10">
                                    <button type="button" onclick="searchPlatilloForDieta()" class="absolute right-0 top-0 bottom-0 px-3 text-blue-600 dark:text-blue-400 hover:text-blue-800"><i class="ph ph-magnifying-glass font-bold"></i></button>
                                </div>
                                
                                <select id="platilloSelectDropdown" class="flex-1 bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white max-w-[200px]">
                                    <option value="">Buscar platillo...</option>
                                </select>
                                
                                <button type="button" onclick="addPlatilloToDieta()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-md transition-all whitespace-nowrap">
                                    Agregar
                                </button>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-14">Img</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platillo Asignado</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Remover</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPlatillosDieta" class="divide-y divide-gray-100 dark:divide-gray-800">
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm -mx-6 px-6 pb-2">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Dieta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="visualCatalogModal" class="hidden fixed inset-0 z-[150] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-md" onclick="closeVisualCatalog()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-6xl h-[90vh] flex flex-col rounded-3xl shadow-2xl relative">
            
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center"><i class="ph-fill ph-book-open text-calori-500 mr-2"></i> Catálogo Visual de Platillos</h3>
                    <p class="text-xs text-gray-500 mt-1">Selecciona los platillos que deseas agregar a la dieta y confirma.</p>
                </div>
                <button type="button" onclick="closeVisualCatalog()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 p-2 rounded-full transition-colors"><i class="ph ph-x text-lg"></i></button>
            </div>

            <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row gap-3 bg-gray-50/50 dark:bg-gray-900/50 shrink-0">
                <div class="relative flex-1">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="visualSearchInput" placeholder="Buscar por nombre, ID o ingredientes..." onkeyup="filterVisualCatalog()" class="w-full bg-white dark:bg-darkbase-800 border border-gray-200 dark:border-gray-700 rounded-xl py-2.5 pl-10 pr-4 text-sm outline-none dark:text-white focus:ring-2 focus:ring-calori-500 transition-all">
                </div>
                <button type="button" onclick="confirmVisualSelection()" class="px-6 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 transition-all shadow-md flex items-center justify-center shrink-0 whitespace-nowrap">
                    <i class="ph ph-plus-circle mr-2 text-lg"></i> Agregar Seleccionados (<span id="visualSelectedCount" class="mx-1">0</span>)
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-100/30 dark:bg-darkbase-950/30">
                <div id="visualCatalogLoader" class="flex flex-col items-center justify-center py-20 hidden">
                    <i class="ph ph-spinner animate-spin text-5xl text-calori-500 mb-4"></i>
                    <p class="text-sm text-gray-500 font-medium">Cargando menú de platillos...</p>
                </div>
                <div id="visualCatalogGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    </div>
            </div>
        </div>
    </div>

    <div id="modalDelete" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-white/50 dark:border-gray-700 w-full max-w-sm rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4"><i class="ph ph-trash text-3xl"></i></div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Dieta?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esta acción borrará la dieta y sus traducciones de forma definitiva.</p>
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
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400">No puedes eliminar esta dieta.</p>
                <p class="text-xs text-orange-600/80 mt-2">La API reporta que esta dieta ya está ligada a algunos platillos o al plan activo de un usuario.</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>