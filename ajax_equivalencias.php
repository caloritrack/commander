<?php
// Nombre del archivo: ajax_equivalencias.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-23
// Versión: 1.0
// Descripción: Controlador Proxy RESTful aislado para gestionar el CRUD de las Equivalencias de Unidades asignadas a los Alimentos.

require_once __DIR__ . '/includes/session.php';

function debugLogEquivalencias($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
    $logFile = $logDir . '/equivalencias_debug.log';
    $date = date('Y-m-d H:i:s');
    $formattedMessage = (is_array($message) || is_object($message)) ? json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $message;
    file_put_contents($logFile, "[$date] " . $formattedMessage . PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

// Validación de Héroe autenticado
if (!isset($_SESSION['user_token'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

$token = $_SESSION['user_token'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id_alimento = trim($_GET['id_alimento'] ?? '');
$id_equivalencia = trim($_GET['id_equivalencia'] ?? '');

if (empty($id_alimento)) {
    echo json_encode(['success' => false, 'message' => 'Se requiere el ID del alimento maestro.']);
    exit();
}

// URL base de Hermes para este dominio
$apiUrl = 'https://api.caloritrack.com/admin/alimentos/' . urlencode($id_alimento) . '/equivalencias';

$ch = curl_init();
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json; charset=utf-8'
];

if ($action === 'list' && $method === 'GET') {
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
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
    if(empty($id_equivalencia)) { 
        echo json_encode(['success' => false, 'message' => 'ID de equivalencia requerido para actualizar.']); exit(); 
    }
    $rawInput = file_get_contents('php://input');
    $headers[] = 'Content-Length: ' . strlen($rawInput);
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . urlencode($id_equivalencia));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
} 
elseif ($action === 'delete' && ($method === 'DELETE' || $method === 'POST')) {
    if(empty($id_equivalencia)) { 
        echo json_encode(['success' => false, 'message' => 'ID de equivalencia requerido para eliminar.']); exit(); 
    }
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . urlencode($id_equivalencia));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
} 
else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida o método HTTP incorrecto.']);
    exit();
}

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Eliminar exitoso (200 OK según la doc de Hermes)
if ($httpCode === 200 && $action === 'delete') {
    echo json_encode(['success' => true, 'message' => 'Equivalencia eliminada correctamente.']);
    exit();
}

if ($curlError) {
    debugLogEquivalencias("Error cURL: " . $curlError);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la API.', 'error' => $curlError]);
    exit();
}

$decodedResponse = json_decode($response, true);

// Manejo de Error 409: La unidad ya está asignada al alimento (Conflicto)
if ($httpCode === 409) {
    echo json_encode([
        'success' => false,
        'http_code' => 409,
        'message' => 'Esta unidad de medida ya se encuentra asignada al alimento.',
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