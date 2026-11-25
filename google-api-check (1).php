<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/google-api.php';

$privateDir = ts_google_private_dir();
$configPath = ts_google_private_config_path();
$checks = [
    ['PHP version 7.4+', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
    ['OpenSSL extension', extension_loaded('openssl'), extension_loaded('openssl') ? 'Available' : 'Enable OpenSSL in cPanel'],
    ['HTTP transport', function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN), function_exists('curl_init') ? 'cURL enabled' : (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) ? 'PHP URL streams enabled' : 'Enable PHP cURL in cPanel')],
    ['Private directory outside public_html', is_dir($privateDir), $privateDir],
    ['Private directory writable', is_dir($privateDir) && is_writable($privateDir), is_dir($privateDir) ? (is_writable($privateDir) ? 'Writable' : 'Not writable; check permissions') : 'Create this folder first'],
    ['OAuth config file', is_file($configPath), $configPath],
];
$configValid = false;
$configMessage = 'Config not loaded yet.';
if (is_file($configPath)) {
    try {
        ts_google_config();
        $configValid = true;
        $configMessage = 'Client ID, Client Secret and Redirect URI format validated (secret hidden).';
    } catch (Throwable $e) {
        $configMessage = $e->getMessage();
    }
}
$checks[] = ['OAuth config values', $configValid, $configMessage];
$allPassed = true;
foreach ($checks as $check) if (!$check[1]) $allPassed = false;
ts_admin_page_start('Google API Setup Check', 'overview', 'Private configuration and PHP capability checks. No credentials are displayed.');
?>
<section class="adm-panel">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">GOOGLE REPORTING API</p><h2>Server readiness</h2><p class="adm-help">This check does not connect to Google or display any secret values.</p></div><?=ts_admin_status_badge($allPassed, 'Ready', 'Needs attention')?></div>
  <div class="adm-list-table"><table><thead><tr><th>CHECK</th><th>STATUS</th><th>DETAIL</th></tr></thead><tbody>
  <?php foreach ($checks as $check): ?><tr><td><b><?=e($check[0])?></b></td><td><?=ts_admin_status_badge((bool)$check[1], 'Passed', 'Action required')?></td><td><?=e($check[2])?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="adm-form-actions"><a class="adm-button adm-button-outline" href="index.php">Back to Dashboard</a><a class="adm-button adm-button-outline" href="settings.php">Website Settings</a></div>
</section>
<?php ts_admin_page_end(); ?>
