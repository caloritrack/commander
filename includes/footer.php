<?php
// Nombre del archivo: includes/footer.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.4
// Descripción: Pie de página global. Se añadió el aviso legal con año dinámico y el sistema de Modal de Confirmación para el cierre de sesión.

?>
    <footer class="absolute bottom-4 right-8 z-0 text-[10px] text-gray-400 dark:text-gray-600 font-medium">
        &copy; <?php echo date('Y'); ?> CaloriTrack &reg;, Todos los Derechos Reservados.
    </footer>

    <div id="logoutModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/20 dark:bg-black/60 backdrop-blur-sm" onclick="hideLogoutModal()"></div>
        <div class="bg-white/90 dark:bg-darkbase-900/90 backdrop-blur-xl border border-white/50 dark:border-gray-700 w-full max-w-sm rounded-3xl p-8 shadow-2xl relative animate-fade-in">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-50 dark:bg-red-900/20 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-warning-circle text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">¿Cerrar Sesión?</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">¿Estás seguro de que deseas salir del Centro de Mando, Arturo? La conexión segura se terminará.</p>
                
                <div class="flex gap-3">
                    <button onclick="hideLogoutModal()" class="flex-1 py-3 px-4 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold rounded-2xl hover:bg-gray-200 transition-colors">
                        Cancelar
                    </button>
                    <a href="logout.php" class="flex-1 py-3 px-4 bg-red-500 text-white font-bold rounded-2xl hover:bg-red-600 shadow-lg shadow-red-500/30 transition-all text-center">
                        Sí, salir
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }
        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // --- Funciones de UI Heredadas ---
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('ph-eye', 'ph-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('ph-eye-slash', 'ph-eye');
            }
        }

        async function handleLogin(event) {
            event.preventDefault();
            const btn = event.target.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            const errorDiv = document.getElementById('errorMessage');
            const errorSpan = errorDiv.querySelector('span');
            const email = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            errorDiv.classList.add('hidden');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin text-xl"></i>';
            btn.classList.add('opacity-90', 'cursor-not-allowed');
            
            try {
                const response = await fetch('ajax_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, password: password })
                });
                const data = await response.json();
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    btn.innerHTML = originalText;
                    btn.classList.remove('opacity-90', 'cursor-not-allowed');
                    errorSpan.textContent = data.message;
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-90', 'cursor-not-allowed');
                errorSpan.textContent = 'Error de conexión con el servidor.';
                errorDiv.classList.remove('hidden');
            }
        }
    </script>
    <?php echo $extraScripts ?? ''; ?>
</body>
</html>