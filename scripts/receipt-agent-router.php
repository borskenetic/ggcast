<?php

declare(strict_types=1);

/**
 * Local print agent for USB POS printers.
 * Started by: php artisan receipt:agent
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Receipt-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$printer = $app->make(App\Services\ReceiptPrinterService::class);

if ($path === '/' || $path === '/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'printer' => $printer->describeTarget(),
        'configured' => $printer->isConfigured(),
    ]);
    exit;
}

if ($path !== '/print' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'POST /print']);
    exit;
}

$expected = (string) config('printing.agent_token', '');
$given = (string) ($_SERVER['HTTP_X_RECEIPT_TOKEN'] ?? '');
if ($expected !== '' && ! hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Invalid print token']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (! is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$student = is_array($payload['student'] ?? null) ? $payload['student'] : [];
$books = is_array($payload['books'] ?? null) ? $payload['books'] : [];

if ($books === []) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'No books to print']);
    exit;
}

$printed = $printer->printCheckout($student, $books);

header('Content-Type: application/json');
http_response_code($printed ? 200 : 502);
echo json_encode([
    'ok' => $printed,
    'message' => $printed ? 'Printed' : 'Printer did not accept the job',
]);
