<?php
// Nombre del archivo: ajax_analytics.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha y Hora: 2026-11-16 11:58:00 CST
// Versión: 1.18 (Sanitización urlencode/intval de parámetros GET, protección de carpeta logs y SSL estricto)
// Descripción: Controlador AJAX proxy seguro para Analíticas y Dashboards. Codifica todos los parámetros antes de construir la URL hacia Hermes y protege los archivos de depuración.

require_once __DIR__ . '/includes/session.php';

// Función interna para guardar logs de forma segura
function debugLog($message) {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/analytics_debug.log';
    
    // Verificamos si la carpeta existe, si no, la creamos con bloqueo web integrado
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
        file_put_contents($logDir . '/.htaccess', "RewriteEngine On\nRewriteRule ^ - [F,L]\nRequire all denied");
        file_put_contents($logDir . '/index.php', "<?php http_response_code(403); exit; ?>");
    }
    
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] " . $message . PHP_EOL, FILE_APPEND);
}

// Indicamos que devolveremos JSON
header('Content-Type: application/json; charset=utf-8');

// Solo aceptamos peticiones GET para lectura de datos
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    debugLog("Error: Método HTTP no permitido (" . $_SERVER['REQUEST_METHOD'] . ")");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificamos que el héroe esté autenticado
if (!isset($_SESSION['user_token'])) {
    debugLog("Error: Intento de acceso sin token de sesión activo.");
    echo json_encode(['success' => false, 'http_code' => 401, 'message' => 'No autorizado. Inicie sesión nuevamente.']);
    exit();
}

$token = $_SESSION['user_token'];
$action = trim($_GET['action'] ?? '');

// [KRILLIN SECURITY] Sanitización y codificación de todos los parámetros GET para evitar HTTP Parameter Pollution
$startDate = urlencode(trim($_GET['startDate'] ?? date('Y-m-d', strtotime('-7 days'))));
$endDate = urlencode(trim($_GET['endDate'] ?? date('Y-m-d')));
$page = max(1, intval($_GET['page'] ?? 1));

$apiUrl = 'https://api.caloritrack.com';
$endpoint = '';

// Ruteo interno de la petición según lo que pida el Dashboard
if ($action === 'kpis') {
    $endpoint = "/admin/analytics/kpis?startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'trends') {
    $metric = urlencode(trim($_GET['metric'] ?? 'unique_users'));
    $interval = urlencode(trim($_GET['interval'] ?? 'day'));
    $endpoint = "/admin/analytics/trends?startDate={$startDate}&endDate={$endDate}&interval={$interval}&metric={$metric}";
} elseif ($action === 'trends_events') {
    $eventName = urlencode(trim($_GET['eventName'] ?? ''));
    $interval = urlencode(trim($_GET['interval'] ?? 'day'));
    $endpoint = "/admin/analytics/trends?startDate={$startDate}&endDate={$endDate}&interval={$interval}&metric=events&eventName={$eventName}";
} elseif ($action === 'distribution') {
    $groupBy = urlencode(trim($_GET['groupBy'] ?? 'screen_name'));
    $endpoint = "/admin/analytics/events/distribution?startDate={$startDate}&endDate={$endDate}&groupBy={$groupBy}";
} elseif ($action === 'metadata_distribution') {
    $eventName = urlencode(trim($_GET['eventName'] ?? ''));
    $metadataKey = urlencode(trim($_GET['metadataKey'] ?? ''));
    $endpoint = "/admin/analytics/events/metadata?eventName={$eventName}&metadataKey={$metadataKey}&startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'funnel_onboarding') {
    $endpoint = "/admin/analytics/funnel/onboarding?startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'schema') {
    $endpoint = "/admin/analytics/schema";
} elseif ($action === 'subscriptions') {
    $endpoint = "/admin/dashboard/subscriptions-summary";
} elseif ($action === 'customers') {
    $endpoint = "/admin/dashboard/customers-report?page={$page}";
} elseif ($action === 'meal_plans') {
    $endpoint = "/admin/dashboard/meal-plans-stats";
} elseif ($action === 'demographics') {
    $endpoint = "/admin/dashboard/demographics-stats";
} elseif ($action === 'dayparting') {
    $endpoint = "/admin/dashboard/dayparting-stats?startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'top_events') {
    $endpoint = "/admin/analytics/events/top?startDate={$startDate}&endDate={$endDate}";
} elseif ($action === 'sessions_detail') {
    $endpoint = "/admin/analytics/sessions/detail?startDate={$startDate}&endDate={$endDate}&page={$page}";
} else {
    debugLog("Error: Acción inválida solicitada ('$action')");
    echo json_encode(['success' => false, 'message' => 'Acción inválida solicitada al proxy']);
    exit();
}

$fullUrl = $apiUrl . $endpoint;

// Consumo de la API de Hermes mediante cURL con verificación SSL activa
$ch = curl_init($fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'x-calori-token: ' . CALORI_WAF_TOKEN
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Manejo de errores de red o HTTP
if ($error || $httpCode !== 200) {
    debugLog("FALLO EN CURL ($fullUrl) -> HTTP: $httpCode | Error cURL: " . ($error ?: "Ninguno"));
    
    // KRILLIN FIX: Si Hermes nos batea por token caducado (401), destruimos la sesión de PHP de una vez
    if ($httpCode === 401) {
        session_destroy();
    }

    echo json_encode([
        'success' => false, 
        'http_code' => $httpCode,
        'message' => 'Error al conectar con los servidores de Hermes.',
        'debug' => ['error' => $error, 'http_code' => $httpCode]
    ]);
    exit();
}

$responseData = json_decode($response, true);
echo json_encode(['success' => true, 'data' => $responseData]);
?>