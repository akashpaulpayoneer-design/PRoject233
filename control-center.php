<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/performance-lib.php';
require_once __DIR__ . '/suite-lib.php';

$pdo = db();
$tab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'security', 'health'], true)) $tab = 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ts_admin_verify_post();
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!in_array($action, ['run_security_scan', 'run_health_scan'], true)) {
            throw new RuntimeException('Unknown Control Center action.');
        }
        // These scans perform a small number of read-only HTTPS HEAD requests.
        // Release the PHP session lock so other Admin tabs remain responsive.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if ($action === 'run_security_scan') {
            $report = ts_suite_security_scan();
            $tab = 'security';
            $message = 'Security scan completed. No website files, database records or settings were changed.';
        } else {
            $report = ts_suite_health_scan($pdo);
            $tab = 'health';
            $message = 'Website health scan completed. All checks were read-only.';
        }
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        ts_admin_flash('success', $message);
    } catch (Throwable $e) {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        error_log('TOP STUDIO Control Center scan error: ' . $e->getMessage());
        ts_admin_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The scan could not be completed. Check the server error log.');
        $tab = $action === 'run_health_scan' ? 'health' : 'security';
    }
    ts_admin_redirect('control-center.php?tab=' . rawurlencode($tab));
}

$performanceReady = false;
$desktop = null;
$mobile = null;
$cacheActive = false;
$backupRows = [];
try {
    if (ts_perf_schema_exists($pdo)) {
        $performanceReady = true;
        $desktop = ts_perf_latest($pdo, 'desktop');
        $mobile = ts_perf_latest($pdo, 'mobile');
        $cacheActive = ts_perf_cache_active();
        $backupRows = ts_perf_backups($pdo);
    }
} catch (Throwable $e) {
    error_log('TOP STUDIO Control Center performance summary error: ' . $e->getMessage());
}
$securityReport = ts_suite_read_report('security');
$healthReport = ts_suite_read_report('health');
$events = ts_suite_recent_events(8);
$flash = ts_admin_flash('get');

