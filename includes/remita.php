<?php
/**
 * Remita payment verification adapter.
 *
 * The live verification endpoint and credentials are deployment secrets and
 * must be supplied by the Polytechnic/Remita integration account.
 * The application fails closed when the integration is not configured.
 */
function verify_remita_rrr(string $rrr, float $expectedAmount): array {
    $url = getenv('REMITA_VERIFY_URL') ?: '';
    $apiKey = getenv('REMITA_API_KEY') ?: '';

    if ($url === '') {
        return ['verified' => false, 'message' => 'Remita verification is not configured on this server.'];
    }

    if (!preg_match('/^[0-9-]{8,30}$/', $rrr)) {
        return ['verified' => false, 'message' => 'Enter a valid Remita Retrieval Reference (RRR).'];
    }

    $payload = json_encode([
        'rrr' => $rrr,
        'amount' => $expectedAmount
    ]);

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
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        return ['verified' => false, 'message' => 'Unable to contact the Remita verification service.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data) || $httpCode < 200 || $httpCode >= 300) {
        return ['verified' => false, 'message' => 'Remita could not verify this RRR.'];
    }

    // Keep the adapter tolerant of provider response naming while still
    // requiring both a successful status and the expected amount.
    $status = strtolower((string)($data['status'] ?? $data['paymentStatus'] ?? $data['responseCode'] ?? ''));
    $amount = isset($data['amount']) ? (float)$data['amount']
        : (isset($data['paymentAmount']) ? (float)$data['paymentAmount'] : null);

    $success = in_array($status, ['success', 'successful', 'paid', '00', '200'], true);
    $amountMatches = $amount !== null && abs($amount - $expectedAmount) < 0.01;

    if (!$success || !$amountMatches) {
        return ['verified' => false, 'message' => 'The RRR is not a verified payment for the required hostel fee.'];
    }

    return ['verified' => true, 'message' => 'Payment verified successfully.'];
}
