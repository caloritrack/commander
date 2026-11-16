<?php
// Nombre del archivo: includes/session.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha y Hora: 2026-11-16 11:45:00 CST
// Versión: 2.4 (Corrección de SameSite=Strict en Rolling Session y adición de cabeceras HTTP de seguridad)
// Descripción: Inicialización centralizada de sesiones para Commander. Carga credenciales WAF, emite cabeceras de seguridad anti-Clickjacking/MIME-sniffing, preserva SameSite=Strict en cada renovación y valida la vigencia del JWT.

// Cargamos la configuración de seguridad de la API (WAF)
require_once __DIR__ . '/api_security.php';

// Emitir cabeceras HTTP de seguridad globales
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Definir el tiempo de vida de la sesión (60 días en segundos)
$dias_sesion = 60;
$segundos_sesion = 60 * 60 * 24 * $dias_sesion;

// Aislamos las sesiones en una carpeta propia dentro de 'includes'
// para que el cron de limpieza nocturno del servidor no nos las elimine.
$session_path = __DIR__ . '/sessions_data';
if (!is_dir($session_path)) {
    mkdir($session_path, 0750, true);
    // Creamos un .htaccess y un index.php por seguridad para evitar cualquier acceso web directo
    file_put_contents($session_path . '/.htaccess', "RewriteEngine On\nRewriteRule ^ - [F,L]\nRequire all denied");
    file_put_contents($session_path . '/index.php', "<?php http_response_code(403); exit; ?>");
}
session_save_path($session_path);

// 1. Configurar el Garbage Collector del servidor para nuestra carpeta
ini_set('session.gc_maxlifetime', $segundos_sesion);

// Le decimos a PHP que él mismo se encargue de limpiar esta carpeta con su propio GC
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// 2. Configurar parámetros estrictos y de larga duración para la Cookie de PHP
session_set_cookie_params([
    'lifetime' => $segundos_sesion,
    'path' => '/',
    'secure' => true,      // Exigir HTTPS (nuestro estándar en CyberPanel/Hostinger)
    'httponly' => true,    // Bloquear acceso desde JavaScript (Previene XSS)
    'samesite' => 'Strict' // Prevenir ataques CSRF
]);

// 3. Levantar la sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Renovar la vigencia de la cookie cada vez que el admin navega (Rolling Session)
// Usamos el formato de arreglo asociativo para preservar intacto el atributo SameSite=Strict.
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], [
        'expires' => time() + $segundos_sesion,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// =====================================================================
// 5. [KRILLIN FIX] EL CERBERO DE PHP: Validación del Token JWT
// =====================================================================
if (isset($_SESSION['user_token']) && !empty($_SESSION['user_token'])) {
    $tokenParts = explode('.', $_SESSION['user_token']);

    // Un JWT válido siempre tiene 3 partes separadas por puntos
    if (count($tokenParts) === 3) {
        // Decodificamos el payload (reemplazamos caracteres de Base64Url a Base64 normal por seguridad)
        $base64 = str_replace(['-', '_'], ['+', '/'], $tokenParts[1]);
        $payload = json_decode(base64_decode($base64), true);
        
        // Verificamos si el JWT tiene fecha de caducidad
        if (isset($payload['exp'])) {
            $fechaCaducidad = $payload['exp'];
            
            // Validamos si la fecha actual ya superó la de caducidad
            // (Le damos 5 segundos de margen por desface de relojes de servidores)
            if (time() >= ($fechaCaducidad - 5)) { 
                
                // ¡El token ya valió! Matamos la sesión desde el origen
                session_unset();
                session_destroy();
                
                // Borramos la cookie de la sesión manteniendo los flags de seguridad
                if (isset($_COOKIE[session_name()])) {
                    setcookie(session_name(), '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
                
                // Detectar si la petición que disparó esto es AJAX/Fetch o una navegación de página normal
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
                
                if ($isAjax) {
                    // Si es un AJAX, le devolvemos un 401 para que el Javascript del <head> levante el modal chulo
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'http_code' => 401, 'message' => 'Sesión expirada.']);
                    exit();
                } else {
                    // Si diste clic a una opción del menú y era navegación normal, te manda a logout
                    header("Location: logout.php");
                    exit();
                }
            }
        }
    }
}
?>