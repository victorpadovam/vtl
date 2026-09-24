<?php
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Email service is not configured yet. Create config.php from config.php.example.');
}

$config = require $configFile;
$apiKey = trim((string)($config['resend_api_key'] ?? ''));
$from   = trim((string)($config['from'] ?? ''));
$to     = trim((string)($config['to'] ?? 'ScoopTroopersATX@gmail.com'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if ($apiKey === '' || str_contains($apiKey, 'xxxxxxxx') || $from === '') {
    http_response_code(500);
    exit('Email service is not configured correctly.');
}

// Honeypot: bots are accepted without sending an email.
if (!empty($_POST['website'] ?? '')) {
    header('Location: index.html?sent=1#quote');
    exit;
}

$clean = static function ($value): string {
    return trim(strip_tags((string)$value));
};

$first   = $clean($_POST['First_Name'] ?? $_POST['First Name'] ?? '');
$last    = $clean($_POST['Last_Name'] ?? $_POST['Last Name'] ?? '');
$email   = filter_var(trim((string)($_POST['Email'] ?? '')), FILTER_SANITIZE_EMAIL);
$phone   = $clean($_POST['Phone'] ?? '');
$country = $clean($_POST['Country'] ?? '');
$address1 = $clean($_POST['Address_Line_1'] ?? $_POST['Address Line 1'] ?? '');
$address2 = $clean($_POST['Address_Line_2'] ?? $_POST['Address Line 2'] ?? '');
$state   = $clean($_POST['State'] ?? '');
$city    = $clean($_POST['City'] ?? '');
$zip     = $clean($_POST['ZIP_Code'] ?? $_POST['ZIP Code'] ?? '');
$service = $clean($_POST['Service'] ?? '');
$message = $clean($_POST['Message'] ?? '');

if (!$first || !$last || !$email || !$phone || !$country || !$address1 || !$city || !$state || !$zip || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit('Please complete all required fields and provide a valid email address.');
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html = '
<!doctype html>
<html><body style="font-family:Arial,sans-serif;color:#1d281b;line-height:1.6">
<h2>New ScoopTroopersATX Quote Request</h2>
<table cellpadding="8" cellspacing="0" style="border-collapse:collapse">
<tr><td><strong>Name</strong></td><td>' . $escape($first . ' ' . $last) . '</td></tr>
<tr><td><strong>Email</strong></td><td>' . $escape($email) . '</td></tr>
<tr><td><strong>Phone</strong></td><td>' . $escape($phone) . '</td></tr>
<tr><td><strong>Country</strong></td><td>' . $escape($country) . '</td></tr>
<tr><td><strong>Address Line 1</strong></td><td>' . $escape($address1) . '</td></tr>
<tr><td><strong>Address Line 2</strong></td><td>' . $escape($address2 ?: '—') . '</td></tr>
<tr><td><strong>City</strong></td><td>' . $escape($city) . '</td></tr>
<tr><td><strong>State</strong></td><td>' . $escape($state) . '</td></tr>
<tr><td><strong>ZIP</strong></td><td>' . $escape($zip) . '</td></tr>
<tr><td><strong>Service</strong></td><td>' . $escape($service) . '</td></tr>
</table>
<h3>Message</h3>
<p>' . nl2br($escape($message ?: 'No additional message.')) . '</p>
</body></html>';

$payload = json_encode([
    'from' => $from,
    'to' => [$to],
    'reply_to' => [$email],
    'subject' => 'New ScoopTroopersATX Quote Request',
    'html' => $html,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError || $status < 200 || $status >= 300) {
    error_log('Resend error: HTTP ' . $status . ' - ' . ($curlError ?: $response));
    http_response_code(502);
    exit('We could not send your request right now. Please call 512-721-8311 or email ScoopTroopersATX@gmail.com.');
}

header('Location: index.html?sent=1#quote');
exit;
