<?php
// Nombre del archivo: includes/footer.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.3
// Descripción: Scripts de interacción base. Se reescribió handleLogin para consumir ajax_login.php de forma asíncrona usando fetch API, manejando errores de servidor y mostrando retroalimentación dinámica al usuario sin recargar la página.
?>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('ph-eye');
                toggleIcon.classList.add('ph-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('ph-eye-slash');
                toggleIcon.classList.add('ph-eye');
            }
        }

        async function handleLogin(event) {
            event.preventDefault();
            const btn = event.target.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            // Elementos para mostrar error
            const errorDiv = document.getElementById('errorMessage');
            const errorSpan = errorDiv.querySelector('span');

            // Capturamos valores. JS mapea 'username' (id del HTML) a la llave 'email' que exige tu API
            const email = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            // Ocultamos errores previos
            errorDiv.classList.add('hidden');
            
            // Estado de carga (Spin)
            btn.innerHTML = '<i class="ph ph-spinner animate-spin text-xl"></i>';
            btn.classList.add('opacity-90', 'cursor-not-allowed');
            
            try {
                // Petición al backend intermedio PHP
                const response = await fetch('ajax_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: email, password: password })
                });

                const data = await response.json();

                if (data.success) {
                    // Login exitoso, PHP ya creó la sesión. Redirigimos.
                    window.location.href = 'dashboard.php';
                } else {
                    // Restaurar botón y mostrar error de Heimdall
                    btn.innerHTML = originalText;
                    btn.classList.remove('opacity-90', 'cursor-not-allowed');
                    errorSpan.textContent = data.message;
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                // Error de red
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