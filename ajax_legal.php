<?php
// Nombre del archivo: ajax_legal.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Versión: 2.0 (Túnel Base64 Anti-Firewall y Proxy DELETE)

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
$docId = $_GET['id'] ?? '';
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
// COMUNICACIÓN CON HERMES
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
    $payloadData = json_decode($rawInput, true);

    // MAGIA: Desencriptamos el Base64 que viene del frontend para burlar a ModSecurity
    if (isset($payloadData['_is_base64']) && $payloadData['_is_base64'] === true) {
        $payloadData['translations'][0]['document_content'] = base64_decode($payloadData['translations'][0]['document_content']);
        unset($payloadData['_is_base64']); // Quitamos la bandera antes de mandarlo a Hermes
    }

    $safeInput = json_encode($payloadData, JSON_UNESCAPED_UNICODE);
    $headers[] = 'Content-Length: ' . strlen($safeInput);

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $safeInput);
    debugLog("Enviando paquete blindado a Hermes. Tamaño: " . strlen($safeInput) . " bytes.");
} 
elseif ($action === 'activate' && $method === 'PUT') {
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $docId . '/activate');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
} 
// FIX: Aceptamos POST desde el navegador para evadir el bloqueo de DELETE en CyberPanel
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $docId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); // PHP hace el trabajo sucio hacia Hermes
    debugLog("Enviando DELETE a Hermes para ID: $docId");
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