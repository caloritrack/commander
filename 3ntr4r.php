<?php
// Nombre del archivo: 3ntr4r.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.5
// Descripción: Se agregó la verificación de sesión inicial para redirigir al dashboard automáticamente si el héroe ya está autenticado.

// Verificamos si ya hay sesión activa
require_once __DIR__ . '/includes/session.php';
if (isset($_SESSION['user_token'])) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = 'Portal de Héroes | Caloritrack';
$bodyClass = 'bg-wellness min-h-screen flex items-center justify-center p-4 font-sans text-gray-800 antialiased relative overflow-hidden';

// Incluimos la cabecera global
require_once __DIR__ . '/includes/header.php';
?>

    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-pulse"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-pulse" style="animation-delay: 2s;"></div>

    <div class="max-w-md w-full bg-white/70 backdrop-blur-xl shadow-2xl rounded-3xl p-8 relative z-10 animate-fade-in border border-white/40">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center mb-4">
                <img src="assets/img/caloritrack-Logo-120-x-120-transparente.png" 
                     alt="Caloritrack Logo" 
                     class="w-24 h-24 object-contain drop-shadow-md">
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">CaloriTrack</h1>
            <p class="text-sm text-gray-500 mt-1 font-medium">Portal de Héroes</p>
        </div>

        <form id="loginForm" class="space-y-6" onsubmit="handleLogin(event)">
            
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1 ml-1">Usuario o Correo</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="ph ph-user text-gray-400 text-lg"></i>
                    </div>
                    <input type="text" id="username" name="username" required 
                        class="block w-full pl-11 pr-4 py-3 bg-white/50 border border-gray-200 rounded-2xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-calori-500 focus:border-transparent transition-all duration-200"
                        placeholder="genesis@caloritrack.com">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1 ml-1 pr-1">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Contraseña</label>
                    <a href="#" class="text-xs font-medium text-calori-600 hover:text-calori-700 transition-colors">¿Olvidaste tu acceso?</a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="ph ph-lock-key text-gray-400 text-lg"></i>
                    </div>
                    <input type="password" id="password" name="password" required 
                        class="block w-full pl-11 pr-12 py-3 bg-white/50 border border-gray-200 rounded-2xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-calori-500 focus:border-transparent transition-all duration-200"
                        placeholder="••••••••">
                    
                    <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                        <i class="ph ph-eye text-lg" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div id="errorMessage" class="hidden flex items-center gap-2 text-red-600 text-sm mt-2 p-3 bg-red-50 rounded-xl border border-red-100">
                <i class="ph ph-warning-circle text-lg"></i>
                <span>Credenciales incorrectas. Intenta de nuevo.</span>
            </div>

            <button type="submit" 
                class="w-full flex items-center justify-center py-3 px-4 border border-transparent rounded-2xl shadow-sm text-sm font-bold text-white bg-calori-600 hover:bg-calori-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-calori-500 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0">
                Ingresar al Centro de Mando
                <i class="ph ph-arrow-right ml-2 text-lg"></i>
            </button>
        </form>

        <div class="mt-8 text-center text-xs text-gray-400">
            <p>Conexión segura y encriptada por Heimdall.</p>
        </div>
    </div>

<?php
// Incluimos el pie de página global
require_once __DIR__ . '/includes/footer.php';
?>