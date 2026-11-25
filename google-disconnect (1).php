<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/google-api.php';

try {
    ts_admin_verify_post();
    $config = ts_google_config();
    ts_google_delete_tokens_and_cache($config);
    ts_admin_flash('success', 'Google reporting connection has been disconnected and the stored token removed.');
} catch (Throwable $e) {
    error_log('TOP STUDIO Google disconnect error: ' . $e->getMessage());
    ts_admin_flash('error', 'Google disconnect could not be completed: ' . $e->getMessage());
}
ts_admin_redirect('index.php');
