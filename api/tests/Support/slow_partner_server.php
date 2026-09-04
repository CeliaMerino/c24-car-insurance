<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '';

if (1 !== preg_match('#/partners/([a-z]+)/quote#', $uri, $matches)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo '{"error":"unknown_partner"}';
    return;
}

usleep(1_500_000);

header('Content-Type: application/json');
echo json_encode([
    'partner' => $matches[1],
    'coverage' => 'third_party_plus',
    'annual_premium_cents' => 56900,
    'currency' => 'EUR',
], JSON_THROW_ON_ERROR);
