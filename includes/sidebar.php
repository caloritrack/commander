<?php
// Nombre del archivo: includes/sidebar.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-09
// Versión: 1.5
// Descripción: Panel lateral. Se agregó el menú principal "Analíticas" y su submenú "Schema" para consultar la estructura de datos dinámicos, y ahora el "Reporte de Clientes".

?>
    <aside id="main-sidebar" class="w-64 bg-white/80 dark:bg-darkbase-950/80 backdrop-blur-xl border-r border-white/50 dark:border-gray-800 flex flex-col z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)] transition-all duration-300">
        <div class="h-20 flex items-center px-6 border-b border-gray-100/50 dark:border-gray-800">
            <div class="w-8 h-8 flex items-center justify-center mr-3 shrink-0">
                <img src="assets/img/caloritrack-Logo-120-x-120-transparente.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sidebar-header-text">CaloriTrack</span>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="dashboard.php" class="nav-item flex items-center px-3 py-2.5 bg-calori-50 dark:bg-calori-900/20 text-calori-700 dark:text-calori-400 rounded-xl font-medium transition-colors">
                <i class="ph ph-squares-four text-xl mr-3"></i>
                <span class="nav-label">Dashboard Global</span>
            </a>

            <div>
                <button onclick="toggleSubmenu('submenu-analytics', 'icon-analytics')" class="nav-item w-full flex items-center justify-between px-3 py-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl font-medium transition-colors focus:outline-none">
                    <div class="flex items-center">
                        <i class="ph ph-chart-line-up text-xl mr-3"></i>
                        <span class="nav-label">Analíticas</span>
                    </div>
                    <i id="icon-analytics" class="ph ph-caret-down text-sm transition-transform duration-300 nav-label"></i>
                </button>
                <div id="submenu-analytics" class="submenu pl-11 pr-3">
                    <a href="schema.php" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Schema (Data)</a>
                    <a href="customers.php" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Reporte de Clientes</a>
                </div>
            </div>

            <a href="#" class="nav-item flex items-center px-3 py-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl font-medium transition-colors">
                <i class="ph ph-users text-xl mr-3"></i>
                <span class="nav-label">Usuarios y Salud</span>
            </a>

            <div>
                <button onclick="toggleSubmenu('submenu-kai', 'icon-kai')" class="nav-item w-full flex items-center justify-between px-3 py-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl font-medium transition-colors focus:outline-none">
                    <div class="flex items-center">
                        <i class="ph ph-brain text-xl mr-3"></i>
                        <span class="nav-label">Cerebro Kai (IA)</span>
                    </div>
                    <i id="icon-kai" class="ph ph-caret-down text-sm transition-transform duration-300 nav-label"></i>
                </button>
                <div id="submenu-kai" class="submenu pl-11 pr-3">
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Modelos</a>
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Conocimiento</a>
                </div>
            </div>

            <div>
                <button onclick="toggleSubmenu('submenu-eng', 'icon-eng')" class="nav-item w-full flex items-center justify-between px-3 py-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl font-medium transition-colors focus:outline-none">
                    <div class="flex items-center">
                        <i class="ph ph-cpu text-xl mr-3"></i>
                        <span class="nav-label">Ingeniería e Infra.</span>
                    </div>
                    <i id="icon-eng" class="ph ph-caret-down text-sm transition-transform duration-300 nav-label"></i>
                </button>
                <div id="submenu-eng" class="submenu open pl-11 pr-3">
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Heimdall</a>
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Hermes</a>
                </div>
            </div>
        </nav>

        <div class="p-4 border-t border-gray-100/50 dark:border-gray-800">
            <a href="#" class="nav-item flex items-center px-3 py-2.5 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl font-medium transition-colors">
                <i class="ph ph-gear text-xl mr-3"></i>
                <span class="nav-label">Configuración</span>
            </a>
        </div>
    </aside>