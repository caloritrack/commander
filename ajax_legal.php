<?php
// Nombre del archivo: ajax_legal.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-07-04
// Versión: 2.8
// Descripción: Controlador Proxy para Bóveda Legal con soporte WAF implementado para interactuar seguro con el API.

require_once __DIR__ . '/includes/session.php';

function debugLog($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
    $logFile = $logDir . '/legal_debug.log';
    $date = date('Y-m-d H:i:s');
    $formattedMessage = (is_array($message) || is_object($message)) ? json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($logFile, "[$date] " . $formattedMessage . PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'http_code' => 401, 'message' => 'No autorizado.']);
    exit();
}

$token = $_SESSION['user_token'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$docId = trim($_GET['id'] ?? '');

if ($action === 'activate' || $action === 'delete') {
    if (in_array($docId, ['undefined', 'null', 'ERROR_NO_UUID', ''])) {
        echo json_encode([
            'success' => false, 
            'http_code' => 400, 
            'message' => 'Error Interno: El botón envió un ID vacío o "undefined". El Cazador de UUIDs no pudo encontrar la llave correcta.'
        ]);
        exit();
    }
}

$apiUrl = 'https://api.caloritrack.com/admin/legal-documents';

if ($action === 'generate_hash' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdfUrl = $input['url'] ?? '';
    if (empty($pdfUrl)) { echo json_encode(['success' => false]); exit(); }

    // En esta llamada no mandamos token WAF porque es para descargar de un bucket R2 o link público externo
    $chPdf = curl_init($pdfUrl);
    curl_setopt($chPdf, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPdf, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($chPdf, CURLOPT_SSL_VERIFYPEER, false);
    $pdfContent = curl_exec($chPdf);
    $httpCodePdf = curl_getinfo($chPdf, CURLINFO_HTTP_CODE);
    curl_close($chPdf);
    
    if ($pdfContent && $httpCodePdf === 200) {
        echo json_encode(['success' => true, 'hash' => hash('sha256', $pdfContent)]);
    } else {
        echo json_encode(['success' => false, 'message' => "HTTP $httpCodePdf"]);
    }
    exit(); 
}

$ch = curl_init();
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json; charset=utf-8',
    'x-calori-token: ' . CALORI_WAF_TOKEN
];

if ($action === 'list' && $method === 'GET') {
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
} 
elseif ($action === 'create' && $method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($rawInput);
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
} 
elseif ($action === 'activate' && ($method === 'PUT' || $method === 'POST')) {
    $input = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($input);
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . urlencode($docId) . '/activate');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
} 
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . urlencode($docId));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); 
} 
else {
    echo json_encode(['success' => false, 'message' => 'Acción no soportada.']);
    exit();
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// KRILLIN FIX: Protección global contra sesiones caducadas en Hermes
if ($httpCode === 401) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    session_destroy();
}

if ($httpCode === 204) {
    echo json_encode(['success' => true, 'http_code' => 204]);
    exit();
}

if ($httpCode >= 400 && $httpCode !== 401) { debugLog("ERROR API ($httpCode): $response"); }

echo json_encode([
    'success' => ($httpCode >= 200 && $httpCode < 300),
    'http_code' => $httpCode,
    'data' => json_decode($response, true)
]);
?>