<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/google-api.php';

try {
    if (isset($_GET['error'])) {
        throw new RuntimeException('Google authorization was not completed: ' . substr((string)$_GET['error'], 0, 100));
    }
    $expectedState = (string)($_SESSION['ts_google_oauth_state'] ?? '');
    $returnedState = (string)($_GET['state'] ?? '');
    $verifier = (string)($_SESSION['ts_google_oauth_verifier'] ?? '');
    $started = (int)($_SESSION['ts_google_oauth_started'] ?? 0);
    unset($_SESSION['ts_google_oauth_state'], $_SESSION['ts_google_oauth_verifier'], $_SESSION['ts_google_oauth_started']);

    if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState) || $started < time() - 900 || $verifier === '') {
        throw new RuntimeException('OAuth state check failed or the authorization session expired. Start Connect Google again.');
    }
    $code = trim((string)($_GET['code'] ?? ''));
    if ($code === '') throw new RuntimeException('Google did not return an authorization code.');

    $config = ts_google_config();
    $body = http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
        'code_verifier' => $verifier,
    ]);
    $response = ts_google_http_request('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], $body);
    $fresh = ts_google_token_response($response);
    $existing = ts_google_read_tokens($config);
    $refreshToken = (string)($fresh['refresh_token'] ?? $existing['refresh_token'] ?? '');
    if ($refreshToken === '') {
        throw new RuntimeException('Google did not return a refresh token. Reconnect and approve the requested read-only access.');
    }

    $tokens = [
        'access_token' => (string)$fresh['access_token'],
        'refresh_token' => $refreshToken,
        'expires_at' => time() + (int)($fresh['expires_in'] ?? 3600),
        'token_type' => (string)($fresh['token_type'] ?? 'Bearer'),
        'scope' => (string)($fresh['scope'] ?? ''),
        'connected_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
    ts_google_write_tokens($config, $tokens);
    $cache = $config['cache_file'];
    if (is_file($cache)) @unlink($cache);
    ts_admin_flash('success', 'Google account connected. Use Refresh Google Reports to load Search Console and GA4 data.');
    ts_admin_redirect('index.php');
} catch (Throwable $e) {
    error_log('TOP STUDIO Google OAuth callback error: ' . $e->getMessage());
    ts_admin_flash('error', 'Google connection failed: ' . $e->getMessage());
    ts_admin_redirect('index.php');
}
