<?php
//
// Nombre del archivo: 404.php
// Autor: Arturo Enriquez Betancourt con Genesis
// Fecha y Hora: 2026-05-06 09:36:25 CST
// Versión: 2.0.0 (Redirección 301 permanente al dominio principal)
// Descripción: Intercepta errores de ruta en el subdominio accounts y redirige el tráfico silenciosamente a la página principal de CaloriTrack para proteger la estructura de directorios.
//

header("Location: https://caloritrack.com", true, 301);
exit;
?>