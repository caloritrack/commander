<?php
// Nombre del archivo: session.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-20
// Versión: 2.0
// Descripción: Inicialización centralizada de sesiones para Commander. Se aumentó el tiempo de expiración de las cookies y del recolector de basura del servidor a 60 días para mantener la sesión de los administradores activa de forma prolongada y evitar el reingreso diario.

// Definir el tiempo de vida de la sesión (60 días en segundos)
$dias_sesion = 60;
$segundos_sesion = 60 * 60 * 24 * $dias_sesion;

// 1. Configurar el Garbage Collector del servidor para que no borre el archivo temporal
ini_set('session.gc_maxlifetime', $segundos_sesion);

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