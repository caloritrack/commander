<?php
// Nombre del archivo: ajax_analytics.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.3
// Descripción: Controlador AJAX proxy seguro. Se agregó la ruta 'schema' para consultar el auto-descubrimiento de la API de Hermes.

require_once __DIR__ . '/includes/session.php';

// Función interna para guardar logs
function debugLog($message) {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/analytics_debug.log';
    
    // Verificamos si la carpeta existe, si no, la creamos (con permisos seguros)
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $date = date('Y-m-d H:i:s');
    // Escribimos al final del archivo
    file_put_contents($logFile, "[$date] " . $message . PHP_EOL, FILE_APPEND);
}

// Indicamos que devolveremos JSON
header('Content-Type: application/json');

// Solo aceptamos peticiones GET para lectura de datos
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    debugLog("Error: Método HTTP no permitido (" . $_SERVER['REQUEST_METHOD'] . ")");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificamos que el héroe esté autenticado
if (!isset($_SESSION['user_token'])) {
    debugLog("Error: Intento de acceso sin token de sesión activo.");
    echo json_encode(['success' => false, 'message' => 'No autorizado. Inicie sesión nuevamente.']);
    exit();
}

$token = $_SESSION['user_token'];
$action = $_GET['action'] ?? '';
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');

$apiUrl = 'https://api.caloritrack.com';
$endpoint = '';

// Ruteo interno de la petición según lo que pida el Dashboard
if ($action === 'kpis') {
    $endpoint = "/admin/analytics/kpis?startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'trends') {
    $metric = $_GET['metric'] ?? 'unique_users';
    $interval = $_GET['interval'] ?? 'day';
    $endpoint = "/admin/analytics/trends?startDate={$startDate}&endDate={$endDate}&interval={$interval}&metric={$metric}";
} elseif ($action === 'distribution') {
    $groupBy = $_GET['groupBy'] ?? 'screen_name';
    $endpoint = "/admin/analytics/events/distribution?startDate={$startDate}&endDate={$endDate}&groupBy={$groupBy}";
} elseif ($action === 'schema') {
    // Nuevo endpoint de auto-descubrimiento
    $endpoint = "/admin/analytics/schema";
} else {
    debugLog("Error: Acción inválida solicitada ('$action')");
    echo json_encode(['success' => false, 'message' => 'Acción inválida solicitada al proxy']);
    exit();
}

$fullUrl = $apiUrl . $endpoint;
debugLog("--- INICIANDO PETICIÓN A HERMES ---");
debugLog("URL Destino: " . $fullUrl);

// Enmascaramos el token un poco por seguridad en los logs
$maskedToken = substr($token, 0, 10) . '...' . substr($token, -10);
debugLog("Token enviado: Bearer " . $maskedToken);

// Consumo de la API de Hermes mediante cURL
$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

debugLog("Código HTTP recibido: " . $httpCode);

// Manejo de errores de red o HTTP
if ($error || $httpCode !== 200) {
    debugLog("FALLO EN CURL o HTTP NO ES 200. Error interno cURL: " . ($error ? $error : "Ninguno") . ". Respuesta de Hermes: " . $response);
    // Si falla, enviamos el error al frontend para depuración
    echo json_encode([
        'success' => false, 
        'message' => 'Error al conectar con los servidores de Hermes.',
        'debug' => ['error' => $error, 'http_code' => $httpCode]
    ]);
    exit();
}

// Guardamos un extracto de la respuesta para no saturar el log si es inmensa
debugLog("RESPUESTA EXITOSA DE HERMES: " . substr($response, 0, 800) . "..."); 
debugLog("--- FIN DE LA PETICIÓN ---");

// Decodificamos la respuesta de Hermes y la empaquetamos
$responseData = json_decode($response, true);
echo json_encode(['success' => true, 'data' => $responseData]);
?>