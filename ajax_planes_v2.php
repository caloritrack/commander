<?php
// Nombre del archivo: ajax_planes_v2.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha y Hora: 2026-11-16 12:03:00 CST
// Versión: 1.8 (Validación numérica de ID/paginación, autoprotección de logs y verificación SSL activa)
// Descripción: Controlador Proxy RESTful para Planes Alimenticios (/admin/planes-v2). Asegura los parámetros de ruta contra inyecciones y valida el certificado SSL de Hermes.

require_once __DIR__ . '/includes/session.php';

function debugLogPlanesV2($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
        file_put_contents($logDir . '/.htaccess', "RewriteEngine On\nRewriteRule ^ - [F,L]\nRequire all denied");
        file_put_contents($logDir . '/index.php', "<?php http_response_code(403); exit; ?>");
    }
    $logFile = $logDir . '/planes_v2_debug.log';
    $date = date('Y-m-d H:i:s');
    $formattedMessage = (is_array($message) || is_object($message)) ? json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($logFile, "[$date] " . $formattedMessage . PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

// Validación estricta de Héroe autenticado
if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'http_code' => 401, 'message' => 'No autorizado. Vuelve a iniciar sesión.']);
    exit();
}

$token = $_SESSION['user_token'];
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');
$id = intval($_GET['id'] ?? 0);

$apiUrl = 'https://api.caloritrack.com/admin/planes-v2';

$ch = curl_init();

$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json; charset=utf-8',
    'x-calori-token: ' . CALORI_WAF_TOKEN
];

// ENRUTADOR DINÁMICO
if ($action === 'list' && $method === 'GET') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, intval($_GET['limit'] ?? 50));
    $search = urlencode(trim($_GET['search'] ?? ''));
    
    $fetchUrl = $apiUrl . "?page={$page}&limit={$limit}";
    if (!empty($search)) {
        $fetchUrl .= "&search={$search}";
    }
    curl_setopt($ch, CURLOPT_URL, $fetchUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
} 
elseif ($action === 'get' && $method === 'GET') {
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID válido requerido.']); exit(); }
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
} 
elseif ($action === 'create' && $method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($rawInput);
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput); 
} 
elseif ($action === 'update' && ($method === 'PUT' || $method === 'POST')) {
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID válido requerido.']); exit(); }
    $rawInput = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($rawInput);
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
} 
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID válido requerido.']); exit(); }
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
} 
else {
    echo json_encode(['success' => false, 'message' => 'Acción no soportada o método HTTP inválido.']);
    exit();
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// KRILLIN FIX: Protección global contra sesiones caducadas en Hermes
if ($httpCode === 401) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    session_destroy();
}

if ($httpCode === 204) {
    echo json_encode(['success' => true, 'http_code' => 204, 'message' => 'Plan V2 eliminado exitosamente']);
    exit();
}

if ($curlError) {
    debugLogPlanesV2("Error cURL Crítico: " . $curlError);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con Hermes.', 'error' => $curlError]);
    exit();
}

$decodedResponse = json_decode($response, true);

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

if ($httpCode === 409) {
    echo json_encode([
        'success' => false,
        'http_code' => 409,
        'message' => 'Restricción de BD. El key_name ya existe o el plan V2 está asociado a usuarios.',
        'data' => $decodedResponse
    ]);
    exit();
}

echo json_encode([
    'success' => ($httpCode >= 200 && $httpCode < 300),
    'http_code' => $httpCode,
    'data' => $decodedResponse
]);
?>