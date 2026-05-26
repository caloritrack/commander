<?php
// Nombre del archivo: ajax_planes_v2.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-25
// Versión: 1.5
// Descripción: Controlador Proxy RESTful aislado. Se eliminó el "Filtro Purificador" que corrompía el JSON y se restauró la cabecera Content-Length para garantizar compatibilidad con el body-parser de Hermes.

require_once __DIR__ . '/includes/session.php';

// Sistema de logs aislados para depuración de la V2
function debugLogPlanesV2($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
    $logFile = $logDir . '/planes_v2_debug.log';
    $date = date('Y-m-d H:i:s');
    $formattedMessage = (is_array($message) || is_object($message)) ? json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($logFile, "[$date] " . $formattedMessage . PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

// Validación estricta de Héroe autenticado
if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Vuelve a iniciar sesión.']);
    exit();
}

$token = $_SESSION['user_token'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = trim($_GET['id'] ?? '');

// Apuntamos al nuevo endpoint V2
$apiUrl = 'https://api.caloritrack.com/admin/planes-v2';

$ch = curl_init();

$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json; charset=utf-8'
];

// ENRUTADOR DINÁMICO
if ($action === 'list' && $method === 'GET') {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $search = urlencode($_GET['search'] ?? '');
    
    $fetchUrl = $apiUrl . "?page={$page}&limit={$limit}";
    if (!empty($search)) {
        $fetchUrl .= "&search={$search}";
    }
    curl_setopt($ch, CURLOPT_URL, $fetchUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
} 
elseif ($action === 'get' && $method === 'GET') {
    if(empty($id)) { echo json_encode(['success' => false, 'message' => 'ID requerido.']); exit(); }
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
} 
elseif ($action === 'create' && $method === 'POST') {
    $rawInput = file_get_contents('php://input');
    
    // KR-FIX: Calculamos y enviamos la longitud exacta para evitar que Node.js rechace el JSON
    $headers[] = 'Content-Length: ' . strlen($rawInput);

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput); 
} 
elseif ($action === 'update' && ($method === 'PUT' || $method === 'POST')) {
    if(empty($id)) { echo json_encode(['success' => false, 'message' => 'ID requerido.']); exit(); }
    $rawInput = file_get_contents('php://input');
    
    // KR-FIX: También en PUT para mantener la integridad de actualización
    $headers[] = 'Content-Length: ' . strlen($rawInput);

    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
} 
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    if(empty($id)) { echo json_encode(['success' => false, 'message' => 'ID requerido.']); exit(); }
    
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
} 
else {
    echo json_encode(['success' => false, 'message' => 'Acción no soportada o método HTTP inválido.']);
    exit();
}

// Configuración Base cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Manejo de Respuesta HTTP 204 (Delete exitoso sin contenido)
if ($httpCode === 204) {
    echo json_encode(['success' => true, 'http_code' => 204, 'message' => 'Plan V2 eliminado exitosamente']);
    exit();
}

// Error crítico de red
if ($curlError) {
    debugLogPlanesV2("Error cURL Crítico: " . $curlError);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con Hermes.', 'error' => $curlError]);
    exit();
}

$decodedResponse = json_decode($response, true);

// KR-FIX: Revelar el mensaje REAL de Hermes si revienta con un 500
if ($httpCode >= 500) {
    $hermesExactError = $decodedResponse['message'] ?? $decodedResponse['error'] ?? 'No se especificó la causa exacta en Node.';
    debugLogPlanesV2("CRASHEO HERMES 500: " . $response);
    echo json_encode([
        'success' => false,
        'http_code' => $httpCode,
        'message' => 'Hermes dice: ' . $hermesExactError,
        'data' => [ 'error' => $hermesExactError ]
    ]);
    exit();
}

// Manejo de Error 409 (Conflicto)
if ($httpCode === 409) {
    echo json_encode([
        'success' => false,
        'http_code' => 409,
        'message' => 'Restricción de BD. El key_name ya existe o el plan V2 está asociado a usuarios.',
        'data' => $decodedResponse
    ]);
    exit();
}

// Respuesta Estándar
echo json_encode([
    'success' => ($httpCode >= 200 && $httpCode < 300),
    'http_code' => $httpCode,
    'data' => $decodedResponse
]);
?>