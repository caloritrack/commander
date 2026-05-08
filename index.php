<?php
// Redirigir a caloritrack.com con un código de estado 301 (Movido permanentemente)
header("Location: https://caloritrack.com", true, 301);

// Detener la ejecución del script para evitar que cargue cualquier otra cosa
exit;
?>