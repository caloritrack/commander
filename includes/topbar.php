<?php
// Nombre del archivo: includes/topbar.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.1
// Descripción: Se agregó el botón de Cerrar Sesión enrutado al archivo de destrucción de variables (logout.php) para blindar el flujo de autenticación completo.
?>
        <header class="h-20 bg-white/40 backdrop-blur-md border-b border-white/50 flex items-center justify-between px-8 z-10">
            
            <div class="flex-1 max-w-xl">
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                    <input type="text" placeholder="Buscar usuarios, métricas o comandos rápidos (⌘K)..." 
                           class="w-full bg-white/60 border border-gray-200 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-calori-500 focus:bg-white transition-all">
                </div>
            </div>

            <div class="flex items-center space-x-5 ml-4">
                
                <a href="logout.php" class="relative p-2 text-red-400 hover:text-red-600 transition-colors rounded-full hover:bg-white/50" title="Cerrar Sesión">
                    <i class="ph ph-sign-out text-xl"></i>
                </a>

                <button class="relative p-2 text-gray-400 hover:text-gray-600 transition-colors rounded-full hover:bg-white/50" title="Notificaciones">
                    <i class="ph ph-bell text-xl"></i>
                    <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full"></span>
                </button>
                
                <div class="w-px h-6 bg-gray-200"></div>

                <button class="flex items-center space-x-3 text-left focus:outline-none hover:opacity-80 transition-opacity">
                    <div class="relative">
                        <img src="https://i.pravatar.cc/150?u=arturo" alt="Avatar" class="w-10 h-10 rounded-full border-2 border-white shadow-sm">
                        <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-bold text-gray-900 leading-tight">Arturo</p>
                        <p class="text-xs text-calori-600 font-medium">CEO & CPO</p>
                    </div>
                    <i class="ph ph-caret-down text-gray-400 text-xs hidden md:block"></i>
                </button>
            </div>
        </header>