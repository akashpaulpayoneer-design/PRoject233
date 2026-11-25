<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/performance-lib.php';

$pdo = db();
$schemaReady = false;
$setupError = '';
try {
    // Avoid running CREATE TABLE IF NOT EXISTS on every Monitor page view.
    if (!ts_perf_schema_exists($pdo)) ts_perf_ensure_schema($pdo);
    $schemaReady = ts_perf_schema_exists($pdo);
    if (!$schemaReady) throw new RuntimeException('Performance Monitor database tables are not available.');
} catch (Throwable $e) {
    error_log('TOP STUDIO performance schema error: ' . $e->getMessage());
    $setupError = 'The Performance Monitor tables could not be prepared. Check that the database user can create tables.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ts_admin_verify_post();
    if (!$schemaReady) {
        ts_admin_flash('error', $setupError);
        ts_admin_redirect('performance.php');
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'run_mobile' || $action === 'run_desktop') {
            $strategy = $action === 'run_mobile' ? 'mobile' : 'desktop';
            $latest = ts_perf_latest($pdo, $strategy);
            if ($latest && (time() - strtotime((string)$latest['created_at'])) < 600) {
                throw new RuntimeException('A ' . ucfirst($strategy) . ' test was already saved in the last 10 minutes. Please wait before running it again to protect the Google API quota.');
            }
            // Rate-limit failed requests too; the old cooldown only counted successful scans.
            $cooldownKey = 'ts_perf_next_attempt_' . $strategy;
            $nextAllowed = (int)($_SESSION[$cooldownKey] ?? 0);
            if ($nextAllowed > time()) {
                $remaining = $nextAllowed - time();
                $minutes = max(1, (int)ceil($remaining / 60));
                throw new RuntimeException('A previous ' . ucfirst($strategy) . ' PageSpeed request is cooling down. Please wait about ' . $minutes . ' minute(s) before trying again.');
            }
            $_SESSION[$cooldownKey] = time() + 600;
            // Crucial: release PHP's session file lock while waiting for Google's remote API.
            // This prevents a slow/429 PageSpeed request from freezing other Admin tabs.
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            try {
                $result = ts_perf_run_scan($pdo, $strategy);
            } catch (Throwable $scanError) {
                if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
                if (stripos($scanError->getMessage(), '429') !== false) {
                    $_SESSION[$cooldownKey] = time() + 1800;
                }
                throw $scanError;
            }
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            ts_admin_flash('success', ucfirst($strategy) . ' PageSpeed test completed and saved to history.');
        } elseif ($action === 'create_backup') {
            $id = ts_perf_create_htaccess_backup($pdo, 'manual_backup');
            ts_admin_flash('success', 'Private .htaccess backup created successfully (Backup #' . $id . '). No live website file was changed.');
        } elseif ($action === 'apply_safe_cache') {
            $result = ts_perf_apply_safe_cache_boost($pdo);
            if (!empty($result['already_active'])) {
                ts_admin_flash('success', 'The TOP STUDIO managed cache rules are already active. No duplicate rules were added.');
            } else {
                ts_admin_flash('success', 'Safe static-cache rules were applied after a private backup. The homepage health check passed. Backup #' . (int)$result['backup_id'] . ' is available below for rollback.');
            }
        } elseif ($action === 'restore_backup') {
            ts_perf_restore_backup($pdo, (int)($_POST['backup_id'] ?? 0));
            ts_admin_flash('success', 'The selected .htaccess backup was restored and the homepage health check passed.');
        } else {
            throw new RuntimeException('Unknown Performance Monitor action.');
        }
    } catch (Throwable $e) {
        error_log('TOP STUDIO performance action error: ' . $e->getMessage());
        ts_admin_flash('error', $e->getMessage());
    }
    ts_admin_redirect('performance.php');
}

$mobile = null;
$desktop = null;
$history = [];
$backups = [];
$cacheActive = false;
if ($schemaReady) {
    try {
        $mobile = ts_perf_latest($pdo, 'mobile');
        $desktop = ts_perf_latest($pdo, 'desktop');
        $history = ts_perf_recent($pdo, 12);
        $backups = ts_perf_backups($pdo);
        $cacheActive = ts_perf_cache_active();
    } catch (Throwable $e) {
        error_log('TOP STUDIO performance dashboard read error: ' . $e->getMessage());
        $setupError = 'The saved Performance Monitor data could not be loaded.';
    }
}