ts_admin_page_start('Performance & Security', 'control-center', 'A compact, read-only health and security center with the existing Performance & Boost tools.');
?>
<style>
.ts-suite-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 15px;padding:6px;border:1px solid rgba(214,179,108,.16);background:#11100d}
.ts-suite-tabs a{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;color:#a99f8d;text-decoration:none;font-size:9px;letter-spacing:.06em;border:1px solid transparent;transition:all .18s ease}
.ts-suite-tabs a:hover{color:#f1e5cc;border-color:rgba(214,179,108,.2)}
.ts-suite-tabs a.is-active{color:#ead5a8;border-color:rgba(214,179,108,.32);background:rgba(214,179,108,.08)}
.ts-suite-panel{margin-bottom:13px;padding:15px;border:1px solid rgba(214,179,108,.14);background:#12110e}
.ts-suite-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-bottom:13px}
.ts-suite-metric{padding:14px;border:1px solid rgba(214,179,108,.14);background:linear-gradient(145deg,rgba(214,179,108,.06),rgba(255,255,255,.012))}
.ts-suite-metric small{display:block;color:#9c927f;font-size:8px;letter-spacing:.12em;text-transform:uppercase}
.ts-suite-metric strong{display:block;margin:5px 0;color:#eee3cf;font:500 27px 'Cormorant Garamond',Georgia,serif}
.ts-suite-muted{color:#9b9284;font-size:9px;line-height:1.7}
.ts-suite-actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.ts-suite-table{width:100%;border-collapse:collapse;font-size:9px}
.ts-suite-table th,.ts-suite-table td{padding:9px 8px;text-align:left;vertical-align:top;border-bottom:1px solid rgba(214,179,108,.10)}
.ts-suite-table th{color:#a99b80;font-size:8px;letter-spacing:.1em;text-transform:uppercase}
.ts-suite-table td{color:#d5ccbd}
.ts-suite-status{display:inline-block;min-width:54px;padding:4px 7px;border:1px solid rgba(214,179,108,.22);color:#ccb98e;font-size:8px;text-align:center;text-transform:uppercase}
.ts-suite-status.pass{border-color:rgba(120,177,132,.35);color:#a9d0b3;background:rgba(70,130,85,.08)}
.ts-suite-status.warn{border-color:rgba(220,175,90,.33);color:#e4c27f;background:rgba(180,130,45,.08)}
.ts-suite-status.critical{border-color:rgba(210,100,90,.38);color:#eda59d;background:rgba(170,70,60,.09)}
.ts-suite-status.info{color:#b9af9e}
.ts-suite-note{padding:10px 12px;border:1px solid rgba(214,179,108,.14);color:#b4a78f;background:rgba(214,179,108,.045);font-size:9px;line-height:1.7}
.ts-suite-empty{padding:16px;border:1px dashed rgba(214,179,108,.20);color:#a99f8d;font-size:10px;line-height:1.7}
@media(max-width:700px){.ts-suite-grid{grid-template-columns:1fr}.ts-suite-table-wrap{overflow-x:auto}.ts-suite-table{min-width:560px}}
</style>

<nav class="ts-suite-tabs" aria-label="TOP STUDIO Control Center">
  <a class="<?= $tab === 'overview' ? 'is-active' : '' ?>" href="control-center.php">Overview</a>
  <a class="<?= $tab === 'security' ? 'is-active' : '' ?>" href="control-center.php?tab=security">Security</a>
  <a class="<?= $tab === 'health' ? 'is-active' : '' ?>" href="control-center.php?tab=health">Website Health</a>
  <a href="performance.php">Performance Tests</a>
  <a href="performance.php#boost">Boost &amp; Backup</a>
</nav>

<?php if ($flash): ?><div class="adm-alert <?=($flash['type'] ?? '') === 'error' ? 'is-error' : 'is-success'?>"><?=e($flash['message'] ?? '')?></div><?php endif; ?>

<?php if ($tab === 'overview'): ?>
  <section class="ts-suite-grid">
    <article class="ts-suite-metric"><small>Desktop PageSpeed</small><strong><?= $desktop ? (int)$desktop['performance_score'] . '/100' : '—' ?></strong><span class="ts-suite-status <?= $desktop && (int)$desktop['performance_score'] >= 90 ? 'pass' : 'info' ?>"><?= $desktop ? 'Latest saved report' : 'Not tested' ?></span><p class="ts-suite-muted"><?= $desktop ? 'Last test: ' . e(date('d M Y, h:i A', strtotime((string)$desktop['created_at']))) : 'Run a manual Desktop test to begin history.' ?></p></article>
    <article class="ts-suite-metric"><small>Mobile PageSpeed</small><strong><?= $mobile ? (int)$mobile['performance_score'] . '/100' : '—' ?></strong><span class="ts-suite-status <?= $mobile && (int)$mobile['performance_score'] >= 90 ? 'pass' : 'info' ?>"><?= $mobile ? 'Latest saved report' : 'Not tested' ?></span><p class="ts-suite-muted"><?= $mobile ? 'Last test: ' . e(date('d M Y, h:i A', strtotime((string)$mobile['created_at']))) : 'Run a manual Mobile test to begin history.' ?></p></article>
    <article class="ts-suite-metric"><small>Security Scan</small><strong><?= isset($securityReport['counts']) ? (int)($securityReport['counts']['critical'] ?? 0) . ' critical' : 'Not scanned' ?></strong><span class="ts-suite-status <?= !empty($securityReport['counts']['critical']) ? 'critical' : (!empty($securityReport) ? 'pass' : 'info') ?>"><?= !empty($securityReport) ? 'Last scan saved' : 'Run when ready' ?></span><p class="ts-suite-muted"><?= !empty($securityReport['scanned_at']) ? e(date('d M Y, h:i A', strtotime((string)$securityReport['scanned_at']))) : 'Read-only checks; no automatic fixes.' ?></p></article>
    <article class="ts-suite-metric"><small>Website Health</small><strong><?= isset($healthReport['counts']) ? (int)($healthReport['counts']['critical'] ?? 0) . ' critical' : 'Not scanned' ?></strong><span class="ts-suite-status <?= !empty($healthReport['counts']['critical']) ? 'critical' : (!empty($healthReport) ? 'pass' : 'info') ?>"><?= !empty($healthReport) ? 'Last scan saved' : 'Run when ready' ?></span><p class="ts-suite-muted"><?= !empty($healthReport['scanned_at']) ? e(date('d M Y, h:i A', strtotime((string)$healthReport['scanned_at']))) : 'Checks public URLs, files and DB connectivity.' ?></p></article>
  </section>

  <section class="ts-suite-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">EXISTING PERFORMANCE ENGINE</p><h2>Performance &amp; Safe Boost</h2><p class="adm-help">The existing PageSpeed Test, step-by-step loader, insights, manual static-cache boost and .htaccess snapshots remain in the Performance Monitor.</p></div><span class="ts-suite-status <?= $cacheActive ? 'pass' : 'info' ?>"><?= $cacheActive ? 'Cache active' : 'Cache not applied' ?></span></div>
    <div class="ts-suite-actions"><a class="adm-button adm-button-gold" href="performance.php">Open Performance Monitor</a><a class="adm-button adm-button-outline" href="performance.php#boost">Open Boost &amp; Backup</a></div>
    <p class="ts-suite-muted">Performance table storage: <?= $performanceReady ? 'ready' : 'not available yet' ?> · Recent .htaccess snapshots: <?= count($backupRows) ?>. This application backup covers the managed .htaccess configuration only; use cPanel Backup for a full website and database backup.</p>
  </section>

  <section class="ts-suite-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">SAFETY MODEL</p><h2>Read first · Approve before change</h2></div></div>
    <div class="ts-suite-note">Security and Health scans are read-only. They do not edit PHP, CSS, JavaScript, .htaccess, database records, Login, Google OAuth or website content. Existing Safe Cache Boost remains a separate, explicitly approved action in Performance Monitor and creates a private .htaccess snapshot first.</div>
  </section>

  <section class="ts-suite-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">RECENT CONTROL CENTER ACTIVITY</p><h2>Scan history</h2></div></div>
    <?php if (!$events): ?><div class="ts-suite-empty">No Security or Health scan activity has been recorded yet.</div><?php else: ?>
      <div class="ts-suite-table-wrap"><table class="ts-suite-table"><thead><tr><th>TIME (UTC)</th><th>ACTIVITY</th><th>CRITICAL</th><th>WARN</th><th>PASS</th></tr></thead><tbody>
      <?php foreach ($events as $event): $d = is_array($event['details'] ?? null) ? $event['details'] : []; ?><tr><td><?=e((string)($event['at'] ?? '—'))?></td><td><?=e(str_replace('_', ' ', (string)($event['event'] ?? 'scan')))?></td><td><?=e($d['critical'] ?? 0)?></td><td><?=e($d['warn'] ?? 0)?></td><td><?=e($d['pass'] ?? 0)?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

<?php elseif ($tab === 'security'): ?>
  <section class="ts-suite-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">READ-ONLY SECURITY AUDIT</p><h2>Security Center</h2><p class="adm-help">Checks response headers, public exposure of common sensitive paths, PHP/session signals and private OAuth storage. No fixes are applied automatically.</p></div><form method="post"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="run_security_scan"><button class="adm-button adm-button-gold" type="submit">Run Security Scan</button></form></div>
    <?php if (!empty($securityReport)): ?>
      <div class="ts-suite-grid">
        <?php foreach (['critical' => 'Critical', 'warn' => 'Warnings', 'pass' => 'Passed', 'info' => 'Info'] as $key => $label): ?><article class="ts-suite-metric"><small><?=e($label)?></small><strong><?= (int)($securityReport['counts'][$key] ?? 0) ?></strong></article><?php endforeach; ?>
      </div>
      <p class="ts-suite-muted">Last scan: <?=e((string)($securityReport['scanned_at'] ?? '—'))?> (UTC)</p>
      <div class="ts-suite-table-wrap"><table class="ts-suite-table"><thead><tr><th>AREA</th><th>CHECK</th><th>STATUS</th><th>DETAIL</th></tr></thead><tbody>
        <?php foreach (($securityReport['items'] ?? []) as $item): ?><tr><td><?=e($item['area'] ?? '')?></td><td><b><?=e($item['label'] ?? '')?></b></td><td><span class="ts-suite-status <?=e($item['status'] ?? 'info')?>"><?=e($item['status'] ?? 'info')?></span></td><td><?=e($item['detail'] ?? '')?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><div class="ts-suite-empty">No Security Scan has been run yet. Press “Run Security Scan” to collect the first read-only report.</div><?php endif; ?>
    <p class="ts-suite-note" style="margin-top:12px">A missing security header is a recommendation, not proof of compromise. Do not enable a restrictive CSP, HSTS or other server header from this report without checking all scripts, fonts, images, forms and Google integrations first.</p>
  </section>

<?php elseif ($tab === 'health'): ?>
  <section class="ts-suite-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">READ-ONLY WEBSITE CHECKS</p><h2>Website Health</h2><p class="adm-help">Checks important public URLs, files/directories, PHP extensions, available disk space and a read-only database connection.</p></div><form method="post"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="run_health_scan"><button class="adm-button adm-button-gold" type="submit">Run Health Scan</button></form></div>
    <?php if (!empty($healthReport)): ?>
      <div class="ts-suite-grid">
        <?php foreach (['critical' => 'Critical', 'warn' => 'Warnings', 'pass' => 'Passed', 'info' => 'Info'] as $key => $label): ?><article class="ts-suite-metric"><small><?=e($label)?></small><strong><?= (int)($healthReport['counts'][$key] ?? 0) ?></strong></article><?php endforeach; ?>
      </div>
      <p class="ts-suite-muted">Last scan: <?=e((string)($healthReport['scanned_at'] ?? '—'))?> (UTC)</p>
      <div class="ts-suite-table-wrap"><table class="ts-suite-table"><thead><tr><th>AREA</th><th>CHECK</th><th>STATUS</th><th>DETAIL</th></tr></thead><tbody>
        <?php foreach (($healthReport['items'] ?? []) as $item): ?><tr><td><?=e($item['area'] ?? '')?></td><td><b><?=e($item['label'] ?? '')?></b></td><td><span class="ts-suite-status <?=e($item['status'] ?? 'info')?>"><?=e($item['status'] ?? 'info')?></span></td><td><?=e($item['detail'] ?? '')?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><div class="ts-suite-empty">No Website Health Scan has been run yet. Press “Run Health Scan” to collect the first report.</div><?php endif; ?>
    <p class="ts-suite-note" style="margin-top:12px">The scan uses HTTPS HEAD requests and a read-only <code>SELECT 1</code>. It does not submit forms, change database records, or modify website files.</p>
  </section>
<?php endif; ?>

<?php ts_admin_page_end(); ?>
