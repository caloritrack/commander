<?php
// Nombre del archivo: includes/sidebar.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.0
// Descripción: Panel lateral (Sidebar) de navegación para el Centro de Mando. Mantiene el logo oficial y contiene la estructura del menú principal y submenús expandibles.
?>
    <aside class="w-64 bg-white/80 backdrop-blur-xl border-r border-white/50 flex flex-col z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)]">
        <div class="h-20 flex items-center px-6 border-b border-gray-100/50">
            <div class="w-8 h-8 flex items-center justify-center mr-3">
                <img src="assets/img/caloritrack-Logo-120-x-120-transparente.png" alt="CaloriTrack Logo" class="w-full h-full object-contain">
            </div>
            <span class="text-xl font-bold tracking-tight text-gray-900">CaloriTrack</span>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="#" class="flex items-center px-3 py-2.5 bg-calori-50 text-calori-700 rounded-xl font-medium transition-colors">
                <i class="ph ph-squares-four text-xl mr-3"></i>
                Dashboard Global
            </a>

            <a href="#" class="flex items-center px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition-colors">
                <i class="ph ph-users text-xl mr-3"></i>
                Usuarios y Salud
            </a>

            <div>
                <button onclick="toggleSubmenu('submenu-kai', 'icon-kai')" class="w-full flex items-center justify-between px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition-colors focus:outline-none">
                    <div class="flex items-center">
                        <i class="ph ph-brain text-xl mr-3"></i>
                        Cerebro Kai (IA)
                    </div>
                    <i id="icon-kai" class="ph ph-caret-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="submenu-kai" class="submenu pl-11 pr-3">
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Modelos de Correlación</a>
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Base de Conocimiento</a>
                </div>
            </div>

            <div>
                <button onclick="toggleSubmenu('submenu-eng', 'icon-eng')" class="w-full flex items-center justify-between px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition-colors focus:outline-none">
                    <div class="flex items-center">
                        <i class="ph ph-cpu text-xl mr-3"></i>
                        Ingeniería e Infra.
                    </div>
                    <i id="icon-eng" class="ph ph-caret-down text-sm transition-transform duration-300"></i>
                </button>
                <div id="submenu-eng" class="submenu open pl-11 pr-3">
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Bases de Datos (Heimdall)</a>
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">APIs y Rutas (Hermes)</a>
                    <a href="#" class="block py-2 text-sm text-gray-500 hover:text-calori-600 transition-colors">Releases iOS (Orion)</a>
                </div>
            </div>

            <a href="#" class="flex items-center px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition-colors">
                <i class="ph ph-palette text-xl mr-3"></i>
                Activos Visuales (Helios)
            </a>
        </nav>

        <div class="p-4 border-t border-gray-100/50">
            <a href="#" class="flex items-center px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-gray-900 rounded-xl font-medium transition-colors">
                <i class="ph ph-gear text-xl mr-3"></i>
                Configuración
            </a>
        </div>
    </aside>