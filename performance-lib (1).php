<?php
/**
 * TOP STUDIO Performance Monitor & Safe Boost helpers.
 * Additive only: creates two reporting/backup tables and never edits CMS records.
 */

function ts_perf_site_url(): string {
    return 'https://topstudiobd.com/';
}

function ts_perf_root(): string {
    $path = realpath(dirname(__DIR__));
    return $path !== false ? $path : dirname(__DIR__);
}

function ts_perf_ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ts_performance_scans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        strategy VARCHAR(10) NOT NULL,
        requested_url VARCHAR(512) NOT NULL,
        performance_score TINYINT UNSIGNED NULL,
        accessibility_score TINYINT UNSIGNED NULL,
        best_practices_score TINYINT UNSIGNED NULL,
        seo_score TINYINT UNSIGNED NULL,
        fcp_ms INT UNSIGNED NULL,
        lcp_ms INT UNSIGNED NULL,
        tbt_ms INT UNSIGNED NULL,
        cls DECIMAL(12,5) NULL,
        speed_index_ms INT UNSIGNED NULL,
        inp_ms INT UNSIGNED NULL,
        field_data_status VARCHAR(20) NULL,
        field_data_json LONGTEXT NULL,
        insights_json LONGTEXT NULL,
        lighthouse_version VARCHAR(40) NULL,
        fetched_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ts_perf_strategy_created (strategy, created_at),
        KEY idx_ts_perf_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ts_performance_backups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        backup_ref CHAR(32) NOT NULL,
        backup_path VARCHAR(500) NOT NULL,
        target_file VARCHAR(32) NOT NULL DEFAULT '.htaccess',
        file_existed TINYINT(1) NOT NULL DEFAULT 0,
        checksum CHAR(64) NULL,
        action_name VARCHAR(40) NOT NULL DEFAULT 'manual_backup',
        status VARCHAR(32) NOT NULL DEFAULT 'created',
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ts_perf_backup_ref (backup_ref),
        KEY idx_ts_perf_backup_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ts_perf_schema_exists(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('ts_performance_scans', 'ts_performance_backups')");
    return (int)$stmt->fetchColumn() === 2;
}

function ts_perf_http_get(string $url, int $timeout = 45): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'TOP-STUDIO-Performance-Monitor/1.0 (+https://topstudiobd.com/)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['body' => is_string($body) ? $body : '', 'status' => $status, 'error' => $error];
    }

    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        return ['body' => '', 'status' => 0, 'error' => 'The hosting server has neither cURL nor remote URL access enabled.'];
    }
    $context = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
        'header' => "User-Agent: TOP-STUDIO-Performance-Monitor/1.0\r\nAccept: application/json,text/html;q=0.9\r\n",
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) $status = (int)$m[1];
        }
    }
    return ['body' => is_string($body) ? $body : '', 'status' => $status, 'error' => $body === false ? 'Could not connect to the remote service.' : ''];
}

function ts_perf_api_key(): string {
    return defined('PAGESPEED_API_KEY') ? trim((string)PAGESPEED_API_KEY) : '';
}

function ts_perf_ms($audits, string $key): ?int {
    if (!isset($audits[$key]['numericValue']) || !is_numeric($audits[$key]['numericValue'])) return null;
    return max(0, (int)round((float)$audits[$key]['numericValue']));
}

function ts_perf_field_data(array $response): array {
    $metrics = $response['loadingExperience']['metrics'] ?? [];
    if (!$metrics) $metrics = $response['originLoadingExperience']['metrics'] ?? [];
    $result = [];
    $map = [
        'FCP' => ['FIRST_CONTENTFUL_PAINT_MS', 1],
        'LCP' => ['LARGEST_CONTENTFUL_PAINT_MS', 1],
        'INP' => ['INTERACTION_TO_NEXT_PAINT', 1000],
        'CLS' => ['CUMULATIVE_LAYOUT_SHIFT_SCORE', 1000],
        'TTFB' => ['EXPERIMENTAL_TIME_TO_FIRST_BYTE', 1],
    ];
    foreach ($map as $label => $spec) {
        $key = $spec[0];
        if (isset($metrics[$key]['percentile']) && is_numeric($metrics[$key]['percentile'])) {
            $result[$label] = (float)$metrics[$key]['percentile'] / $spec[1];
        }
    }
    $status = $response['loadingExperience']['overall_category'] ?? ($response['originLoadingExperience']['overall_category'] ?? 'NONE');
    return ['status' => (string)$status, 'metrics' => $result];
}

