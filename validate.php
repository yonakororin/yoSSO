<?php
header('Content-Type: application/json');

$codes_file = __DIR__ . '/data/codes.json';
if (!file_exists($codes_file)) {
    echo json_encode(['error' => 'Server error']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $_REQUEST['code'] ?? $input['code'] ?? '';

if (!$code) {
    echo json_encode(['valid' => false, 'error' => 'No code provided']);
    exit;
}

$codes = json_decode(file_get_contents($codes_file), true);

if (isset($codes[$code])) {
    $data = $codes[$code];
    if ($data['expires_at'] > time()) {
        echo json_encode(['valid' => true, 'username' => $data['username']]);
        
        // Invalidate code after use (Single use)
        unset($codes[$code]);
        file_put_contents($codes_file, json_encode($codes));
        exit;
    }
}

echo json_encode(['valid' => false, 'error' => 'Invalid or expired code']);
?>
