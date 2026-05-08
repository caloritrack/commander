<?php
// Nombre del archivo: logout.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.0
// Descripción: Controlador para cerrar sesión. Destruye la variable de sesión, borra la cookie del navegador de forma segura y redirige a la pantalla de entrada.

require_once __DIR__ . '/includes/session.php';

// Vaciamos las variables de sesión
$_SESSION = [];

// Si se desea destruir la sesión completamente, borramos también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruimos la sesión final
session_destroy();

// Redirigimos al área de acceso
header("Location: 3ntr4r.php");
exit();
?>