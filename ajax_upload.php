<?php
// Nombre del archivo: ajax_upload.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-27
// Versión: 1.1
// Descripción: Controlador Proxy para recibir imágenes comprimidas desde el frontend (WebP) y subirlas a la API de Hermes. Se corrigió el error 404 restaurando el endpoint correcto (/admin/upload-image).

require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificación de Seguridad: Sólo Héroes logueados pueden subir archivos
if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Vuelve a iniciar sesión.']);
    exit();
}

$token = $_SESSION['user_token'];

// 2. Verificación de archivo
if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen.']);
    exit();
}

$file = $_FILES['image'];
$folder = $_POST['folder'] ?? 'general'; // Para separar en R2: /food o /platillos

// Validar errores de subida nativos de PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar el archivo temporal en el servidor PHP. Código: ' . $file['error']]);
    exit();
}

// 3. Empaquetar y mandar a la API de Hermes
// Preparamos el archivo con CURLFile (Evita el deprecado '@')
$cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
$postData = [
    'image' => $cfile,
    'folder' => $folder
];

// EL FIX ESTÁ AQUÍ: Restauramos la ruta correcta que Hermes ya conoce
$apiUrl = 'https://api.caloritrack.com/admin/upload-image'; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// KRILLIN FIX: Si Hermes nos batea por token caducado, matamos la sesión PHP
if ($httpCode === 401) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
}
// ------------------

// 4. Manejo de Errores de Red
if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con Hermes.', 'error' => $curlError]);
    exit();
}

$decoded = json_decode($response, true);

// 5. Devolver la respuesta al Frontend
if ($httpCode >= 200 && $httpCode < 300) {
    // Éxito: devolvemos la ruta o nombre final para que el Javascript lo ponga en el input de texto
    echo json_encode([
        'success' => true, 
        'data' => isset($decoded['data']) ? $decoded['data'] : $decoded
    ]);
} else {
    // Falla: Hermes rechazó el archivo
    $errorMsg = isset($decoded['message']) ? $decoded['message'] : 'Hermes rechazó el archivo (Error ' . $httpCode . ').';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}
?>