<?php
// Nombre del archivo: includes/auth_protect.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.0
// Descripción: Middleware de protección de rutas. Se asegura de que exista una sesión válida (user_token) antes de renderizar la página. Si no existe, redirige al login.

require_once __DIR__ . '/session.php';

// Si no existe el token de sesión, expulsar al login
if (!isset($_SESSION['user_token'])) {
    header("Location: 3ntr4r.php");
    exit();
}
?>