<?php
// Nombre del archivo: platillos_catalog.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-29
// Versión: 1.11
// Descripción: Se ajustó el buscador de ingredientes para que consulte a Hermes utilizando el filtro es_publico=1, garantizando que solo se armen platillos con alimentos globales.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Catálogo de Platillos | Portal de Héroes Caloritrack';
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
        
        /* Ocultar flechas del input number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentPlatilloId = null;
        
        // Arreglo temporal para gestionar los ingredientes del platillo actual
        let currentIngredients = [];

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
            btn.classList.remove('text-gray-500', 'border-transparent');
            btn.classList.add('text-calori-600', 'border-calori-600', 'dark:text-calori-400', 'dark:border-calori-400');
        }

        // --- RESOLUCIÓN INTELIGENTE DE IMÁGENES (Soporta Legacy y Cloudflare R2) ---
        function resolvePlatilloImageUrl(rawUrl) {
            if (!rawUrl || rawUrl.trim() === '') return '';
            rawUrl = rawUrl.trim();
            if (rawUrl.startsWith('http')) return rawUrl;
            if (rawUrl.startsWith('/')) rawUrl = rawUrl.substring(1);
            
            if (rawUrl.includes('foodimages/platillos/')) {
                return 'https://api.caloritrack.com/' + rawUrl;
            }
            
            if (rawUrl.includes('platillos/')) {
                return 'https://images.caloritrack.com/' + rawUrl;
            } else {
                return 'https://images.caloritrack.com/platillos/' + rawUrl;
            }
        }

        // Resolución de la URL de Alimentos (Para la tabla de ingredientes)
        function resolveAlimentoImageUrl(rawUrl) {
            if (!rawUrl || rawUrl.trim() === '') return '';
            rawUrl = rawUrl.trim();
            if (rawUrl.startsWith('http')) return rawUrl;
            if (rawUrl.startsWith('/')) rawUrl = rawUrl.substring(1);
            
            if (rawUrl.includes('foodimages/food/')) {
                return 'https://api.caloritrack.com/' + rawUrl;
            }
            
            if (rawUrl.includes('food/')) {
                return 'https://images.caloritrack.com/' + rawUrl;
            } else {
                return 'https://images.caloritrack.com/food/' + rawUrl;
            }
        }

        function updateLivePreview() {
            const inputVal = document.getElementById('inp_url_imagen').value;
            const previewContainer = document.getElementById('liveImagePreviewContainer');
            const finalUrl = resolvePlatilloImageUrl(inputVal);

            if (finalUrl !== '') {
                previewContainer.innerHTML = `<img src="${finalUrl}" id="livePreviewImgElement" alt="Previsualización" class="w-full h-full object-contain p-2 drop-shadow-lg transition-all duration-300">`;
                
                document.getElementById('livePreviewImgElement').onerror = function() {
                    previewContainer.innerHTML = `
                        <div class="flex flex-col items-center justify-center p-6 text-center text-red-500 animate-fade-in">
                            <i class="ph ph-image-broken text-5xl mb-2 opacity-70"></i>
                            <span class="text-xs font-bold">Archivo no encontrado en el servidor</span>
                        </div>
                    `;
                };
            } else {
                previewContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center p-6 text-center text-gray-400 dark:text-gray-500">
                        <i class="ph ph-cooking-pot text-5xl mb-2 opacity-40 animate-pulse"></i>
                        <span class="text-xs font-medium">Sin imagen asignada</span>
                    </div>
                `;
            }
        }

        // === COMPRESIÓN DE IMÁGENES AL VUELO ===
        async function compressAndUploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const originalSize = file.size;
            const statsDiv = document.getElementById('compressionStats');
            const uploadBtn = document.getElementById('btnUploadImage');
            const originalBtnHtml = uploadBtn.innerHTML;
            
            uploadBtn.innerHTML = '<i class="ph ph-spinner animate-spin mr-1"></i> Procesando...';
            uploadBtn.disabled = true;
            statsDiv.classList.add('hidden');

            try {
                const img = new Image();
                img.src = URL.createObjectURL(file);
                await new Promise(res => img.onload = res);

                const MAX_WIDTH = 800;
                let width = img.width;
                let height = img.height;

                if (width > MAX_WIDTH) {
                    height = Math.floor(height * (MAX_WIDTH / width));
                    width = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                const blob = await new Promise(res => canvas.toBlob(res, 'image/webp', 0.8));
                const compressedSize = blob.size;

                const formatBytes = (bytes) => (bytes / 1024).toFixed(1) + ' KB';
                const originalMB = (originalSize / (1024*1024)).toFixed(2) + ' MB';
                const compressedKB = formatBytes(compressedSize);
                const savings = Math.round((1 - (compressedSize / originalSize)) * 100);

                document.getElementById('compOriginal').innerText = originalSize > 1024*1024 ? originalMB : formatBytes(originalSize);
                document.getElementById('compNueva').innerText = compressedKB;
                document.getElementById('compAhorro').innerText = savings + '%';
                statsDiv.classList.remove('hidden');

                const formData = new FormData();
                const safeName = file.name.replace(/\.[^/.]+$/, "").replace(/[^a-z0-9]/gi, '_').toLowerCase();
                formData.append('image', blob, safeName + ".webp");
                formData.append('folder', 'platillos');
                
                const response = await fetch('ajax_upload.php', { method: 'POST', body: formData });
                const json = await response.json();

                if (json.success && json.data && json.data.url) {
                    document.getElementById('inp_url_imagen').value = json.data.url;
                    updateLivePreview();
                    showToast("Imagen subida y optimizada mágicamente.", "success");
                } else {
                    showToast("Error al subir a la nube: " + (json.message || "No se recibió respuesta válida de Hermes"), "error");
                }

            } catch (err) {
                console.error(err);
                showToast("Error procesando la compresión de la imagen.", "error");
            } finally {
                uploadBtn.innerHTML = originalBtnHtml;
                uploadBtn.disabled = false;
                event.target.value = ''; 
            }
        }

        // --- CARGA DE TABLA DE PLATILLOS ---
        async function loadPlatillos(page = 1) {
            const tbody = document.getElementById('platillosTableBody');
            const loader = document.getElementById('tableLoader');
            const tableCont = document.getElementById('tableContainer');
            const searchVal = document.getElementById('searchInput').value.trim();
            
            loader.classList.remove('hidden');
            tableCont.classList.add('hidden');

            try {
                const res = await fetch(`ajax_platillos.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchVal)}`);
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
                        html = `<tr><td colspan="6" class="text-center py-12 text-gray-500 font-medium"><i class="ph ph-cooking-pot text-4xl block mb-2 opacity-50"></i>No se encontraron platillos.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            const activeBadge = item.activo 
                                ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded uppercase">Activo</span>`
                                : `<span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-bold rounded uppercase">Inactivo</span>`;
                            
                            const publicBadge = item.es_publico 
                                ? `<span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase ml-1">Global</span>`
                                : '';

                            const imgSrc = resolvePlatilloImageUrl(item.url_imagen);
                            const imgHtml = imgSrc 
                                ? `<img src="${imgSrc}" alt="IMG" class="w-full h-full object-cover" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=&quot;ph ph-image-broken text-gray-400 text-2xl&quot;></i>';">` 
                                : `<i class="ph ph-image text-gray-400 text-2xl"></i>`;

                            html += `
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap text-xs text-gray-500 font-mono">${item.id_platillo}</td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0 bg-gray-100 flex items-center justify-center">
                                            ${imgHtml}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">${item.nombre_es || item.nombre_clave}</p>
                                        <p class="text-xs text-gray-500 font-mono">${item.nombre_clave}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium">
                                        ${item.sugerencia_comida || 'N/A'}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        ${activeBadge} ${publicBadge}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openFormModal(${item.id_platillo})" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Editar"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(${item.id_platillo})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar"><i class="ph ph-trash text-lg"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    tbody.innerHTML = html;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-red-500">Error de red al cargar platillos.</td></tr>`;
            } finally {
                loader.classList.add('hidden');
                tableCont.classList.remove('hidden');
            }
        }

        // --- MANEJO DEL MODAL (CREAR / EDITAR) ---
        async function openFormModal(id = null) {
            currentPlatilloId = id;
            currentIngredients = []; 
            renderIngredientsTable(); 

            document.getElementById('formModal').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = id 
                ? '<i class="ph-fill ph-cooking-pot text-calori-500 mr-2"></i> Editar Platillo' 
                : '<i class="ph-fill ph-cooking-pot text-calori-500 mr-2"></i> Nuevo Platillo';
            
            document.getElementById('formPlatillo').reset();
            document.getElementById('compressionStats').classList.add('hidden');
            document.getElementById('liveImagePreviewContainer').innerHTML = `
                <div class="flex flex-col items-center justify-center p-6 text-center text-gray-400 dark:text-gray-500">
                    <i class="ph ph-cooking-pot text-5xl mb-2 opacity-40 animate-pulse"></i>
                    <span class="text-xs font-medium">Sin imagen asignada</span>
                </div>
            `;
            
            document.getElementById('ingrSelectFood').innerHTML = '<option value="">Primero busca un alimento...</option>';
            document.getElementById('ingrSelectUnidad').innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';

            if(id) {
                try {
                    const res = await fetch(`ajax_platillos.php?action=get&id=${id}`);
                    const json = await res.json();
                    if(json.success && json.data.data) {
                        const item = json.data.data;
                        
                        document.getElementById('inp_nombre_clave').value = item.nombre_clave || '';
                        document.getElementById('inp_sugerencia_comida').value = item.sugerencia_comida || '';
                        document.getElementById('inp_url_imagen').value = item.url_imagen || '';
                        document.getElementById('inp_alt_text_imagen').value = item.alt_text_imagen || '';
                        document.getElementById('inp_activo').checked = (item.activo == 1);
                        document.getElementById('inp_es_publico').checked = (item.es_publico == 1);
                        
                        document.getElementById('inp_nombre_es').value = item.nombre_es || '';
                        document.getElementById('inp_descripcion_es').value = item.descripcion_es || '';
                        document.getElementById('inp_nombre_en').value = item.nombre_en || '';
                        document.getElementById('inp_descripcion_en').value = item.descripcion_en || '';

                        if(item.ingredientes && Array.isArray(item.ingredientes)) {
                            currentIngredients = item.ingredientes.map(ing => ({
                                id_alimento: ing.id_alimento,
                                nombre_alimento: ing.nombre_alimento || 'Alimento #' + ing.id_alimento,
                                cantidad_gramos: ing.cantidad_gramos,
                                equivalenciaText: '<i class="ph ph-spinner animate-spin text-calori-500"></i>',
                                url_imagen: ''
                            }));
                            renderIngredientsTable();
                            
                            currentIngredients.forEach((ing, idx) => {
                                fetch(`ajax_alimentos.php?action=get&id=${ing.id_alimento}`)
                                    .then(r => r.json())
                                    .then(j => {
                                        if(j.success && j.data && j.data.data) {
                                            currentIngredients[idx].url_imagen = j.data.data.url_imagen;
                                            renderIngredientsTable();
                                        }
                                    }).catch(e => console.log('Silencioso: No se pudo cargar img alimento.'));

                                fetch(`ajax_equivalencias.php?action=list&id_alimento=${ing.id_alimento}`)
                                    .then(r => r.json())
                                    .then(j => {
                                        if (j.success && j.data && j.data.length > 0) {
                                            let bestEq = j.data[0];
                                            
                                            for(let i = 0; i < j.data.length; i++) {
                                                let eq = j.data[i];
                                                let division = ing.cantidad_gramos / eq.gramos_equivalentes;
                                                if (Math.abs(division - Math.round(division)) < 0.05) {
                                                    bestEq = eq;
                                                    break;
                                                }
                                            }

                                            let qty = +(ing.cantidad_gramos / bestEq.gramos_equivalentes).toFixed(2);
                                            let unitName = bestEq.nombre_clave.charAt(0).toUpperCase() + bestEq.nombre_clave.slice(1);
                                            
                                            if (qty > 1 && !unitName.endsWith('s')) {
                                                unitName += 's';
                                            }

                                            currentIngredients[idx].equivalenciaText = `<span class="text-green-600 dark:text-green-400 font-medium">${qty} ${unitName}</span>`;
                                        } else {
                                            currentIngredients[idx].equivalenciaText = `<span class="text-gray-500">${ing.cantidad_gramos} Gramos</span>`;
                                        }
                                        renderIngredientsTable();
                                    }).catch(() => {
                                        currentIngredients[idx].equivalenciaText = `<span class="text-gray-500">${ing.cantidad_gramos} Gramos</span>`;
                                        renderIngredientsTable();
                                    });
                            });
                        }
                    }
                } catch(e) {
                    showToast("Error al cargar los datos del platillo.", "error");
                    closeFormModal();
                }
            }

            updateLivePreview();
            switchTab('general');
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
            currentPlatilloId = null;
        }

        async function savePlatillo(event) {
            event.preventDefault();
            
            if(currentIngredients.length === 0) {
                showToast("Debes agregar al menos un ingrediente al platillo.", "warning");
                return;
            }

            const btn = document.getElementById('btnSave');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            const ingredientesLimpio = currentIngredients.map(ing => ({
                id_alimento: parseInt(ing.id_alimento),
                cantidad_gramos: parseFloat(ing.cantidad_gramos)
            }));

            const payload = {
                nombre_clave: document.getElementById('inp_nombre_clave').value.trim(),
                sugerencia_comida: document.getElementById('inp_sugerencia_comida').value,
                url_imagen: document.getElementById('inp_url_imagen').value.trim(),
                alt_text_imagen: document.getElementById('inp_alt_text_imagen').value.trim(),
                activo: document.getElementById('inp_activo').checked ? 1 : 0,
                es_publico: document.getElementById('inp_es_publico').checked ? 1 : 0,
                nombre_es: document.getElementById('inp_nombre_es').value.trim(),
                descripcion_es: document.getElementById('inp_descripcion_es').value.trim(),
                nombre_en: document.getElementById('inp_nombre_en').value.trim(),
                descripcion_en: document.getElementById('inp_descripcion_en').value.trim(),
                ingredientes: ingredientesLimpio
            };

            const endpoint = currentPlatilloId ? `ajax_platillos.php?action=update&id=${currentPlatilloId}` : `ajax_platillos.php?action=create`;
            
            try {
                const res = await fetch(endpoint, {
                    method: currentPlatilloId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if(json.success || json.http_code === 201 || json.http_code === 200) {
                    showToast(currentPlatilloId ? "Platillo actualizado exitosamente." : "Platillo creado exitosamente.", "success");
                    closeFormModal();
                    loadPlatillos(currentPage);
                } else if(json.http_code === 409) {
                    showToast("Error: Ya existe un platillo con ese nombre clave.", "warning");
                } else {
                    showToast("Error API: " + (json.data?.error || json.message || 'Verifica los datos'), "error");
                }
            } catch(e) {
                showToast("Error de conexión al guardar.", "error");
            } finally {
                btn.innerHTML = 'Guardar Platillo';
                btn.disabled = false;
            }
        }

        // --- LÓGICA DE GESTIÓN DE INGREDIENTES Y EQUIVALENCIAS ---
        async function searchFoodForIngredient() {
            const searchVal = document.getElementById('ingrSearchInput').value.trim();
            const resultsSel = document.getElementById('ingrSelectFood');
            const unitSel = document.getElementById('ingrSelectUnidad');
            
            if(searchVal.length < 2) {
                showToast("Escribe al menos 2 letras para buscar un alimento.", "warning");
                return;
            }

            resultsSel.innerHTML = '<option value="">Buscando...</option>';
            unitSel.innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';
            
            try {
                // Filtramos por alimentos públicos
                const res = await fetch(`ajax_alimentos.php?action=list&limit=20&es_publico=1&search=${encodeURIComponent(searchVal)}`);
                const json = await res.json();
                
                if(json.success && json.data && json.data.data.length > 0) {
                    resultsSel.innerHTML = '<option value="">Selecciona un alimento...</option>';
                    json.data.data.forEach(food => {
                        resultsSel.innerHTML += `<option value="${food.id_alimento}" data-img="${food.url_imagen || ''}">${food.nombre_es || food.nombre_clave} (ID: ${food.id_alimento})</option>`;
                    });
                } else {
                    resultsSel.innerHTML = '<option value="">No se encontraron resultados.</option>';
                }
            } catch(e) {
                resultsSel.innerHTML = '<option value="">Error en búsqueda.</option>';
            }
        }

        async function loadEquivalenciesForFood() {
            const selectEl = document.getElementById('ingrSelectFood');
            const unitSel = document.getElementById('ingrSelectUnidad');
            const idAlimento = selectEl.value;

            unitSel.innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';
            if(!idAlimento) return;

            try {
                unitSel.innerHTML = '<option value="1" data-name="g">Cargando unidades...</option>';
                const res = await fetch(`ajax_equivalencias.php?action=list&id_alimento=${idAlimento}`);
                const json = await res.json();

                unitSel.innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';

                if(json.success && json.data && json.data.length > 0) {
                    json.data.forEach(eq => {
                        unitSel.innerHTML += `<option value="${eq.gramos_equivalentes}" data-name="${eq.nombre_clave}">${eq.nombre_clave} (${eq.gramos_equivalentes}g)</option>`;
                    });
                }
            } catch(e) {
                unitSel.innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';
            }
        }

        function addIngredient() {
            const selectEl = document.getElementById('ingrSelectFood');
            const unitSel = document.getElementById('ingrSelectUnidad');
            
            const idAlimento = selectEl.value;
            const nombreAlimento = selectEl.options[selectEl.selectedIndex]?.text.replace(/ \(ID: \d+\)$/, '') || 'Alimento ' + idAlimento;
            const imgUrl = selectEl.options[selectEl.selectedIndex]?.getAttribute('data-img') || '';
            const cantidad = document.getElementById('ingrCantidadInput').value;

            if(!idAlimento) {
                showToast("Por favor selecciona un alimento.", "warning");
                return;
            }
            if(!cantidad || cantidad <= 0) {
                showToast("Ingresa una cantidad válida.", "warning");
                return;
            }

            const factorGramos = parseFloat(unitSel.value);
            const unitNameRaw = unitSel.options[unitSel.selectedIndex].getAttribute('data-name');
            const totalGramos = parseFloat((cantidad * factorGramos).toFixed(2));

            let equivVisual = '';
            if (factorGramos !== 1) {
                let unitName = unitNameRaw.charAt(0).toUpperCase() + unitNameRaw.slice(1);
                if (cantidad > 1 && !unitName.endsWith('s')) {
                    unitName += 's'; 
                }
                equivVisual = `<span class="text-green-600 dark:text-green-400 font-medium">${cantidad} ${unitName}</span>`;
            } else {
                equivVisual = `<span class="text-gray-500">${cantidad} Gramos</span>`;
            }

            const exists = currentIngredients.findIndex(ing => ing.id_alimento == idAlimento);
            if(exists >= 0) {
                currentIngredients[exists].cantidad_gramos = parseFloat((currentIngredients[exists].cantidad_gramos + totalGramos).toFixed(2));
                currentIngredients[exists].equivalenciaText = `<span class="text-blue-500 font-bold">Sumado en DB</span>`;
                showToast("El ingrediente ya existía, se han sumado los gramos.", "success");
            } else {
                currentIngredients.push({
                    id_alimento: parseInt(idAlimento),
                    nombre_alimento: nombreAlimento,
                    cantidad_gramos: totalGramos,
                    equivalenciaText: equivVisual,
                    url_imagen: imgUrl
                });
            }

            document.getElementById('ingrSearchInput').value = '';
            document.getElementById('ingrSelectFood').innerHTML = '<option value="">Primero busca un alimento...</option>';
            document.getElementById('ingrCantidadInput').value = '';
            unitSel.innerHTML = '<option value="1" data-name="g">Gramos (g)</option>';

            renderIngredientsTable();
        }

        function removeIngredient(index) {
            currentIngredients.splice(index, 1);
            renderIngredientsTable();
        }

        function renderIngredientsTable() {
            const tbody = document.getElementById('tbodyIngredients');
            let html = '';
            
            if(currentIngredients.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-6 text-sm text-gray-500 font-medium">Aún no hay ingredientes agregados.</td></tr>';
            } else {
                currentIngredients.forEach((ing, index) => {
                    const imgSrc = resolveAlimentoImageUrl(ing.url_imagen);
                    const imgHtml = imgSrc 
                        ? `<img src="${imgSrc}" alt="IMG" class="w-10 h-10 object-cover rounded-lg shadow-sm border border-gray-200 dark:border-gray-700" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\\'w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-700\\\'><i class=\\\'ph ph-apple-logo text-gray-400 text-xl\\\'></i></div>';">` 
                        : `<div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center border border-gray-200 dark:border-gray-700"><i class="ph ph-apple-logo text-gray-400 text-xl"></i></div>`;

                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="py-3 px-4 w-14 whitespace-nowrap">
                                ${imgHtml}
                            </td>
                            <td class="py-3 px-4">
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">${ing.nombre_alimento}</p>
                                <p class="text-[10px] text-gray-400 font-mono">ID: ${ing.id_alimento}</p>
                            </td>
                            <td class="py-3 px-4 text-sm font-medium">
                                ${ing.equivalenciaText || '-'}
                            </td>
                            <td class="py-3 px-4 text-sm text-center">
                                <span class="font-black text-blue-600 dark:text-blue-400">${ing.cantidad_gramos} g</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button type="button" onclick="removeIngredient(${index})" class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Quitar">
                                    <i class="ph ph-x-circle text-lg"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            tbody.innerHTML = html;
        }

        // --- Eliminar Platillo ---
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
                const res = await fetch(`ajax_platillos.php?action=delete&id=${deleteId}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.success || json.http_code === 204) {
                    showToast("Platillo eliminado correctamente.", "success");
                    closeDeleteModal();
                    loadPlatillos(currentPage);
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
        function searchAction(e) { if(e.key === 'Enter') loadPlatillos(1); }
        function changePage(delta) {
            const newP = currentPage + delta;
            if(newP >= 1 && newP <= totalPages) loadPlatillos(newP);
        }

        document.addEventListener('DOMContentLoaded', () => loadPlatillos(1));
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
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Platillos</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Gestión de recetas, platos y sus ingredientes (Bilingüe).</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="searchInput" onkeydown="searchAction(event)" placeholder="Buscar platillo por nombre..." class="w-full bg-white/70 dark:bg-darkbase-900/70 border border-white/50 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white shadow-sm">
                    </div>
                    <button onclick="loadPlatillos(1)" class="p-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm"><i class="ph ph-arrows-clockwise font-bold"></i></button>
                    
                    <button onclick="openFormModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                        <i class="ph ph-plus-circle text-lg"></i>
                        Nuevo Platillo
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Platillos: <span id="totalCounter" class="text-calori-600 dark:text-calori-400 font-black text-sm">--</span></span>
            </div>

            <div id="tableLoader" class="flex-1 flex flex-col items-center justify-center">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Cargando recetas mágicas...</p>
            </div>

            <div id="tableContainer" class="hidden flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">ID</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Img</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categoría</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="platillosTableBody" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
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
                <h3 id="formTitle" class="text-xl font-bold text-gray-900 dark:text-white flex items-center"><i class="ph-fill ph-cooking-pot text-calori-500 mr-2"></i> Nuevo Platillo</h3>
                <button type="button" onclick="closeFormModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-800 px-6 pt-2 shrink-0 overflow-x-auto scrollbar-hide">
                <button type="button" id="btn-tab-general" onclick="switchTab('general')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 text-calori-600 border-calori-600 whitespace-nowrap">Datos Generales</button>
                <button type="button" id="btn-tab-es" onclick="switchTab('es')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇲🇽</span> Español</button>
                <button type="button" id="btn-tab-en" onclick="switchTab('en')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 whitespace-nowrap"><span class="mr-1">🇺🇸</span> Inglés</button>
                <button type="button" id="btn-tab-ingredientes" onclick="switchTab('ingredientes')" class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 whitespace-nowrap"><i class="ph ph-apple-logo mr-1"></i>Ingredientes</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50 dark:bg-gray-900/50 relative">
                
                <form id="formPlatillo" onsubmit="savePlatillo(event)">
                    
                    <div id="tab-general" class="tab-content animate-fade-in">
                        <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                            
                            <div class="lg:col-span-6 space-y-6">
                                <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                                    <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Información Base</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Clave (Interno)</label>
                                            <input type="text" id="inp_nombre_clave" required placeholder="ej. pollo_asado_verduras" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sugerencia de Comida</label>
                                            <select id="inp_sugerencia_comida" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
                                                <option value="Desayuno">Desayuno</option>
                                                <option value="Almuerzo">Almuerzo</option>
                                                <option value="Comida">Comida</option>
                                                <option value="Cena">Cena</option>
                                                <option value="Snack">Snack</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                                    <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Multimedia</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">URL Imagen o Subir Archivo</label>
                                            <div class="flex gap-2">
                                                <input type="text" id="inp_url_imagen" oninput="updateLivePreview()" placeholder="ej. Chistorra-a-la-Mexicana.png" class="flex-1 w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none dark:text-white font-mono focus:ring-2 focus:ring-calori-500 transition-all">
                                                
                                                <button type="button" id="btnUploadImage" onclick="document.getElementById('fileUploadInput').click()" class="px-4 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-lg text-sm font-bold transition-colors border border-blue-200 dark:border-blue-800 flex items-center shrink-0">
                                                    <i class="ph ph-upload-simple mr-1 text-lg"></i> Subir
                                                </button>
                                                <input type="file" id="fileUploadInput" accept="image/*" class="hidden" onchange="compressAndUploadImage(event)">
                                            </div>
                                            
                                            <div id="compressionStats" class="hidden mt-2 p-2.5 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex items-center justify-between text-xs text-green-700 dark:text-green-400 transition-all">
                                                <span class="flex items-center"><i class="ph ph-arrows-in mr-1.5 text-lg"></i> Original: <span id="compOriginal" class="mx-1 font-mono"></span> ➔ Nueva: <span id="compNueva" class="ml-1 font-bold font-mono"></span></span>
                                                <span class="font-black bg-green-200 dark:bg-green-800/50 px-2 py-0.5 rounded uppercase tracking-wider text-green-800 dark:text-green-300">Ahorro: <span id="compAhorro"></span></span>
                                            </div>
                                            
                                            <p class="text-[10px] text-gray-400 mt-1">El sistema auto-completará el path. Base CDN: <span class="font-bold text-gray-500">https://images.caloritrack.com/platillos/</span></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Alt Text Imagen (Accesibilidad)</label>
                                            <input type="text" id="inp_alt_text_imagen" placeholder="Descripción de la imagen para lectores de pantalla..." class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm outline-none dark:text-white focus:ring-2 focus:ring-calori-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 p-4 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-900/30">
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_activo" class="w-5 h-5 text-calori-600 rounded" checked><span class="text-sm font-bold text-gray-700 dark:text-gray-300">Platillo Activo</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="inp_es_publico" class="w-5 h-5 text-blue-600 rounded" checked><span class="text-sm font-bold text-blue-700 dark:text-blue-400">Es Público (Global)</span></label>
                                </div>
                            </div>

                            <div class="lg:col-span-4 flex flex-col">
                                <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col h-full min-h-[320px] max-h-[450px]">
                                    <h4 class="font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-100 dark:border-gray-700 pb-2 flex items-center justify-between shrink-0">
                                        <span>Vista Previa del Platillo</span>
                                        <span class="text-[10px] bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-gray-500 font-bold uppercase tracking-wider">En Vivo</span>
                                    </h4>
                                    
                                    <div id="liveImagePreviewContainer" class="flex-1 w-full bg-gray-50 dark:bg-gray-900 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden relative">
                                        </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div id="tab-es" class="tab-content hidden animate-fade-in">
                        <div class="bg-white dark:bg-darkbase-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-4"><span class="text-2xl">🇲🇽</span><h4 class="font-bold text-gray-900 dark:text-white">Textos Comerciales en Español</h4></div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Comercial (display_name_es)</label>
                                    <input type="text" id="inp_nombre_es" required placeholder="Plan Déficit Moderado" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-calori-500 outline-none dark:text-white">
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

                    <div id="tab-ingredientes" class="tab-content hidden animate-fade-in space-y-6">
                        <div class="bg-blue-50/30 dark:bg-blue-900/10 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30">
                            <h4 class="font-black text-blue-900 dark:text-blue-400 flex items-center mb-1"><i class="ph-fill ph-magnifying-glass text-xl mr-2"></i> Buscar Alimento (Catálogo)</h4>
                            <p class="text-xs text-blue-600/70 dark:text-blue-400/70 mb-4">Busca un alimento, elige su presentación y nosotros calculamos los gramos por debajo.</p>
                            
                            <div class="flex flex-col md:flex-row gap-3">
                                <div class="flex-1 relative">
                                    <input type="text" id="ingrSearchInput" placeholder="Ej. pan, leche..." class="w-full bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white pr-10">
                                    <button type="button" onclick="searchFoodForIngredient()" class="absolute right-0 top-0 bottom-0 px-3 text-blue-600 dark:text-blue-400 hover:text-blue-800"><i class="ph ph-magnifying-glass font-bold"></i></button>
                                </div>
                                
                                <select id="ingrSelectFood" onchange="loadEquivalenciesForFood()" class="flex-1 bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                                    <option value="">Primero busca un alimento...</option>
                                </select>
                                
                                <div class="w-full md:w-28">
                                    <input type="number" step="0.01" id="ingrCantidadInput" placeholder="Cant." class="w-full bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white text-center">
                                </div>
                                
                                <select id="ingrSelectUnidad" class="w-full md:w-40 bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                                    <option value="1" data-name="g">Gramos (g)</option>
                                </select>
                                
                                <button type="button" onclick="addIngredient()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-md transition-all whitespace-nowrap">
                                    Agregar
                                </button>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkbase-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-14">Img</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Alimento Seleccionado</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Equivalencia</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Cantidad (g)</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Remover</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyIngredients" class="divide-y divide-gray-100 dark:divide-gray-800">
                                    </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 sticky bottom-0 bg-gray-50/90 dark:bg-gray-900/90 backdrop-blur-sm -mx-6 px-6 pb-2">
                        <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                            Guardar Platillo
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
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Platillo?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Se borrará físicamente el platillo, sus traducciones y sus ingredientes asociados. Esto no se puede deshacer.</p>
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
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400">No puedes eliminar este platillo.</p>
                <p class="text-xs text-orange-600/80 mt-2">La base de datos reporta que este platillo ya fue asignado a un plan alimenticio o a un registro en el bitácora de algún usuario activo.</p>
            </div>
            <button onclick="closeConflictModal()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>