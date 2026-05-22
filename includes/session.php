<?php
// Nombre del archivo: session.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-22
// Versión: 2.1
// Descripción: Inicialización centralizada de sesiones para Commander. Se implementó una ruta local aislada de almacenamiento de sesiones ('sessions_data') para evitar que el Garbage Collector del servidor (AlmaLinux/CyberPanel) elimine las sesiones durante la madrugada. Se configuró a PHP para auto-gestionar la limpieza respetando los 60 días de vigencia.

// Definir el tiempo de vida de la sesión (60 días en segundos)
$dias_sesion = 60;
$segundos_sesion = 60 * 60 * 24 * $dias_sesion;

// KRILLIN FIX: Aislamos las sesiones en una carpeta propia dentro de 'includes'
// para que el cron de limpieza nocturno del servidor no nos las elimine.
$session_path = __DIR__ . '/sessions_data';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
    // Creamos un .htaccess rápido por seguridad para evitar cualquier acceso web directo
    file_put_contents($session_path . '/.htaccess', 'Require all denied');
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
// Así, los 60 días se cuentan desde el ÚLTIMO día que usaste el Commander.
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(), 
        $_COOKIE[session_name()], 
        time() + $segundos_sesion, 
        '/', 
        '', 
        true, 
        true
    );
}
?>