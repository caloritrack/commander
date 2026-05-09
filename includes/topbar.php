<?php
// Nombre del archivo: includes/topbar.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.2
// Descripción: Barra superior. Se integraron los botones de control para colapsar el sidebar y alternar entre modo claro y oscuro.

?>
        <header class="h-20 bg-white/40 dark:bg-darkbase-900/40 backdrop-blur-md border-b border-white/50 dark:border-gray-800 flex items-center justify-between px-8 z-10 transition-colors">
            
            <div class="flex items-center gap-4 flex-1 max-w-xl">
                <button onclick="toggleSidebar()" class="p-2 text-gray-500 hover:bg-white/50 dark:hover:bg-gray-800 rounded-xl transition-all">
                    <i class="ph ph-list text-2xl"></i>
                </button>

                <div class="relative w-full">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                    <input type="text" placeholder="Buscar métricas o comandos (⌘K)..." 
                           class="w-full bg-white/60 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 transition-all dark:text-white">
                </div>
            </div>

            <div class="flex items-center space-x-4 ml-4">
                
                <button onclick="toggleTheme()" class="p-2.5 text-gray-500 dark:text-yellow-400 hover:bg-white/50 dark:hover:bg-gray-800 rounded-xl transition-all" title="Cambiar Tema">
                    <i class="ph ph-sun-dim hidden dark:block text-xl"></i>
                    <i class="ph ph-moon block dark:hidden text-xl"></i>
                </button>

                <button onclick="showLogoutModal()" class="p-2.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all" title="Cerrar Sesión">
                    <i class="ph ph-sign-out text-xl"></i>
                </button>

                <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>

                <div class="flex items-center space-x-3 text-left">
                    <div class="relative">
                        <img src="https://i.pravatar.cc/150?u=arturo" alt="Avatar" class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-700 shadow-sm">
                        <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"></div>
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">Arturo</p>
                        <p class="text-xs text-calori-600 dark:text-calori-500 font-medium">CEO & CPO</p>
                    </div>
                </div>
            </div>
        </header>

        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('main-sidebar');
                sidebar.classList.toggle('sidebar-collapsed');
            }

            function toggleTheme() {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            }
        </script>