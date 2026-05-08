<?php
// Nombre del archivo: ajax_login.php
// Autor: Arturo Enriquez Betancourt con Krillin
// Fecha: 2026-05-08
// Versión: 1.0
// Descripción: Controlador AJAX que recibe credenciales, arma el payload JSON y consume el endpoint de la API oficial mediante cURL. Maneja la creación de la sesión tras un login exitoso.

require_once __DIR__ . '/includes/session.php';

// Indicamos que devolveremos JSON
header('Content-Type: application/json');

// Solo aceptamos peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtenemos el cuerpo de la petición (JSON) enviado por fetch()
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Credenciales incompletas']);
    exit();
}

// Consumo de la API de Caloritrack mediante cURL
$apiUrl = 'https://api.caloritrack.com/admin/auth/login';
$payload = json_encode(['email' => $email, 'password' => $password]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Verificamos si hubo un error a nivel de red o de HTTP
if ($error || $httpCode !== 200) {
    $decoded = json_decode($response, true);
    // Si la API devuelve un mensaje de error específico, lo usamos. Si no, mensaje genérico.
    $msg = $decoded['message'] ?? 'Credenciales incorrectas o error en el servidor Heimdall.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

$responseData = json_decode($response, true);

// ¡Login Exitoso! Guardamos en sesión los datos (Ajustar según la estructura real que devuelva tu API)
$_SESSION['user_token'] = $responseData['token'] ?? 'authenticated';
$_SESSION['user_data'] = $responseData['user'] ?? ['email' => $email];

// Devolvemos éxito al Frontend
echo json_encode(['success' => true]);
?>