function ts_perf_collect_insights(array $audits): array {
    $ids = [
        'largest-contentful-paint-element', 'lcp-breakdown-insight', 'render-blocking-resources',
        'uses-optimized-images', 'modern-image-formats', 'uses-responsive-images',
        'uses-long-cache-ttl', 'unused-javascript', 'dom-size', 'forced-reflow-insight',
        'network-dependency-tree-insight', 'mainthread-work-breakdown', 'uses-text-compression',
    ];
    $items = [];
    foreach ($ids as $id) {
        if (empty($audits[$id]) || !is_array($audits[$id])) continue;
        $a = $audits[$id];
        if (isset($a['score']) && $a['score'] !== null && (float)$a['score'] >= 0.9) continue;
        $details = $a['details'] ?? [];
        $items[] = [
            'id' => $id,
            'title' => (string)($a['title'] ?? $id),
            'description' => (string)($a['description'] ?? ''),
            'display_value' => (string)($a['displayValue'] ?? ''),
            'score' => isset($a['score']) && is_numeric($a['score']) ? (float)$a['score'] : null,
            'savings_ms' => isset($details['overallSavingsMs']) ? (int)$details['overallSavingsMs'] : 0,
            'savings_bytes' => isset($details['overallSavingsBytes']) ? (int)$details['overallSavingsBytes'] : 0,
        ];
    }
    return $items;
}

