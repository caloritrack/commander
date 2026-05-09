<?php
// Nombre del archivo: schema.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.1
// Descripción: Pantalla de Auto-descubrimiento del Esquema. Se añadió una tarjeta inferior que expone el JSON crudo con un botón de copiado rápido al portapapeles.

// 0. Protección de ruta
require_once __DIR__ . '/includes/auth_protect.php';

// 1. Configuramos las variables para el header
$pageTitle = 'Schema de Analíticas | Portal de Héroes Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex font-sans text-gray-800 antialiased overflow-hidden relative';

// CSS y JS Específico
ob_start();
?>
    <style>
        .submenu { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; max-height: 0; opacity: 0; overflow: hidden; }
        .submenu.open { max-height: 200px; opacity: 1; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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

        document.addEventListener('DOMContentLoaded', async function() {
            const container = document.getElementById('schemaContainer');
            const loader = document.getElementById('schemaLoader');
            
            try {
                const res = await fetch('ajax_analytics.php?action=schema');
                const json = await res.json();
                
                if(json.success && json.data) {
                    // Renderizamos las listas visuales
                    renderList('listCategories', json.data.categories, 'ph-folder-notch', 'text-blue-500', 'bg-blue-50 dark:bg-blue-900/20');
                    renderList('listEvents', json.data.events, 'ph-cursor-click', 'text-purple-500', 'bg-purple-50 dark:bg-purple-900/20');
                    renderList('listMetadata', json.data.metadata_keys, 'ph-tag', 'text-green-500', 'bg-green-50 dark:bg-green-900/20');
                    
                    // Inyectamos el JSON crudo en la nueva tarjeta
                    document.getElementById('rawSchemaContent').textContent = JSON.stringify(json.data, null, 4);

                    loader.classList.add('hidden');
                    container.classList.remove('hidden');
                }
            } catch (error) {
                console.error("Error al cargar el schema:", error);
            }
        });

        function renderList(elementId, items, icon, iconColor, bgBadge) {
            const el = document.getElementById(elementId);
            el.innerHTML = '';
            items.forEach(item => {
                el.innerHTML += `
                    <div class="flex items-center p-3 mb-3 bg-gray-50 dark:bg-darkbase-900 rounded-xl border border-gray-100 dark:border-gray-800 transition-colors">
                        <div class="p-1.5 rounded-lg ${bgBadge} mr-3">
                            <i class="ph ${icon} ${iconColor} text-lg"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 font-mono">${item}</span>
                    </div>
                `;
            });
        }

        // Nueva función para copiar el JSON al portapapeles
        function copySchemaToClipboard() {
            const rawText = document.getElementById('rawSchemaContent').textContent;
            navigator.clipboard.writeText(rawText).then(() => {
                const btnIcon = document.getElementById('copyIcon');
                
                // Efecto visual de éxito
                btnIcon.classList.replace('ph-copy', 'ph-check-circle');
                btnIcon.classList.replace('text-gray-400', 'text-green-500');
                
                // Restauramos el ícono después de 2 segundos
                setTimeout(() => {
                    btnIcon.classList.replace('ph-check-circle', 'ph-copy');
                    btnIcon.classList.replace('text-green-500', 'text-gray-400');
                }, 2000);
            }).catch(err => {
                console.error('Error al copiar al portapapeles: ', err);
            });
        }
    </script>
<?php
$extraHead = ob_get_clean();
$extraScripts = '';

// 2. Incluimos Header Global
require_once __DIR__ . '/includes/header.php';
?>

    <div class="absolute top-[-10%] left-[20%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[10%] w-[500px] h-[500px] bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-40 animate-pulse pointer-events-none" style="animation-delay: 2s;"></div>

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden z-10 relative">
        
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-y-auto p-8">
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight transition-colors">Diccionario de Datos (Schema)</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1 transition-colors">Estructura autodescubierta en tiempo real desde la API de Hermes.</p>
            </div>

            <div id="schemaLoader" class="flex flex-col items-center justify-center h-64">
                <i class="ph ph-spinner animate-spin text-4xl text-calori-500 mb-4"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">Consultando la estructura de Hermes...</p>
            </div>

            <div id="schemaContainer" class="hidden grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm transition-all">
                    <div class="flex items-center mb-6">
                        <i class="ph ph-folder-open text-2xl text-blue-500 mr-2"></i>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Categorías</h2>
                    </div>
                    <div id="listCategories" class="h-[400px] overflow-y-auto pr-2"></div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm transition-all">
                    <div class="flex items-center mb-6">
                        <i class="ph ph-lightning text-2xl text-purple-500 mr-2"></i>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Eventos</h2>
                    </div>
                    <div id="listEvents" class="h-[400px] overflow-y-auto pr-2"></div>
                </div>

                <div class="bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm transition-all">
                    <div class="flex items-center mb-6">
                        <i class="ph ph-brackets-curly text-2xl text-green-500 mr-2"></i>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Llaves de Metadata</h2>
                    </div>
                    <div id="listMetadata" class="h-[400px] overflow-y-auto pr-2"></div>
                </div>

                <div class="md:col-span-3 bg-white/70 dark:bg-darkbase-950/90 backdrop-blur-lg border border-white/40 dark:border-gray-800 p-6 rounded-2xl shadow-sm transition-all mt-2">
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center">
                            <i class="ph ph-file-code text-2xl text-gray-500 mr-2"></i>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Schema Crudo (JSON)</h2>
                        </div>
                        <button onclick="copySchemaToClipboard()" class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg transition-colors text-sm font-medium focus:outline-none">
                            <i id="copyIcon" class="ph ph-copy text-lg text-gray-400"></i> Copiar
                        </button>
                    </div>
                    <div class="bg-gray-50 dark:bg-darkbase-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 overflow-x-auto">
                        <pre id="rawSchemaContent" class="text-sm font-mono text-gray-700 dark:text-gray-300"></pre>
                    </div>
                </div>

            </div>

        </div>
    </main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>