$flash = ts_admin_flash('get');
ts_admin_page_start('Performance & Boost', 'overview', 'A compact health monitor and controlled, reversible website optimization tools.');
?>
<style>
.ts-perf-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px;padding:17px 19px;border:1px solid rgba(220,190,134,.17);background:linear-gradient(110deg,rgba(201,169,110,.10),rgba(255,255,255,.015));}
.ts-perf-intro p{margin:4px 0 0;color:#a49d90;font-size:10px;line-height:1.7}.ts-perf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}.ts-perf-card{min-width:0;padding:16px;border:1px solid rgba(220,190,134,.15);background:#12110e}.ts-perf-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#a49d90;font-size:9px;letter-spacing:.13em;text-transform:uppercase}.ts-perf-score{display:inline-flex;align-items:center;justify-content:center;min-width:54px;padding:5px 10px;border:1px solid rgba(220,190,134,.25);font:600 25px 'DM Sans',sans-serif;letter-spacing:-.04em}.ts-perf-score.is-good{color:#a9d0b3;border-color:rgba(130,190,145,.35);background:rgba(70,130,85,.10)}.ts-perf-score.is-mid{color:#e6c17d;border-color:rgba(220,175,90,.3);background:rgba(180,130,45,.09)}.ts-perf-score.is-low{color:#e6a19a;border-color:rgba(200,100,90,.3);background:rgba(170,70,60,.09)}.ts-perf-score.is-empty{color:#766e61}.ts-perf-submetrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:13px}.ts-perf-submetrics span{display:block;padding:8px 7px;background:rgba(255,255,255,.025);color:#a49d90;font-size:8px}.ts-perf-submetrics b{display:block;margin-top:3px;color:#eee5d5;font-size:11px;font-weight:600}.ts-perf-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px}.ts-perf-note{margin:12px 0 0;color:#978d7b;font-size:9px;line-height:1.7}.ts-perf-section{margin-bottom:16px}.ts-perf-table{width:100%;border-collapse:collapse;font-size:9px}.ts-perf-table th,.ts-perf-table td{padding:10px 9px;border-bottom:1px solid rgba(220,190,134,.10);text-align:left;vertical-align:middle}.ts-perf-table th{color:#9c907b;font-size:8px;letter-spacing:.10em;text-transform:uppercase}.ts-perf-table td{color:#d8d0c3}.ts-perf-muted{color:#918879!important}.ts-perf-pill{display:inline-block;padding:4px 7px;border:1px solid rgba(220,190,134,.18);color:#c8b78f;font-size:8px}.ts-perf-pill.good{color:#a9d0b3;border-color:rgba(130,190,145,.3)}.ts-perf-pill.warn{color:#e6c17d;border-color:rgba(220,175,90,.3)}.ts-perf-warning{padding:11px 13px;border:1px solid rgba(220,175,90,.25);background:rgba(180,130,45,.07);color:#d9c08f;font-size:9px;line-height:1.7}.ts-perf-good{padding:11px 13px;border:1px solid rgba(130,190,145,.2);background:rgba(70,130,85,.07);color:#bad5bd;font-size:9px;line-height:1.7}.ts-perf-insight-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.ts-perf-insight{padding:12px;border:1px solid rgba(220,190,134,.12);background:rgba(255,255,255,.015)}.ts-perf-insight b{display:block;margin-bottom:5px;color:#e4d8c2;font-size:10px}.ts-perf-insight small{color:#a49d90;font-size:8px;line-height:1.6}.ts-perf-inline-form{display:inline-flex;margin:0}.ts-perf-form-note{color:#978d7b;font-size:8px;line-height:1.6}.ts-perf-empty{padding:16px;border:1px dashed rgba(220,190,134,.2);color:#a49d90;font-size:10px;line-height:1.7}
@media(max-width:800px){.ts-perf-intro{flex-direction:column}.ts-perf-submetrics{grid-template-columns:repeat(2,minmax(0,1fr))}.ts-perf-insight-list{grid-template-columns:1fr}.ts-perf-table-wrap{overflow-x:auto}.ts-perf-table{min-width:620px}}

/* Compact Premium Performance Test Step Loader — active only after a valid click. */
.ts-perf-run-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:148px;cursor:pointer;transition:opacity .2s ease,transform .2s ease,box-shadow .2s ease}
.ts-perf-run-btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 7px 22px rgba(201,169,110,.14)}
.ts-perf-run-btn:disabled{opacity:.62;cursor:not-allowed;transform:none}
.ts-perf-btn-icon{font-size:13px;line-height:1}
.ts-perf-loader{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:16px;background:rgba(5,5,5,.78);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .24s ease,visibility .24s ease}
.ts-perf-loader.is-active{opacity:1;visibility:visible;pointer-events:auto}
.ts-perf-loader-card{position:relative;width:min(100%,410px);max-height:min(88vh,680px);overflow:auto;padding:24px 22px 20px;text-align:center;border:1px solid rgba(215,185,125,.34);border-radius:17px;background:radial-gradient(ellipse at 50% -35%,rgba(194,155,83,.17),transparent 65%),linear-gradient(145deg,#171510,#0c0b09 72%);box-shadow:0 25px 90px rgba(0,0,0,.55),inset 0 1px rgba(255,255,255,.035)}
.ts-perf-loader-card:before{content:"";position:absolute;top:0;left:18%;right:18%;height:1px;background:linear-gradient(90deg,transparent,#d6b36c,transparent);opacity:.9}
.ts-perf-loader-mark{position:relative;width:64px;height:64px;margin:0 auto 14px;display:grid;place-items:center}
.ts-perf-loader-ring,.ts-perf-loader-ring:before,.ts-perf-loader-ring:after{position:absolute;inset:0;border-radius:50%;border:1px solid transparent;content:""}
.ts-perf-loader-ring{border-top-color:#e4c27f;border-right-color:rgba(212,177,109,.22);box-shadow:0 0 24px rgba(214,179,108,.08)}
.ts-perf-loader-ring:before{inset:7px;border-bottom-color:#b89451;border-left-color:rgba(212,177,109,.18)}
.ts-perf-loader-ring:after{inset:14px;border-top-color:rgba(236,211,159,.72);border-right-color:rgba(236,211,159,.10)}
.ts-perf-loader.is-active .ts-perf-loader-ring{animation:tsPerfSpin 1.15s linear infinite}
.ts-perf-loader.is-active .ts-perf-loader-ring:before{animation:tsPerfSpin 1.8s linear infinite reverse}
.ts-perf-loader.is-active .ts-perf-loader-ring:after{animation:tsPerfSpin 2.4s linear infinite}
.ts-perf-loader-monogram{position:relative;z-index:1;color:#e6d2a8;font:500 17px 'Cormorant Garamond',Georgia,serif;letter-spacing:.11em;text-shadow:0 0 18px rgba(220,185,112,.28)}
.ts-perf-loader-eyebrow{margin:0 0 6px;color:#bba477;font-size:8px;font-weight:600;letter-spacing:.20em;text-transform:uppercase}
.ts-perf-loader-title{margin:0;color:#f3ead9;font:500 24px 'Cormorant Garamond',Georgia,serif;letter-spacing:.01em}
.ts-perf-loader-device{display:inline-flex;align-items:center;gap:7px;margin:10px 0 0;padding:6px 10px;border:1px solid rgba(214,179,108,.19);border-radius:999px;color:#d5c39f;background:rgba(214,179,108,.06);font-size:9px;letter-spacing:.10em;text-transform:uppercase}
.ts-perf-loader-device:before{content:"";width:6px;height:6px;border-radius:50%;background:#d6b36c;box-shadow:0 0 9px #d6b36c}
.ts-perf-loader-status{min-height:17px;margin:12px 0 0;color:#b9b0a1;font-size:10px;line-height:1.6}
.ts-perf-steps{display:grid;gap:7px;margin:17px 0 0;padding:0;text-align:left;list-style:none}
.ts-perf-step{display:grid;grid-template-columns:24px minmax(0,1fr);gap:9px;align-items:center;padding:8px 9px;border:1px solid rgba(255,255,255,.055);border-radius:9px;background:rgba(255,255,255,.018);opacity:.52;transition:opacity .2s ease,border-color .2s ease,background .2s ease}
.ts-perf-step-mark{width:22px;height:22px;display:grid;place-items:center;border:1px solid rgba(214,179,108,.22);border-radius:50%;color:#8f836b;font:600 8px 'DM Sans',sans-serif}
.ts-perf-step-copy{min-width:0}.ts-perf-step-copy strong{display:block;color:#c9c0af;font-size:9px;font-weight:600;line-height:1.4}.ts-perf-step-copy small{display:block;margin-top:2px;color:#827969;font-size:8px;line-height:1.4}
.ts-perf-step.is-current{opacity:1;border-color:rgba(214,179,108,.42);background:rgba(214,179,108,.07)}
.ts-perf-step.is-current .ts-perf-step-mark{border-color:#c9a96e;color:#f0dba9;box-shadow:0 0 12px rgba(214,179,108,.13);animation:tsPerfPulse 1.4s ease-in-out infinite}
.ts-perf-step.is-current .ts-perf-step-copy strong{color:#f0dfbd}
.ts-perf-step.is-done{opacity:.88}.ts-perf-step.is-done .ts-perf-step-mark{border-color:rgba(137,190,145,.38);color:#a9d0b3}
.ts-perf-loader-track{height:3px;margin:13px 0 0;overflow:hidden;border-radius:10px;background:rgba(255,255,255,.08)}
.ts-perf-loader-track span{display:block;width:25%;height:100%;border-radius:10px;background:linear-gradient(90deg,transparent,#d6b36c,#f0dba9,transparent);box-shadow:0 0 12px rgba(214,179,108,.5)}
.ts-perf-loader.is-active .ts-perf-loader-track span{animation:tsPerfSweep 1.45s ease-in-out infinite}
.ts-perf-loader-foot{margin:11px 0 0;color:#766f62;font-size:8px;letter-spacing:.04em}
.ts-perf-schema-note{margin:7px 0 0;color:#d9b779;font-size:8px;line-height:1.6}
@keyframes tsPerfSpin{to{transform:rotate(360deg)}}
@keyframes tsPerfSweep{0%{transform:translateX(-120%)}100%{transform:translateX(430%)}}
@keyframes tsPerfPulse{50%{box-shadow:0 0 0 4px rgba(214,179,108,.07),0 0 15px rgba(214,179,108,.2)}}
@media(prefers-reduced-motion:reduce){.ts-perf-loader.is-active .ts-perf-loader-ring,.ts-perf-loader.is-active .ts-perf-loader-ring:before,.ts-perf-loader.is-active .ts-perf-loader-ring:after,.ts-perf-loader.is-active .ts-perf-step.is-current .ts-perf-step-mark,.ts-perf-loader.is-active .ts-perf-loader-track span{animation-duration:3.5s}.ts-perf-loader,.ts-perf-run-btn{transition:none}}
</style>

<?php if ($flash): ?><div class="adm-alert <?=($flash['type'] ?? '') === 'error' ? 'is-error' : 'is-success'?>"><?=e($flash['message'] ?? '')?></div><?php endif; ?>
<?php if (!$schemaReady || $setupError !== ''): ?><div class="ts-perf-warning"><?=e($setupError)?></div><?php endif; ?>

<section class="ts-perf-intro">
  <div><p class="adm-eyebrow">TOP STUDIO / WEBSITE HEALTH</p><h2 style="margin:0;color:#f1e7d6;font:500 27px 'Cormorant Garamond',serif">Performance Monitor</h2><p>Google PageSpeed lab tests, saved history and real-user field metrics when Google has enough data. Tests only run when you press a button.</p></div>
  <div class="ts-perf-actions" style="margin-top:0">
    <a class="adm-button adm-button-outline" href="../" target="_blank" rel="noopener">View website ↗</a>
    <a class="adm-button adm-button-quiet" href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Ftopstudiobd.com%2F" target="_blank" rel="noopener">Open PageSpeed ↗</a>
  </div>
</section>

<section class="ts-perf-grid">
  <?php foreach (['desktop' => $desktop, 'mobile' => $mobile] as $device => $row): ?>
    <article class="ts-perf-card">
      <div class="ts-perf-card-head"><span><?=e(strtoupper($device))?> PERFORMANCE</span><?= $row ? ts_perf_score_badge($row['performance_score']) : '<span class="ts-perf-pill">Not tested yet</span>' ?></div>
      <?php if ($row): ?>
        <div class="ts-perf-submetrics">
          <span>FCP<b><?=e(ts_perf_seconds($row['fcp_ms']))?></b></span>
          <span>LCP<b><?=e(ts_perf_seconds($row['lcp_ms']))?></b></span>
          <span>TBT<b><?= $row['tbt_ms'] === null ? '—' : e((int)$row['tbt_ms'] . ' ms') ?></b></span>
          <span>CLS<b><?= $row['cls'] === null ? '—' : e(number_format((float)$row['cls'], 3)) ?></b></span>
        </div>
        <p class="ts-perf-note">Accessibility <?=e($row['accessibility_score'] ?? '—')?> · Best Practices <?=e($row['best_practices_score'] ?? '—')?> · SEO <?=e($row['seo_score'] ?? '—')?> · <?=e(date('d M Y, h:i A', strtotime((string)$row['created_at'])))?></p>
      <?php else: ?><div class="ts-perf-empty">No saved result yet. Run a PageSpeed test to start building your history.</div><?php endif; ?>
      <div class="ts-perf-actions">
        <form method="post" class="ts-perf-inline-form" data-ts-perf-test data-ts-perf-device="<?=e($device)?>"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="run_<?=e($device)?>"><button class="adm-button adm-button-gold ts-perf-run-btn" type="submit" data-server-disabled="<?=(!$schemaReady ? '1' : '0')?>" <?=(!$schemaReady ? 'disabled aria-disabled="true" title="Performance Monitor database setup is not ready."' : '')?>><span class="ts-perf-btn-icon" aria-hidden="true">↗</span><span>Run <?=e(ucfirst($device))?> Test</span></button></form><?php if (!$schemaReady): ?><p class="ts-perf-schema-note">Test button is unavailable because Performance Monitor database tables are not ready. Check the setup warning above; the button is not running or loading.</p><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<section class="adm-panel ts-perf-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">REAL USER EXPERIENCE</p><h2>Core Web Vitals / Field Data</h2><p class="adm-help">CrUX data comes from real Chrome users over a rolling period. It may be unavailable for a new or low-traffic site.</p></div></div>
  <div class="adm-list-table">
    <table><thead><tr><th>DEVICE</th><th>FIELD DATA STATUS</th><th>INP</th><th>LCP</th><th>CLS</th><th>FCP</th></tr></thead><tbody>
    <?php foreach (['Desktop' => $desktop, 'Mobile' => $mobile] as $label => $row):
      $field = $row ? json_decode((string)$row['field_data_json'], true) : [];
      $field = is_array($field) ? $field : [];
    ?>
      <tr><td><b><?=e($label)?></b></td><td><?= $row && !empty($row['field_data_status']) && $row['field_data_status'] !== 'NONE' ? '<span class="ts-perf-pill good">' . e($row['field_data_status']) . '</span>' : '<span class="ts-perf-pill warn">No field data</span>' ?></td>
      <td><?=isset($field['INP']) ? e(number_format((float)$field['INP']) . ' ms') : '—'?></td>
      <td><?=isset($field['LCP']) ? e(number_format((float)$field['LCP'] / 1000, 2) . ' s') : '—'?></td>
      <td><?=isset($field['CLS']) ? e(number_format((float)$field['CLS'], 3)) : '—'?></td>
      <td><?=isset($field['FCP']) ? e(number_format((float)$field['FCP'] / 1000, 2) . ' s') : '—'?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <p class="ts-perf-note">A Lighthouse score is a controlled lab result; field data is a separate real-user dataset. Do not treat missing field data as a failure.</p>
</section>

<section class="adm-panel ts-perf-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">LIGHTHOUSE INSIGHTS</p><h2>Optimization Recommendations</h2><p class="adm-help">Items below are read from the latest saved Google PageSpeed test. Recommendations are informational; they are never auto-applied.</p></div></div>
  <?php
    $hasInsights = false;
    foreach (['Mobile' => $mobile, 'Desktop' => $desktop] as $insightDevice => $insightRow):
      $insightList = $insightRow ? json_decode((string)$insightRow['insights_json'], true) : [];
      $insightList = is_array($insightList) ? $insightList : [];
      if (!$insightList) continue;
      $hasInsights = true;
  ?>
    <h3 style="margin:12px 0 9px;color:#d8c39a;font-size:10px;letter-spacing:.12em;text-transform:uppercase"><?=e($insightDevice)?> — Latest Test</h3>
    <div class="ts-perf-insight-list">
      <?php foreach (array_slice($insightList, 0, 6) as $insight): ?>
        <article class="ts-perf-insight"><b><?=e($insight['title'] ?? 'Optimization opportunity')?></b>
          <?php if (!empty($insight['display_value'])): ?><small><?=e($insight['display_value'])?> · </small><?php endif; ?>
          <small><?=!empty($insight['savings_ms']) ? e((int)$insight['savings_ms'] . ' ms possible saving') : ''?><?=!empty($insight['savings_bytes']) ? e((!empty($insight['savings_ms']) ? ' · ' : '') . number_format((int)$insight['savings_bytes'] / 1024, 1) . ' KiB possible saving') : ''?></small>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$hasInsights): ?><div class="ts-perf-empty">No optimization insights have been saved yet. Run a Mobile or Desktop PageSpeed test first.</div><?php endif; ?>
</section>

<section class="adm-panel ts-perf-section" id="boost">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">SAFE WEBSITE BOOST</p><h2>Controlled Optimization</h2><p class="adm-help">Only static-file caching can be applied by this first version. Images, PHP, JavaScript logic, CSS layout, CMS content, database records and Google OAuth are never auto-edited.</p></div><?= $cacheActive ? '<span class="ts-perf-pill good">Managed cache active</span>' : '<span class="ts-perf-pill">Not applied</span>' ?></div>
  <div class="ts-perf-good"><b>Safe Cache Boost:</b> Adds a clearly marked, IfModule-guarded cache block for static files only. A private backup is created outside public_html before the change. The homepage is checked after applying; if the check fails, the previous .htaccess is restored automatically.</div>
  <div class="ts-perf-actions">
    <form method="post" class="ts-perf-inline-form" onsubmit="return confirm('Create a private backup of .htaccess? No website file will be changed.');"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="create_backup"><button class="adm-button adm-button-outline" type="submit" <?=(!$schemaReady ? 'disabled' : '')?>>Create Backup</button></form>
    <form method="post" class="ts-perf-inline-form" onsubmit="return confirm('Apply only the managed static-cache rules? A private backup will be created first. The homepage will be checked and the change will be rolled back automatically if the check fails.');"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="apply_safe_cache"><button class="adm-button adm-button-gold" type="submit" <?=(!$schemaReady || $cacheActive ? 'disabled' : '')?>>Apply Safe Cache Boost</button></form>
  </div>
  <p class="ts-perf-form-note">No automatic image conversion, minification, plugin installation, database cleanup, PHP edits, or design changes. Those changes need separate review and a dedicated backup.</p>
  <div class="adm-divider"></div>
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">BACKUP &amp; ROLLBACK</p><h2>Recent private snapshots</h2></div></div>
  <?php if (!$backups): ?><div class="ts-perf-empty">No backup snapshots yet. A private .htaccess snapshot will be stored outside the public website folder before any cache change.</div>
  <?php else: ?>
    <div class="ts-perf-table-wrap"><table class="ts-perf-table"><thead><tr><th>DATE</th><th>ACTION</th><th>STATUS</th><th>RESTORE</th></tr></thead><tbody>
      <?php foreach ($backups as $backup): ?><tr><td><?=e(date('d M Y, h:i A', strtotime((string)$backup['created_at'])))?></td><td><?=e(str_replace('_', ' ', (string)$backup['action_name']))?></td><td><span class="ts-perf-pill <?=in_array($backup['status'], ['applied','created','restored','restore_guard'], true) ? 'good' : 'warn'?>"><?=e(str_replace('_', ' ', (string)$backup['status']))?></span></td><td>
        <?php if (in_array($backup['status'], ['applied','restored'], true)): ?><form method="post" class="ts-perf-inline-form" onsubmit="return confirm('Restore the selected .htaccess backup? A safety snapshot of the current file will be created first, and the homepage will be checked after restore.');"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="backup_id" value="<?=(int)$backup['id']?>"><button type="submit" class="adm-button adm-button-quiet">Restore</button></form><?php else: ?><span class="ts-perf-muted">—</span><?php endif; ?>
      </td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="adm-panel ts-perf-section" id="history">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">TEST HISTORY</p><h2>Recent PageSpeed Tests</h2></div></div>
  <?php if (!$history): ?><div class="ts-perf-empty">No tests saved yet. Run Mobile and Desktop tests above. The monitor will keep the latest 120 days of results.</div>
  <?php else: ?><div class="ts-perf-table-wrap"><table class="ts-perf-table"><thead><tr><th>DATE</th><th>DEVICE</th><th>PERFORMANCE</th><th>ACCESSIBILITY</th><th>BEST PRACTICES</th><th>SEO</th><th>LCP</th><th>INP (FIELD)</th></tr></thead><tbody>
    <?php foreach ($history as $item): $field = json_decode((string)$item['field_data_json'], true); $field = is_array($field) ? $field : []; ?><tr><td><?=e(date('d M, h:i A', strtotime((string)$item['created_at'])))?></td><td><?=e(ucfirst((string)$item['strategy']))?></td><td><?=ts_perf_score_badge($item['performance_score'])?></td><td><?=e($item['accessibility_score'] ?? '—')?></td><td><?=e($item['best_practices_score'] ?? '—')?></td><td><?=e($item['seo_score'] ?? '—')?></td><td><?=e(ts_perf_seconds($item['lcp_ms']))?></td><td><?=isset($field['INP']) ? e(number_format((float)$field['INP']) . ' ms') : '—'?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<section class="adm-panel ts-perf-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">SETUP &amp; LIMITS</p><h2>How the monitor works</h2></div></div>
  <ul style="margin:0;padding-left:18px;color:#a49d90;font-size:9px;line-height:1.9">
    <li>PageSpeed tests run only when an administrator presses a button. There is a 10-minute cooldown per device to protect API quota.</li>
    <li>The PageSpeed API can be called without a key for occasional manual testing. For frequent or scheduled use, Google recommends configuring an API key.</li>
    <li>An optional key can be defined server-side in config.php as <code>define('PAGESPEED_API_KEY', 'YOUR_KEY');</code>. Never paste it into a public website field or share it in chat.</li>
    <li>Daily automatic scans are not enabled in this version. They can be added later through a restricted cPanel Cron task.</li>
    <li>Safe Cache Boost affects only static resource caching. It does not change source images, layout, page text, database content, admin login or OAuth.</li>
  </ul>
</section>

<div class="ts-perf-loader" id="tsPerfLoader" aria-hidden="true" role="status" aria-live="polite" aria-label="PageSpeed test is running">
  <div class="ts-perf-loader-card">
    <div class="ts-perf-loader-mark" aria-hidden="true"><span class="ts-perf-loader-ring"></span><span class="ts-perf-loader-monogram">TS</span></div>
    <p class="ts-perf-loader-eyebrow">TOP STUDIO / PERFORMANCE LAB</p>
    <h2 class="ts-perf-loader-title">Analyzing Your Website</h2>
    <div class="ts-perf-loader-device" id="tsPerfLoaderDevice">Mobile PageSpeed Test</div>
    <p class="ts-perf-loader-status" id="tsPerfLoaderStatus">Test request is being prepared…</p>
    <ol class="ts-perf-steps" id="tsPerfSteps">
      <li class="ts-perf-step" data-step="0"><span class="ts-perf-step-mark">01</span><span class="ts-perf-step-copy"><strong>Preparing the test</strong><small>Selected device and website URL</small></span></li>
      <li class="ts-perf-step" data-step="1"><span class="ts-perf-step-mark">02</span><span class="ts-perf-step-copy"><strong>Connecting to Google PageSpeed</strong><small>Sending a secure test request</small></span></li>
      <li class="ts-perf-step" data-step="2"><span class="ts-perf-step-mark">03</span><span class="ts-perf-step-copy"><strong>Running Lighthouse audit</strong><small>Loading, layout and responsiveness</small></span></li>
      <li class="ts-perf-step" data-step="3"><span class="ts-perf-step-mark">04</span><span class="ts-perf-step-copy"><strong>Waiting for the test report</strong><small>FCP, LCP, TBT, CLS, SEO and accessibility</small></span></li>
    </ol>
    <div class="ts-perf-loader-track" aria-hidden="true"><span></span></div>
    <p class="ts-perf-loader-foot">Steps update while the test runs · No fake percentage</p>
  </div>
</div>
<script>
(function () {
  'use strict';
  var loader = document.getElementById('tsPerfLoader');
  if (!loader || loader.dataset.bound === '1') return;
  loader.dataset.bound = '1';
  var deviceLabel = document.getElementById('tsPerfLoaderDevice');
  var statusLabel = document.getElementById('tsPerfLoaderStatus');
  var steps = Array.prototype.slice.call(loader.querySelectorAll('[data-step]'));
  var running = false;
  var timers = [];

  function clearTimers() {
    timers.forEach(function (timer) { window.clearTimeout(timer); });
    timers = [];
  }
  function setStep(activeIndex) {
    steps.forEach(function (item, index) {
      var mark = item.querySelector('.ts-perf-step-mark');
      item.classList.remove('is-current', 'is-done');
      if (index < activeIndex) {
        item.classList.add('is-done');
        if (mark) mark.textContent = '✓';
      } else if (index === activeIndex) {
        item.classList.add('is-current');
        if (mark) mark.textContent = '•';
      } else if (mark) {
        mark.textContent = String(index + 1).padStart(2, '0');
      }
    });
    var descriptions = [
      'Preparing the selected device test and website address…',
      'Connecting securely and sending the request to Google…',
      'Google Lighthouse is checking page loading and rendering…',
      'Waiting for Google to return the audit and performance metrics…'
    ];
    statusLabel.textContent = descriptions[Math.min(activeIndex, descriptions.length - 1)];
  }
  function resetAfterNavigation() {
    // Browsers can restore this page from back/forward cache with disabled controls.
    clearTimers();
    running = false;
    loader.classList.remove('is-active');
    loader.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
    document.querySelectorAll('form[data-ts-perf-test] button[type="submit"]').forEach(function (button) {
      if (button.getAttribute('data-server-disabled') === '1') return;
      button.disabled = false;
      button.removeAttribute('aria-busy');
      var label = button.querySelector('span:last-child');
      if (label) label.textContent = 'Run ' + ((button.closest('form').getAttribute('data-ts-perf-device') || 'mobile').replace(/^./, function (c) { return c.toUpperCase(); })) + ' Test';
    });
    steps.forEach(function (item, index) {
      item.classList.remove('is-current', 'is-done');
      var mark = item.querySelector('.ts-perf-step-mark');
      if (mark) mark.textContent = String(index + 1).padStart(2, '0');
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-ts-perf-test]')) return;
    var button = event.submitter || form.querySelector('button[type="submit"]');
    if (button && button.getAttribute('data-server-disabled') === '1') {
      event.preventDefault();
      return;
    }
    if (running) { event.preventDefault(); return; }
    running = true;
    clearTimers();
    var device = (form.getAttribute('data-ts-perf-device') || 'mobile').toLowerCase();
    deviceLabel.textContent = (device === 'desktop' ? 'Desktop' : 'Mobile') + ' PageSpeed Test';
    loader.classList.add('is-active');
    loader.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    setStep(0);

    // Keep the test controls from receiving a second submission while this request is in flight.
    document.querySelectorAll('form[data-ts-perf-test] button[type="submit"]').forEach(function (item) {
      item.disabled = true;
      item.setAttribute('aria-busy', 'true');
      item.setAttribute('data-ts-busy', '1');
    });

    // Google returns a single completed Lighthouse report, not live phase telemetry.
    // These are honest high-level activity stages; no fabricated percentage is shown.
    timers.push(window.setTimeout(function () { if (running) setStep(1); }, 900));
    timers.push(window.setTimeout(function () { if (running) setStep(2); }, 2600));
    timers.push(window.setTimeout(function () { if (running) setStep(3); }, 6500));
  }, true);

  window.addEventListener('pageshow', resetAfterNavigation);
  window.addEventListener('pagehide', clearTimers);
})();
</script>

<?php ts_admin_page_end(); ?>
