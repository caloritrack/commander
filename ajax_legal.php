<?php
// Nombre del archivo: ajax_legal.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-20
// Versión: 2.4
// Descripción: Controlador Proxy para Bóveda Legal. Corregida la ruta de activación (PUT) para asegurar que el UUID viaje correctamente hacia la API de Hermes.

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
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

$token = $_SESSION['user_token'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$docId = $_GET['id'] ?? ''; // KRILLIN: Ahora este parámetro recibe el UUID del documento para la API
$apiUrl = 'https://api.caloritrack.com/admin/legal-documents';

// --------------------------------------------------------------------------------------
// GENERADOR DE HASH SHA-256
// --------------------------------------------------------------------------------------
if ($action === 'generate_hash' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdfUrl = $input['url'] ?? '';
    if (empty($pdfUrl)) { echo json_encode(['success' => false]); exit(); }

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

// --------------------------------------------------------------------------------------
// COMUNICACIÓN CON HERMES (API)
// --------------------------------------------------------------------------------------
$ch = curl_init();
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json; charset=utf-8'
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
    debugLog("Enviando petición de creación a Hermes con URLs limpias.");
} 
elseif ($action === 'activate' && $method === 'PUT') {
    $input = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($input);
    
    // KRILLIN FIX: Ahora concatena de forma limpia el UUID enviado por el cliente
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $docId . '/activate');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    debugLog("Enviando PUT de activación a Hermes para UUID: $docId");
} 
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $docId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); 
    debugLog("Enviando DELETE a Hermes para UUID: $docId");
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

if ($httpCode === 204) {
    echo json_encode(['success' => true, 'http_code' => 204]);
    exit();
}

if ($httpCode >= 400) { debugLog("ERROR API ($httpCode): $response"); }

echo json_encode([
    'success' => ($httpCode >= 200 && $httpCode < 300),
    'http_code' => $httpCode,
    'data' => json_decode($response, true)
]);
?>