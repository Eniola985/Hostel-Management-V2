<?php
/**
 * Remita payment verification adapter.
 *
 * Live verification requires the Polytechnic/Remita integration credentials.
 * A development-only verifier is available for local testing when explicitly
 * enabled with REMITA_DEV_MODE=true in .env.local. Production remains fail-closed.
 */
function remita_config(string $key): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string)$value;
    }

    $file = __DIR__ . '/../.env.local';
    if (is_readable($file)) {
        $config = parse_ini_file($file);
        if (isset($config[$key])) {
            // parse_ini_file() may normalize true/yes/on to "1".
            if ($config[$key] === true || $config[$key] === 1 || $config[$key] === '1') {
                return 'true';
            }
            if ($config[$key] !== '') {
                return (string)$config[$key];
            }
        }
    }

    return '';
}

function verify_remita_rrr(string $rrr, float $expectedAmount): array {
    if (!preg_match('/^[0-9-]{8,30}$/', $rrr)) {
        return [
            'verified' => false,
            'message' => 'Enter a valid Remita Retrieval Reference (RRR).'
        ];
    }

    // Explicit local development mode only. Never enable this in production.
    $devMode = in_array(
        strtolower(trim(remita_config('REMITA_DEV_MODE'))),
        ['true', '1', 'yes', 'on'],
        true
    );
    if ($devMode) {
        return [
            'verified' => true,
            'message' => 'Development payment verification passed. Live Remita verification is not enabled.',
            'source' => 'Development Test'
        ];
    }

    $url = remita_config('REMITA_VERIFY_URL');
    $apiKey = remita_config('REMITA_API_KEY');

    if ($url === '') {
        return [
            'verified' => false,
            'message' => 'Remita verification is not configured on this server.'
        ];
    }

    $payload = json_encode(['rrr' => $rrr, 'amount' => $expectedAmount]);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        return ['verified' => false, 'message' => 'Unable to contact the Remita verification service.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || $httpCode < 200 || $httpCode >= 300) {
        return ['verified' => false, 'message' => 'Remita could not verify this RRR.'];
    }

    $status = strtolower((string)($data['status'] ?? $data['paymentStatus'] ?? $data['responseCode'] ?? ''));
    $amount = isset($data['amount']) ? (float)$data['amount']
        : (isset($data['paymentAmount']) ? (float)$data['paymentAmount'] : null);

    $success = in_array($status, ['success', 'successful', 'paid', '00', '200'], true);
    $amountMatches = $amount !== null && abs($amount - $expectedAmount) < 0.01;

    if (!$success || !$amountMatches) {
        return ['verified' => false, 'message' => 'The RRR is not a verified payment for the required hostel fee.'];
    }

    return [
        'verified' => true,
        'message' => 'Payment verified successfully.',
        'source' => 'Remita'
    ];
}