function ts_perf_run_scan(PDO $pdo, string $strategy): array {
    if (!in_array($strategy, ['mobile', 'desktop'], true)) throw new RuntimeException('Invalid test device.');
    if (function_exists('set_time_limit')) @set_time_limit(60);

    $endpoint = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';
    $query = 'url=' . rawurlencode(ts_perf_site_url()) . '&strategy=' . rawurlencode($strategy)
        . '&category=performance&category=accessibility&category=best-practices&category=seo&locale=en';
    $key = ts_perf_api_key();
    if ($key !== '') $query .= '&key=' . rawurlencode($key);

    $http = ts_perf_http_get($endpoint . '?' . $query, 45);
    $json = json_decode($http['body'], true);
    if ($http['status'] < 200 || $http['status'] >= 300 || !is_array($json)) {
        $message = $json['error']['message'] ?? ($http['error'] !== '' ? $http['error'] : 'Google PageSpeed API returned an invalid response.');
        if ($http['status'] === 429) $message = 'Google PageSpeed quota/rate limit reached (HTTP 429). This monitor will pause new tests for 30 minutes to protect the quota. The Admin session is released while Google responds, so other Admin pages should remain usable.';
        if ($http['status'] === 403 && $key === '') $message = 'Google API access was denied. Enable the PageSpeed Insights API and optionally configure PAGESPEED_API_KEY in config.php.';
        throw new RuntimeException('PageSpeed test failed (' . (int)$http['status'] . '): ' . $message);
    }
    $lhr = $json['lighthouseResult'] ?? null;
    if (!is_array($lhr)) throw new RuntimeException('Google returned no Lighthouse result for this test.');
    $categories = $lhr['categories'] ?? [];
    $audits = $lhr['audits'] ?? [];
    $score = static function (array $category): ?int {
        return isset($category['score']) && is_numeric($category['score']) ? max(0, min(100, (int)round((float)$category['score'] * 100))) : null;
    };
    $field = ts_perf_field_data($json);
    $fieldMetrics = $field['metrics'];
    $fcp = ts_perf_ms($audits, 'first-contentful-paint');
    $lcp = ts_perf_ms($audits, 'largest-contentful-paint');
    $tbt = ts_perf_ms($audits, 'total-blocking-time');
    $si = ts_perf_ms($audits, 'speed-index');
    $cls = isset($audits['cumulative-layout-shift']['numericValue']) && is_numeric($audits['cumulative-layout-shift']['numericValue'])
        ? (float)$audits['cumulative-layout-shift']['numericValue'] : null;
    $inp = isset($fieldMetrics['INP']) ? (int)round($fieldMetrics['INP']) : null;
    $insights = ts_perf_collect_insights($audits);

    $stmt = $pdo->prepare('INSERT INTO ts_performance_scans
        (strategy, requested_url, performance_score, accessibility_score, best_practices_score, seo_score,
         fcp_ms, lcp_ms, tbt_ms, cls, speed_index_ms, inp_ms, field_data_status, field_data_json,
         insights_json, lighthouse_version, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $strategy, (string)($lhr['finalUrl'] ?? ts_perf_site_url()),
        $score($categories['performance'] ?? []), $score($categories['accessibility'] ?? []),
        $score($categories['best-practices'] ?? []), $score($categories['seo'] ?? []),
        $fcp, $lcp, $tbt, $cls, $si, $inp, $field['status'],
        json_encode($fieldMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($insights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        substr((string)($lhr['lighthouseVersion'] ?? ''), 0, 40),
        !empty($lhr['fetchTime']) ? date('Y-m-d H:i:s', strtotime((string)$lhr['fetchTime'])) : date('Y-m-d H:i:s'),
    ]);
    $id = (int)$pdo->lastInsertId();
    $pdo->exec("DELETE FROM ts_performance_scans WHERE created_at < DATE_SUB(NOW(), INTERVAL 120 DAY)");

    return ['id' => $id, 'strategy' => $strategy, 'performance' => $score($categories['performance'] ?? []),
        'accessibility' => $score($categories['accessibility'] ?? []), 'best_practices' => $score($categories['best-practices'] ?? []),
        'seo' => $score($categories['seo'] ?? []), 'fcp_ms' => $fcp, 'lcp_ms' => $lcp, 'tbt_ms' => $tbt,
        'cls' => $cls, 'speed_index_ms' => $si, 'field_status' => $field['status']];
}

function ts_perf_latest(PDO $pdo, string $strategy): ?array {
    $stmt = $pdo->prepare('SELECT * FROM ts_performance_scans WHERE strategy = ? ORDER BY created_at DESC, id DESC LIMIT 1');
    $stmt->execute([$strategy]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function ts_perf_recent(PDO $pdo, int $limit = 12): array {
    $limit = max(1, min(30, $limit));
    return $pdo->query('SELECT * FROM ts_performance_scans ORDER BY created_at DESC, id DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC);
}

function ts_perf_backup_root(): string {
    return dirname(ts_perf_root()) . DIRECTORY_SEPARATOR . '.topstudio-private-performance-backups';
}

function ts_perf_create_htaccess_backup(PDO $pdo, string $action = 'manual_backup'): int {
    $root = ts_perf_root();
    $target = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_link($target)) throw new RuntimeException('The .htaccess file is a symbolic link. Backup/Boost was stopped for safety.');
    if (is_file($target) && filesize($target) > 1048576) throw new RuntimeException('The current .htaccess is larger than 1 MB. Backup/Boost was stopped for safety.');

    $backupRoot = ts_perf_backup_root();
    if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0700, true)) throw new RuntimeException('Private backup folder could not be created outside public_html. Check hosting folder permissions.');
    $backupRootReal = realpath($backupRoot);
    $rootReal = realpath($root);
    if ($backupRootReal === false || $rootReal === false || strpos($backupRootReal, $rootReal . DIRECTORY_SEPARATOR) === 0) {
        throw new RuntimeException('Private backup storage is not safely located outside the website public folder.');
    }
    if (!is_writable($backupRootReal)) throw new RuntimeException('Private backup folder is not writable. No website files were changed.');

    $ref = bin2hex(random_bytes(16));
    $dir = $backupRootReal . DIRECTORY_SEPARATOR . $ref;
    if (!@mkdir($dir, 0700, true)) throw new RuntimeException('Could not create a private backup snapshot.');
    $existed = is_file($target);
    $backupFile = $dir . DIRECTORY_SEPARATOR . 'htaccess.snapshot';
    $checksum = null;
    if ($existed) {
        if (!is_readable($target) || !@copy($target, $backupFile)) {
            @rmdir($dir);
            throw new RuntimeException('Could not copy the current .htaccess. No website files were changed.');
        }
        @chmod($backupFile, 0600);
        $checksum = hash_file('sha256', $backupFile) ?: null;
    }
    $stmt = $pdo->prepare("INSERT INTO ts_performance_backups (backup_ref, backup_path, target_file, file_existed, checksum, action_name, status, created_by) VALUES (?, ?, '.htaccess', ?, ?, ?, 'created', ?)");
    $stmt->execute([$ref, $backupFile, $existed ? 1 : 0, $checksum, $action, isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null]);
    return (int)$pdo->lastInsertId();
}

function ts_perf_safe_cache_block(): string {
    return <<<'HTACCESS'
# BEGIN TOP STUDIO SAFE STATIC CACHE
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 7 days"
  ExpiresByType application/javascript "access plus 7 days"
  ExpiresByType text/javascript "access plus 7 days"
  ExpiresByType image/jpeg "access plus 30 days"
  ExpiresByType image/png "access plus 7 days"
  ExpiresByType image/webp "access plus 30 days"
  ExpiresByType image/avif "access plus 30 days"
  ExpiresByType image/svg+xml "access plus 7 days"
  ExpiresByType font/woff2 "access plus 30 days"
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "\.(?:css|js)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
  <FilesMatch "\.(?:jpe?g|webp|avif)$">
    Header set Cache-Control "public, max-age=2592000"
  </FilesMatch>
  <FilesMatch "\.(?:png|svg|woff2)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
</IfModule>
# END TOP STUDIO SAFE STATIC CACHE
HTACCESS;
}

function ts_perf_verify_live_home(): array {
    $result = ts_perf_http_get(ts_perf_site_url(), 25);
    $status = (int)$result['status'];
    $ok = $status >= 200 && $status < 300 && stripos((string)$result['body'], 'TOP STUDIO') !== false;
    return ['ok' => $ok, 'status' => $status, 'error' => (string)$result['error']];
}

function ts_perf_restore_snapshot_file(array $backup): void {
    $root = ts_perf_root();
    $target = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_link($target)) throw new RuntimeException('The current .htaccess is a symbolic link. Restore stopped for safety.');
    if ((int)$backup['file_existed'] === 1) {
        $backupRoot = realpath(ts_perf_backup_root());
        $backupFile = realpath((string)$backup['backup_path']);
        if ($backupRoot === false || $backupFile === false || strpos($backupFile, $backupRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($backupFile)) {
            throw new RuntimeException('Private backup snapshot could not be verified. Restore was stopped.');
        }
        $root = ts_perf_root();
        $temp = @tempnam($root, '.ts-restore-');
        if ($temp === false) throw new RuntimeException('Could not create a temporary restore file.');
        if (!@copy($backupFile, $temp)) { @unlink($temp); throw new RuntimeException('Could not copy the saved .htaccess snapshot.'); }
        @chmod($temp, 0644);
        if (!@rename($temp, $target)) { @unlink($temp); throw new RuntimeException('Could not safely replace .htaccess with the backup snapshot.'); }
    } else {
        if (is_file($target) && !@unlink($target)) throw new RuntimeException('Could not remove the .htaccess created by the boost.');
    }
}

function ts_perf_apply_safe_cache_boost(PDO $pdo): array {
    $root = ts_perf_root();
    $target = $root . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_link($target)) throw new RuntimeException('The .htaccess file is a symbolic link. Boost was stopped for safety.');
    $current = is_file($target) ? @file_get_contents($target) : '';
    if ($current === false) throw new RuntimeException('Could not read .htaccess. No changes were made.');
    if (strpos($current, '# BEGIN TOP STUDIO SAFE STATIC CACHE') !== false) {
        return ['already_active' => true, 'backup_id' => null];
    }

    $backupId = ts_perf_create_htaccess_backup($pdo, 'safe_cache_boost');
    $backupStmt = $pdo->prepare('SELECT * FROM ts_performance_backups WHERE id = ? LIMIT 1');
    $backupStmt->execute([$backupId]);
    $backup = $backupStmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) throw new RuntimeException('Backup record could not be verified. Boost was stopped.');

    $newContent = rtrim((string)$current) . (trim((string)$current) !== '' ? "\n\n" : '') . ts_perf_safe_cache_block() . "\n";
    $temp = @tempnam($root, '.ts-cache-');
    if ($temp === false) throw new RuntimeException('Could not create a temporary config file. Backup was saved; no change was applied.');
    $written = @file_put_contents($temp, $newContent, LOCK_EX);
    if ($written === false) { @unlink($temp); throw new RuntimeException('Could not write the cache rules. Backup was saved; no change was applied.'); }
    @chmod($temp, is_file($target) ? (fileperms($target) & 0777) : 0644);
    if (!@rename($temp, $target)) { @unlink($temp); throw new RuntimeException('Could not safely replace .htaccess. Backup was saved; no change was applied.'); }

    $check = ts_perf_verify_live_home();
    if (!$check['ok']) {
        ts_perf_restore_snapshot_file($backup);
        $u = $pdo->prepare("UPDATE ts_performance_backups SET status = 'auto_rolled_back' WHERE id = ?");
        $u->execute([$backupId]);
        throw new RuntimeException('The homepage health check did not pass (HTTP ' . $check['status'] . '). The previous .htaccess was automatically restored.');
    }
    $u = $pdo->prepare("UPDATE ts_performance_backups SET status = 'applied' WHERE id = ?");
    $u->execute([$backupId]);
    return ['already_active' => false, 'backup_id' => $backupId];
}

