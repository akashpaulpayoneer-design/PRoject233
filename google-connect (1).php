<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/google-api.php';

try {
    $config = ts_google_config();
    $state = bin2hex(random_bytes(32));
    $verifier = ts_google_base64url(random_bytes(64));
    $challenge = ts_google_base64url(hash('sha256', $verifier, true));
    $_SESSION['ts_google_oauth_state'] = $state;
    $_SESSION['ts_google_oauth_verifier'] = $verifier;
    $_SESSION['ts_google_oauth_started'] = time();

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'include_granted_scopes' => 'true',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    exit;
} catch (Throwable $e) {
    error_log('TOP STUDIO Google connect error: ' . $e->getMessage());
    ts_admin_flash('error', 'Google connection is not ready: ' . $e->getMessage());
    ts_admin_redirect('index.php');
}
