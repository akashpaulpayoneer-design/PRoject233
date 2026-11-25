<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/google-api.php';

try {
    ts_admin_verify_post();
    // Google sync may perform several remote API requests. Release the PHP session lock
    // so other Admin pages/tabs are not blocked while this explicit refresh is running.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $report = ts_google_collect_reports(db(), true);
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $gscOk = !empty($report['gsc']['ok']);
    $ga4Ok = !empty($report['ga4']['ok']);
    if ($gscOk && $ga4Ok) {
        ts_admin_flash('success', 'Google Search Console and GA4 reports refreshed successfully.');
    } elseif ($gscOk || $ga4Ok) {
        $details = [];
        if (!$gscOk && !empty($report['errors']['gsc'])) $details[] = 'Search Console: ' . $report['errors']['gsc'];
        if (!$ga4Ok && !empty($report['errors']['ga4'])) $details[] = 'GA4: ' . $report['errors']['ga4'];
        ts_admin_flash('error', 'Part of the Google report was refreshed. ' . implode(' ', $details));
    } else {
        $details = $report['errors'] ?? [];
        ts_admin_flash('error', 'Google reports could not be refreshed. ' . implode(' ', array_map('strval', $details)));
    }
} catch (Throwable $e) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    error_log('TOP STUDIO Google sync error: ' . $e->getMessage());
    ts_admin_flash('error', 'Google report refresh failed: ' . $e->getMessage());
}
ts_admin_redirect('index.php');