function ts_perf_restore_backup(PDO $pdo, int $backupId): void {
    if ($backupId <= 0) throw new RuntimeException('Invalid backup selection.');
    $stmt = $pdo->prepare('SELECT * FROM ts_performance_backups WHERE id = ? LIMIT 1');
    $stmt->execute([$backupId]);
    $chosen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$chosen || $chosen['target_file'] !== '.htaccess') throw new RuntimeException('The selected backup was not found or is not restorable.');

    $guardId = ts_perf_create_htaccess_backup($pdo, 'pre_restore_safety_snapshot');
    $g = $pdo->prepare('SELECT * FROM ts_performance_backups WHERE id = ? LIMIT 1');
    $g->execute([$guardId]);
    $guard = $g->fetch(PDO::FETCH_ASSOC);
    try {
        ts_perf_restore_snapshot_file($chosen);
        $check = ts_perf_verify_live_home();
        if (!$check['ok']) {
            ts_perf_restore_snapshot_file($guard);
            $u = $pdo->prepare("UPDATE ts_performance_backups SET status = 'restore_failed_reverted' WHERE id = ?");
            $u->execute([$backupId]);
            throw new RuntimeException('Restored configuration failed the homepage check. The current configuration was put back automatically.');
        }
        $u = $pdo->prepare("UPDATE ts_performance_backups SET status = 'restored' WHERE id = ?");
        $u->execute([$backupId]);
        $u = $pdo->prepare("UPDATE ts_performance_backups SET status = 'restore_guard' WHERE id = ?");
        $u->execute([$guardId]);
    } catch (Throwable $e) {
        throw $e;
    }
}

function ts_perf_cache_active(): bool {
    $file = ts_perf_root() . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($file) || !is_readable($file)) return false;
    $content = (string)@file_get_contents($file);
    return strpos($content, '# BEGIN TOP STUDIO SAFE STATIC CACHE') !== false;
}

function ts_perf_backups(PDO $pdo): array {
    return $pdo->query('SELECT id, action_name, status, file_existed, created_at FROM ts_performance_backups ORDER BY id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
}

function ts_perf_seconds($milliseconds): string {
    if ($milliseconds === null || $milliseconds === '') return '—';
    return number_format(((float)$milliseconds) / 1000, 2) . ' s';
}

function ts_perf_score_badge($score): string {
    if ($score === null || $score === '') return '<span class="ts-perf-score is-empty">—</span>';
    $n = (int)$score;
    $class = $n >= 90 ? 'is-good' : ($n >= 50 ? 'is-mid' : 'is-low');
    return '<span class="ts-perf-score ' . $class . '">' . $n . '</span>';
}
