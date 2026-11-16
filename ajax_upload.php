<?php
// Nombre del archivo: ajax_upload.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha y Hora: 2026-11-16 11:58:00 CST
// Versión: 1.3 (Validación real de MIME type con finfo, límite de tamaño, whitelist de carpetas y SSL estricto)
// Descripción: Controlador Proxy para subir imágenes a Cloudflare R2 vía Hermes. Valida en servidor que los bytes correspondan a una imagen legítima y restringe los directorios destino permitidos.

require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Verificación de Seguridad: Sólo Héroes logueados pueden subir archivos
if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'http_code' => 401, 'message' => 'No autorizado. Vuelve a iniciar sesión.']);
    exit();
}

$token = $_SESSION['user_token'];

// 2. Verificación de archivo
if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen.']);
    exit();
}

$file = $_FILES['image'];

// [KRILLIN SECURITY] Whitelist estricta para el directorio destino en R2
$allowedFolders = ['general', 'food', 'platillos'];
$requestedFolder = trim($_POST['folder'] ?? 'general');
$folder = in_array($requestedFolder, $allowedFolders, true) ? $requestedFolder : 'general';

// Validar errores de subida nativos de PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar el archivo temporal en el servidor PHP. Código: ' . $file['error']]);
    exit();
}

// Validar tamaño máximo (10 MB)
$maxBytes = 10 * 1024 * 1024;
if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (10 MB) o está vacío.']);
    exit();
}

// [KRILLIN SECURITY] Inspección real de bytes (MIME Type) en el servidor
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = [
    'image/webp' => 'webp',
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif'
];

if (!array_key_exists($realMimeType, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'message' => 'Formato de archivo no permitido (' . htmlspecialchars($realMimeType) . '). Sólo se aceptan imágenes WebP, JPG, PNG o GIF.']);
    exit();
}

// Sanitizar el nombre del archivo enviado
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
$safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName) ?: 'imagen_caloritrack';
$safeFileName = $safeBaseName . '.' . $allowedMimeTypes[$realMimeType];

// 3. Empaquetar y mandar a la API de Hermes
$cfile = new CURLFile($file['tmp_name'], $realMimeType, $safeFileName);
$postData = [
    'image' => $cfile,
    'folder' => $folder
];

$apiUrl = 'https://api.caloritrack.com/admin/upload-image'; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'x-calori-token: ' . CALORI_WAF_TOKEN
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

// 4. Manejo de Errores de Red
if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con Hermes.', 'error' => $curlError]);
    exit();
}

$decoded = json_decode($response, true);

// 5. Devolver la respuesta al Frontend
if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode([
        'success' => true, 
        'data' => isset($decoded['data']) ? $decoded['data'] : $decoded
    ]);
} else {
    $errorMsg = isset($decoded['message']) ? $decoded['message'] : 'Hermes rechazó el archivo (Error ' . $httpCode . ').';
    echo json_encode(['success' => false, 'http_code' => $httpCode, 'message' => $errorMsg]);
}
?>