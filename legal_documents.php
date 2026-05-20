<?php
// Nombre del archivo: legal_documents.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-20
// Versión: 1.2
// Descripción: Interfaz gráfica de la Bóveda Legal. Integrada con generación de hash por backend (PHP cURL) para evadir restricciones de subdominios.

require_once __DIR__ . '/includes/auth_protect.php';

$pageTitle = 'Bóveda Legal | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

ob_start();
?>
    <style>
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .submenu { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; max-height: 0; opacity: 0; overflow: hidden; }
        .submenu.open { max-height: 200px; opacity: 1; }
    </style>
    <script>
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

        // Sistema de Toasts Nativos
        function showToast(message, type = 'success') {
            const toast = document.getElementById('globalToast');
            const toastIcon = document.getElementById('toastIcon');
            const toastText = document.getElementById('toastText');
            
            toast.classList.remove('translate-y-full', 'opacity-0', 'bg-green-500', 'bg-red-500');
            
            if(type === 'success') {
                toast.classList.add('bg-green-500');
                toastIcon.className = 'ph ph-check-circle text-white text-xl mr-2';
            } else {
                toast.classList.add('bg-red-500');
                toastIcon.className = 'ph ph-warning-circle text-white text-xl mr-2';
            }
            
            toastText.textContent = message;
            toast.classList.remove('hidden');
            
            setTimeout(() => {
                toast.classList.add('translate-y-full', 'opacity-0');
            }, 3500);
        }

        // Variables Globales
        let documentToActivate = null;
        let documentToDelete = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadDocuments();
            initHashGenerator();
        });

        // --- AUTOGENERADOR DE HASH SHA-256 VÍA PHP ---
        function initHashGenerator() {
            const pdfInput = document.getElementById('doc_pdf');
            if (!pdfInput) return;

            pdfInput.addEventListener('blur', async function() {
                const url = this.value.trim();
                const hashInput = document.getElementById('doc_hash');
                
                // KRILLIN FIX: Ya no exigimos que termine en .pdf, solo que no esté vacía
                if (!url) return;
                
                hashInput.value = "Calculando por PHP...";
                hashInput.disabled = true;

                try {
                    // Llamamos a nuestra propia herramienta en ajax_legal.php
                    const res = await fetch('ajax_legal.php?action=generate_hash', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ url: url })
                    });
                    
                    const json = await res.json();
                    
                    if (json.success) {
                        hashInput.value = json.hash;
                        showToast("Hash SHA-256 calculado exitosamente por el servidor.", "success");
                    } else {
                        hashInput.value = "";
                        showToast("Error del servidor: " + json.message, "error");
                    }
                    
                } catch (error) {
                    console.error("Error en petición local:", error);
                    hashInput.value = "";
                    showToast("No se pudo conectar con el calculador de hash interno.", "error");
                } finally {
                    // Mantenemos el campo readonly para que el usuario no modifique el hash legítimo a menos que quiera
                    hashInput.disabled = false;
                }
            });
        }

        // --- LISTAR DOCUMENTOS ---
        async function loadDocuments() {
            const tbody = document.getElementById('docsTableBody');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8"><i class="ph ph-spinner animate-spin text-2xl text-calori-500"></i></td></tr>';
            
            try {
                const res = await fetch('ajax_legal.php?action=list');
                const json = await res.json();
                
                if (json.success && json.data) {
                    const docs = json.data.data || json.data;
                    let html = '';
                    
                    if(docs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-500">No hay documentos en la bóveda.</td></tr>';
                        return;
                    }

                    docs.forEach(doc => {
                        const typeName = doc.document_type.replace(/_/g, ' ');
                        const isActive = doc.is_active == 1;
                        const statusBadge = isActive 
                            ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded border border-green-200"><i class="ph ph-check-circle mr-1"></i>Activo</span>'
                            : '<span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs font-bold rounded border border-gray-200 dark:border-gray-700">Borrador</span>';

                        const actionActivate = !isActive 
                            ? `<button onclick="confirmActivate(${doc.id}, '${doc.document_type}')" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Publicar/Activar"><i class="ph ph-rocket-launch text-lg"></i></button>`
                            : '';
                            
                        const actionDelete = !isActive
                            ? `<button onclick="confirmDelete(${doc.id})" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar Borrador"><i class="ph ph-trash text-lg"></i></button>`
                            : '';

                        html += `
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-4 px-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">${typeName}</td>
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 font-mono">${doc.version_string}</td>
                                <td class="py-4 px-4 whitespace-nowrap">${statusBadge}</td>
                                <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${doc.created_at ? new Date(doc.created_at).toLocaleDateString() : '-'}</td>
                                <td class="py-4 px-4 whitespace-nowrap flex gap-2">
                                    ${actionActivate}
                                    ${actionDelete}
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">No se pudieron cargar los documentos.</td></tr>';
                }
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Error de conexión con Hermes.</td></tr>';
            }
        }

        // --- CREAR DOCUMENTO ---
        function openCreateModal() { document.getElementById('modalCreate').classList.remove('hidden'); }
        function closeCreateModal() { document.getElementById('modalCreate').classList.add('hidden'); document.getElementById('formCreate').reset(); }

        async function handleCreate(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitCreate');
            const hashVal = document.getElementById('doc_hash').value.trim();

            // KRILLIN FIX: Blindaje para no enviar JSON incompleto a Hermes
            if (!hashVal || hashVal.length !== 64) {
                showToast("El Hash SHA-256 no es válido. Haz clic en la URL del PDF para calcularlo automáticamente.", "error");
                return;
            }

            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Guardando...';
            btn.disabled = true;

            const payload = {
                document_type: document.getElementById('doc_type').value,
                version_string: document.getElementById('doc_version').value,
                translations: [{
                    language_code: "es-MX",
                    document_content: document.getElementById('doc_content').value,
                    pdf_url: document.getElementById('doc_pdf').value,
                    document_hash: hashVal
                }]
            };

            try {
                const res = await fetch('ajax_legal.php?action=create', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                
                if (json.http_code === 201 || json.success) {
                    showToast("Documento legal creado como borrador exitosamente.", "success");
                    closeCreateModal();
                    loadDocuments();
                } else {
                    let realError = "Error de validación.";
                    if (json.data && json.data.message) realError = json.data.message;
                    showToast("Rechazado por API: " + realError, "error");
                }
            } catch (err) {
                showToast("Error crítico de comunicación al guardar.", "error");
            } finally {
                btn.innerHTML = 'Guardar Borrador';
                btn.disabled = false;
            }
        }

        // --- ACTIVAR DOCUMENTO ---
        function confirmActivate(id, type) {
            documentToActivate = { id, type };
            document.getElementById('modalActivate').classList.remove('hidden');
        }
        function cancelActivate() {
            documentToActivate = null;
            document.getElementById('modalActivate').classList.add('hidden');
        }

        async function processActivate() {
            const btn = document.getElementById('btnConfirmActivate');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Activando...';
            btn.disabled = true;

            try {
                const res = await fetch(`ajax_legal.php?action=activate&id=${documentToActivate.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ document_type: documentToActivate.type })
                });
                const json = await res.json();

                if (json.http_code === 200 || json.success) {
                    showToast("Documento activado exitosamente. La versión anterior ha sido archivada.", "success");
                    loadDocuments();
                } else if(json.http_code === 404) {
                    showToast("El documento no existe o ya fue eliminado.", "error");
                } else {
                    showToast("Error al activar el documento.", "error");
                }
            } catch (err) {
                showToast("Error de red.", "error");
            } finally {
                cancelActivate();
                btn.innerHTML = 'Sí, Publicar';
                btn.disabled = false;
            }
        }

        // --- ELIMINAR DOCUMENTO ---
        function confirmDelete(id) {
            documentToDelete = id;
            document.getElementById('modalDelete').classList.remove('hidden');
        }
        function cancelDelete() {
            documentToDelete = null;
            document.getElementById('modalDelete').classList.add('hidden');
        }

        async function processDelete() {
            const btn = document.getElementById('btnConfirmDelete');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Borrando...';
            btn.disabled = true;

            try {
                const res = await fetch(`ajax_legal.php?action=delete&id=${documentToDelete}`, { method: 'DELETE' });
                const json = await res.json();

                if (json.http_code === 204) {
                    showToast("Documento eliminado correctamente.", "success");
                    loadDocuments();
                    cancelDelete();
                } else if (json.http_code === 409) {
                    cancelDelete();
                    document.getElementById('modalError409').classList.remove('hidden');
                } else if (json.http_code === 400) {
                    showToast("Petición malformada, falta el ID.", "error");
                    cancelDelete();
                } else {
                    showToast("No se pudo eliminar el documento.", "error");
                    cancelDelete();
                }
            } catch (err) {
                showToast("Error de red al eliminar.", "error");
                cancelDelete();
            } finally {
                btn.innerHTML = 'Sí, Eliminar';
                btn.disabled = false;
            }
        }

        function closeError409() {
            document.getElementById('modalError409').classList.add('hidden');
        }
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
            
            <div class="mb-8 flex flex-col lg:flex-row lg:items-end justify-between gap-4 shrink-0">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Bóveda Legal</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Gestión y versionado seguro de contratos y políticas (Heimdall Lock Activo).</p>
                </div>
                
                <button onclick="openCreateModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold hover:bg-gray-800 dark:hover:bg-gray-100 transition-all shadow-lg shadow-gray-900/10 dark:shadow-white/5">
                    <i class="ph ph-plus-circle text-lg"></i>
                    Nuevo Borrador
                </button>
            </div>

            <div class="flex-1 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 dark:bg-darkbase-900/80 sticky top-0 z-10 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
                            <tr>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo de Documento</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Versión</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fecha Creación</th>
                                <th class="py-4 px-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="docsTableBody" class="divide-y divide-gray-100 dark:divide-gray-800">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="modalCreate" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeCreateModal()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-gray-200 dark:border-gray-700 w-full max-w-2xl rounded-3xl p-8 shadow-2xl relative">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Redactar Nuevo Documento</h3>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><i class="ph ph-x text-xl"></i></button>
            </div>
            
            <form id="formCreate" onsubmit="handleCreate(event)" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                        <select id="doc_type" required class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-calori-500 outline-none">
                            <option value="TERMS_AND_CONDITIONS">Términos y Condiciones</option>
                            <option value="PRIVACY_POLICY">Aviso de Privacidad</option>
                            <option value="MEDICAL_DISCLAIMER">Descargo Médico</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Versión (Ej. v2.0_2026)</label>
                        <input type="text" id="doc_version" required class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-calori-500 outline-none">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Contenido (HTML / Markdown)</label>
                    <textarea id="doc_content" required rows="5" class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-calori-500 outline-none font-mono"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">URL PDF (Mismo dominio principal)</label>
                        <input type="url" id="doc_pdf" placeholder="https://caloritrack.com/..." required class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-calori-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Hash SHA-256 (Automático)</label>
                        <input type="text" id="doc_hash" readonly placeholder="Calculando al perder el foco de la URL..." class="w-full bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2 text-sm text-gray-600 dark:text-gray-300 outline-none font-mono cursor-not-allowed">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" onclick="closeCreateModal()" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 transition-colors">Cancelar</button>
                    <button type="submit" id="btnSubmitCreate" class="px-5 py-2.5 bg-calori-600 text-white font-bold rounded-xl hover:bg-calori-700 shadow-lg shadow-calori-600/30 transition-all flex items-center gap-2">
                        Guardar Borrador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalActivate" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="cancelActivate()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-white/50 dark:border-gray-700 w-full max-w-sm rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="w-16 h-16 bg-blue-50 dark:bg-blue-900/20 text-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-rocket-launch text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Publicar Versión?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">¿Estás seguro de publicar esta versión? Esto archivará automáticamente la versión anterior y todos los usuarios verán esta nueva.</p>
            
            <div class="flex gap-3">
                <button onclick="cancelActivate()" class="flex-1 py-3 px-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 transition-colors">Cancelar</button>
                <button id="btnConfirmActivate" onclick="processActivate()" class="flex-1 py-3 px-4 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-500/30 transition-all">Sí, Publicar</button>
            </div>
        </div>
    </div>

    <div id="modalDelete" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="cancelDelete()"></div>
        <div class="bg-white dark:bg-darkbase-900 border border-white/50 dark:border-gray-700 w-full max-w-sm rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Eliminar Borrador?</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">Esta acción es irreversible y borrará el documento temporal.</p>
            
            <div class="flex gap-3">
                <button onclick="cancelDelete()" class="flex-1 py-3 px-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-200 transition-colors">Cancelar</button>
                <button id="btnConfirmDelete" onclick="processDelete()" class="flex-1 py-3 px-4 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 shadow-lg shadow-red-500/30 transition-all">Sí, Eliminar</button>
            </div>
        </div>
    </div>

    <div id="modalError409" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-red-900/40 backdrop-blur-sm" onclick="closeError409()"></div>
        <div class="bg-white dark:bg-darkbase-900 border-2 border-red-500 w-full max-w-md rounded-3xl p-8 shadow-2xl relative text-center">
            <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg border-4 border-white dark:border-darkbase-900">
                <i class="ph ph-shield-warning text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-gray-900 dark:text-white mt-4 mb-2 uppercase tracking-wide">Bloqueo de Seguridad</h3>
            <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-xl border border-red-200 dark:border-red-800 mb-6">
                <p class="text-sm font-bold text-red-600 dark:text-red-400">
                    No se puede eliminar este documento porque tiene valor probatorio y ya está en uso por uno o más usuarios.
                </p>
                <p class="text-xs text-red-500/80 mt-2">La restricción fue impuesta por la base de datos MariaDB para garantizar el cumplimiento normativo.</p>
            </div>
            <button onclick="closeError409()" class="w-full py-3 px-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold rounded-xl hover:bg-gray-800 transition-all">Entendido</button>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>