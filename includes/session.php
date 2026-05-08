<?php
// Nombre del archivo: includes/session.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.0
// Descripción: Configuración y arranque seguro de sesiones. Previene el secuestro de sesiones (Session Hijacking) configurando flags estrictas para las cookies de sesión.

if (session_status() === PHP_SESSION_NONE) {
    // Parámetros de seguridad para la cookie de sesión
    ini_set('session.cookie_httponly', 1); // Evita acceso a la cookie vía JavaScript (mitiga XSS)
    ini_set('session.use_only_cookies', 1); // Fuerza el uso de cookies para la sesión
    ini_set('session.cookie_samesite', 'Strict'); // Mitiga ataques CSRF
    
    // NOTA PARA PRODUCCIÓN: Descomentar la siguiente línea cuando el sitio tenga HTTPS (SSL) activado.
    // ini_set('session.cookie_secure', 1); 

    session_start();
}
?>