<?php
// =====================================================
// Public API endpoint for lead capture, including WhatsApp Cloud API.
//
// Secure this with a secret key passed as ?key=YOUR_SECRET
// or as an "X-API-KEY" header. Set the key below.
// =====================================================
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

define('WEBHOOK_SECRET', getenv('DMP_WEBHOOK_SECRET') ?: 'your-long-random-secret');
define('WHATSAPP_VERIFY_TOKEN', getenv('DMP_WHATSAPP_VERIFY_TOKEN') ?: 'your-whatsapp-verify-token');

// Meta calls this endpoint with GET while verifying the webhook URL.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && hash_equals(WHATSAPP_VERIFY_TOKEN, $token)) {
        echo $challenge;
    } else {
        http_response_code(403);
        echo 'Webhook verification failed';
    }
    exit;
}

$providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!hash_equals(WEBHOOK_SECRET, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

// Accept JSON or form-encoded payloads. Meta WhatsApp callbacks are nested.
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!$input) { $input = $_POST; }

if (($input['object'] ?? '') === 'whatsapp_business_account') {
    $messages = [];
    foreach (($input['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            foreach (($change['value']['messages'] ?? []) as $message) {
                $messages[] = [
                    'name' => $change['value']['contacts'][0]['profile']['name'] ?? 'WhatsApp Contact',
                    'phone' => $message['from'] ?? '',
                    'source' => 'whatsapp',
                ];
            }
        }
    }

    $created = 0;
    $duplicates = 0;
    foreach ($messages as $message) {
        $phone = preg_replace('/\D+/', '', $message['phone']);
        if ($phone === '') {
            continue;
        }

        $dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ? LIMIT 1");
        $dup->execute([$phone]);
        if ($dup->fetch()) {
            $duplicates++;
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO leads (name, phone, source, status) VALUES (?, ?, 'whatsapp', 'new')");
        $stmt->execute([$message['name'], $phone]);
        $created++;
    }

    echo json_encode(['success' => true, 'created' => $created, 'duplicates' => $duplicates]);
    exit;
}

// WATI sends a flat event payload such as waId, name, and eventType.
$watiEvent = strtolower((string)($input['eventType'] ?? $input['event_type'] ?? ''));
$watiPhone = $input['waId']
    ?? $input['wa_id']
    ?? $input['whatsappNumber']
    ?? $input['phoneNumber']
    ?? $input['contact']['waId']
    ?? $input['contact']['phone']
    ?? '';
$watiName = $input['name']
    ?? $input['contact']['name']
    ?? $input['contact']['fullName']
    ?? 'WATI Contact';

if ($watiPhone !== '' && ($watiEvent === '' || str_contains($watiEvent, 'message') || str_contains($watiEvent, 'contact'))) {
    $phone = preg_replace('/\D+/', '', (string)$watiPhone);
    if ($phone === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'WATI payload has no valid phone number']);
        exit;
    }

    $dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ? LIMIT 1");
    $dup->execute([$phone]);
    if ($existing = $dup->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Duplicate lead, not re-added', 'lead_id' => $existing['id']]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO leads (name, phone, source, status) VALUES (?, ?, 'whatsapp', 'new')");
    $stmt->execute([trim((string)$watiName) ?: 'WATI Contact', $phone]);
    echo json_encode(['success' => true, 'message' => 'WATI lead captured', 'lead_id' => db_last_insert_id('leads')]);
    exit;
}

$name   = trim($input['name'] ?? '');
$phone  = preg_replace('/\D+/', '', $input['phone'] ?? '');
$email  = trim($input['email'] ?? '');
$city   = trim($input['city'] ?? '');
$source = strtolower(trim($input['source'] ?? 'website'));

if (!in_array($source, ['facebook','google','instagram','whatsapp','referral','walk-in','website','other'])) {
    $source = 'other';
}

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'name and phone are required']);
    exit;
}

// Duplicate check
$dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ? LIMIT 1");
$dup->execute([$phone]);
if ($existing = $dup->fetch()) {
    echo json_encode(['success' => true, 'message' => 'Duplicate lead, not re-added', 'lead_id' => $existing['id']]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, city, source, status) VALUES (?,?,?,?,?, 'new')");
$stmt->execute([$name, $phone, $email, $city, $source]);
$leadId = db_last_insert_id('leads');

echo json_encode(['success' => true, 'message' => 'Lead captured', 'lead_id' => $leadId]);
