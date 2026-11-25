<?php
/**
 * TOP STUDIO Performance & Security Suite V2 support library.
 * Read-only scanners and private report storage. No CMS/website data is changed.
 */

function ts_suite_web_root(): string
{
    $path = realpath(dirname(__DIR__));
    return $path !== false ? $path : dirname(__DIR__);
}

function ts_suite_private_dir(): string
{
    $webRoot = ts_suite_web_root();
    $parent = dirname($webRoot);
    $dir = $parent . DIRECTORY_SEPARATOR . '.topstudio-private-suite';

    if (is_link($dir)) {
        throw new RuntimeException('Private Suite storage is a symbolic link; scan report storage was refused.');
    }
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        throw new RuntimeException('Private Suite storage could not be created outside public_html.');
    }
    $real = realpath($dir);
    $rootReal = realpath($webRoot);
    if ($real === false || $rootReal === false || $real === $rootReal || strpos($real, $rootReal . DIRECTORY_SEPARATOR) === 0) {
        throw new RuntimeException('Private Suite storage is not outside the public website folder.');
    }
    @chmod($real, 0700);
    if (!is_writable($real)) {
        throw new RuntimeException('Private Suite storage is not writable.');
    }
    return $real;
}

function ts_suite_report_path(string $type): string
{
    if (!in_array($type, ['security', 'health'], true)) {
        throw new InvalidArgumentException('Invalid Suite report type.');
    }
    return ts_suite_private_dir() . DIRECTORY_SEPARATOR . $type . '-latest.json';
}

function ts_suite_save_report(string $type, array $report): void
{
    $path = ts_suite_report_path($type);
    $json = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('The scan report could not be encoded.');
    }
    $temp = @tempnam(dirname($path), '.suite-report-');
    if ($temp === false) {
        throw new RuntimeException('A private temporary report file could not be created.');
    }
    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        @unlink($temp);
        throw new RuntimeException('The scan report could not be saved.');
    }
    @chmod($temp, 0600);
    if (!@rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('The private scan report could not be committed.');
    }
}

function ts_suite_read_report(string $type): array
{
    try {
        $path = ts_suite_report_path($type);
        if (!is_file($path) || !is_readable($path)) return [];
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable $e) {
        return [];
    }
}

function ts_suite_audit_event(string $event, array $details = []): void
{
    try {
        $path = ts_suite_private_dir() . DIRECTORY_SEPARATOR . 'activity.jsonl';
        if (is_file($path) && (int)@filesize($path) > 262144) {
            $old = $path . '.1';
            if (is_file($old)) @unlink($old);
            @rename($path, $old);
        }
        $record = [
            'at' => gmdate('c'),
            'admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            'event' => substr(preg_replace('/[^a-z0-9_.-]/i', '', $event) ?: 'event', 0, 48),
            'details' => $details,
        ];
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json)) @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        if (is_file($path)) @chmod($path, 0600);
    } catch (Throwable $e) {
        error_log('TOP STUDIO Suite audit log write failed: ' . $e->getMessage());
    }
}

function ts_suite_recent_events(int $limit = 12): array
{
    try {
        $path = ts_suite_private_dir() . DIRECTORY_SEPARATOR . 'activity.jsonl';
        if (!is_file($path) || !is_readable($path)) return [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) return [];
        $lines = array_slice($lines, -max(1, min(30, $limit)));
        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) $rows[] = $row;
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/** HEAD only; follows same-site redirects and verifies TLS. */
function ts_suite_http_head(string $url, int $timeout = 6): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || strtolower((string)($parts['host'] ?? '')) !== 'topstudiobd.com') {
        throw new InvalidArgumentException('Suite scanner only permits HTTPS requests to topstudiobd.com.');
    }

    $result = ['status' => 0, 'headers' => [], 'effective_url' => $url, 'redirects' => 0, 'error' => ''];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'TOP-STUDIO-Suite-Scanner/2.0 (+https://topstudiobd.com/)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $result['status'] = (int)($info['http_code'] ?? 0);
        $result['effective_url'] = (string)($info['url'] ?? $url);
        $result['redirects'] = (int)($info['redirect_count'] ?? 0);
        $result['error'] = $error;
        if (is_string($raw)) {
            $blocks = preg_split("/\r?\n\r?\n/", trim($raw)) ?: [];
            foreach ($blocks as $block) {
                $lines = preg_split("/\r?\n/", $block) ?: [];
                if (!$lines || !preg_match('~^HTTP/\S+\s+\d{3}~i', trim((string)$lines[0]))) continue;
                $headers = [];
                foreach (array_slice($lines, 1) as $line) {
                    $pos = strpos($line, ':');
                    if ($pos === false) continue;
                    $name = strtolower(trim(substr($line, 0, $pos)));
                    $value = trim(substr($line, $pos + 1));
                    if ($name !== '') $headers[$name] = $value;
                }
                $result['headers'] = $headers;
            }
        }
        return $result;
    }

    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $result['error'] = 'cURL and allow_url_fopen are unavailable.';
        return $result;
    }
    $ctx = stream_context_create([
        'http' => ['method' => 'HEAD', 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 4],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $headers = @get_headers($url, true, $ctx);
    if (!is_array($headers)) {
        $result['error'] = 'The hosting server could not complete a verified HTTPS HEAD request.';
        return $result;
    }
    foreach ($headers as $name => $value) {
        if (is_int($name) && preg_match('~^HTTP/\S+\s+(\d{3})~i', (string)$value, $m)) $result['status'] = (int)$m[1];
        elseif (!is_int($name)) $result['headers'][strtolower((string)$name)] = is_array($value) ? (string)end($value) : (string)$value;
    }
    return $result;
}

function ts_suite_status_item(string $area, string $label, string $status, string $detail): array
{
    return ['area' => $area, 'label' => $label, 'status' => $status, 'detail' => $detail];
}

function ts_suite_security_scan(): array
{
    if (function_exists('set_time_limit')) @set_time_limit(40);
    $root = ts_suite_web_root();
    $items = [];
    $home = ts_suite_http_head('https://topstudiobd.com/', 4);
    $homeOk = $home['status'] >= 200 && $home['status'] < 400;
    $items[] = ts_suite_status_item('Transport', 'HTTPS homepage', $homeOk ? 'pass' : 'critical', $homeOk ? 'HTTPS endpoint returned HTTP ' . $home['status'] . '.' : 'HTTPS check failed: HTTP ' . $home['status'] . '. ' . $home['error']);

    $headers = $home['headers'] ?? [];
    $hsts = isset($headers['strict-transport-security']);
    $items[] = ts_suite_status_item('Headers', 'HSTS', $hsts ? 'pass' : 'warn', $hsts ? 'Strict-Transport-Security is present.' : 'HSTS was not observed on the final HTTPS response. Review hosting configuration before enabling it.');
    $nosniff = strtolower((string)($headers['x-content-type-options'] ?? '')) === 'nosniff';
    $items[] = ts_suite_status_item('Headers', 'MIME sniffing protection', $nosniff ? 'pass' : 'warn', $nosniff ? 'X-Content-Type-Options: nosniff is present.' : 'X-Content-Type-Options: nosniff was not observed.');
    $frame = isset($headers['x-frame-options']) || (isset($headers['content-security-policy']) && stripos((string)$headers['content-security-policy'], 'frame-ancestors') !== false);
    $items[] = ts_suite_status_item('Headers', 'Clickjacking protection', $frame ? 'pass' : 'warn', $frame ? 'X-Frame-Options or CSP frame-ancestors was observed.' : 'No X-Frame-Options or CSP frame-ancestors directive was observed.');
    $referrer = isset($headers['referrer-policy']);
    $items[] = ts_suite_status_item('Headers', 'Referrer Policy', $referrer ? 'pass' : 'warn', $referrer ? 'Referrer-Policy is present.' : 'Referrer-Policy was not observed.');
    $csp = isset($headers['content-security-policy']);
    $items[] = ts_suite_status_item('Headers', 'Content Security Policy', $csp ? 'pass' : 'info', $csp ? 'A Content-Security-Policy header is present.' : 'No enforced CSP was observed. Do not add a restrictive CSP without testing Google Fonts, Unsplash/Pexels images, chat and analytics first.');

    $sensitive = [
        '/.env' => 'Environment file',
        '/.git/config' => 'Git configuration',
        '/error_log' => 'Public error log',
        '/admin/error_log' => 'Admin error log',
        '/backup.zip' => 'Backup archive',
    ];
    foreach ($sensitive as $path => $label) {
        try {
            $probe = ts_suite_http_head('https://topstudiobd.com' . $path, 3);
            $effectivePath = (string)(parse_url((string)$probe['effective_url'], PHP_URL_PATH) ?: '/');
            $isSameResource = rtrim($effectivePath, '/') === rtrim($path, '/');
            $exposed = $probe['status'] >= 200 && $probe['status'] < 300 && $isSameResource;
            $status = $exposed ? 'critical' : (($probe['status'] === 0) ? 'warn' : 'pass');
            $detail = $exposed ? 'The endpoint returned HTTP ' . $probe['status'] . ' without redirecting away. Review and block public access immediately.' : (($probe['status'] === 0) ? 'Could not verify this endpoint: ' . $probe['error'] : 'No direct public access was confirmed (HTTP ' . $probe['status'] . ').');
            $items[] = ts_suite_status_item('Exposure', $label, $status, $detail);
        } catch (Throwable $e) {
            $items[] = ts_suite_status_item('Exposure', $label, 'warn', 'Check could not complete: ' . $e->getMessage());
        }
    }

    $configPath = $root . DIRECTORY_SEPARATOR . 'config.php';
    if (is_file($configPath)) {
        $mode = @fileperms($configPath);
        $worldWritable = $mode !== false && (($mode & 0002) !== 0);
        $items[] = ts_suite_status_item('Files', 'Root config.php permissions', $worldWritable ? 'critical' : 'pass', $worldWritable ? 'config.php is world-writable. Correct its permissions through cPanel.' : 'config.php exists and is not world-writable. Its contents were not read or changed.');
    } else {
        $items[] = ts_suite_status_item('Files', 'Root config.php', 'warn', 'config.php was not found at the expected public_html root.');
    }

    $loginPath = __DIR__ . DIRECTORY_SEPARATOR . 'login.php';
    if (is_file($loginPath) && is_readable($loginPath)) {
        $loginCode = (string)@file_get_contents($loginPath);
        $csrf = stripos($loginCode, 'verify_csrf') !== false;
        $regenerate = stripos($loginCode, 'session_regenerate_id') !== false;
        $status = ($csrf && $regenerate) ? 'pass' : 'warn';
        $items[] = ts_suite_status_item('Admin', 'Login CSRF / session rotation', $status, 'CSRF check: ' . ($csrf ? 'found' : 'not detected') . '; session ID rotation after login: ' . ($regenerate ? 'found' : 'not detected') . '.');
    } else {
        $items[] = ts_suite_status_item('Admin', 'Login source check', 'warn', 'login.php could not be read by the scanner.');
    }

    $cookie = session_get_cookie_params();
    $secureCookie = !empty($cookie['secure']) || filter_var(ini_get('session.cookie_secure'), FILTER_VALIDATE_BOOLEAN);
    $httpOnly = !empty($cookie['httponly']) || filter_var(ini_get('session.cookie_httponly'), FILTER_VALIDATE_BOOLEAN);
    $sameSite = (string)($cookie['samesite'] ?? ini_get('session.cookie_samesite'));
    $items[] = ts_suite_status_item('Admin', 'Session cookie flags', ($secureCookie && $httpOnly && $sameSite !== '') ? 'pass' : 'warn', 'Secure: ' . ($secureCookie ? 'on' : 'not detected') . '; HttpOnly: ' . ($httpOnly ? 'on' : 'not detected') . '; SameSite: ' . ($sameSite !== '' ? $sameSite : 'not detected') . '. Scanner reports runtime settings only; it does not change session configuration.');

    $privateDir = dirname($root) . DIRECTORY_SEPARATOR . 'topstudio_private';
    $privateOk = is_dir($privateDir) && !is_link($privateDir) && realpath($privateDir) !== false && strpos((string)realpath($privateDir), $root . DIRECTORY_SEPARATOR) !== 0;
    $items[] = ts_suite_status_item('Files', 'Private Google OAuth storage', $privateOk ? 'pass' : 'warn', $privateOk ? 'topstudio_private is outside public_html.' : 'Expected private Google OAuth storage was not verified outside public_html.');

    $counts = ['critical' => 0, 'warn' => 0, 'pass' => 0, 'info' => 0];
    foreach ($items as $item) if (isset($counts[$item['status']])) $counts[$item['status']]++;
    $report = ['type' => 'security', 'scanned_at' => gmdate('c'), 'items' => $items, 'counts' => $counts];
    ts_suite_save_report('security', $report);
    ts_suite_audit_event('security_scan', ['critical' => $counts['critical'], 'warn' => $counts['warn'], 'pass' => $counts['pass']]);
    return $report;
}

function ts_suite_health_scan(PDO $pdo): array
{
    if (function_exists('set_time_limit')) @set_time_limit(40);
    $root = ts_suite_web_root();
    $items = [];
    try {
        $pdo->query('SELECT 1')->fetchColumn();
        $items[] = ts_suite_status_item('Database', 'Database connection', 'pass', 'A read-only SELECT 1 query succeeded. No table or record was changed.');
    } catch (Throwable $e) {
        $items[] = ts_suite_status_item('Database', 'Database connection', 'critical', 'Read-only database check failed: ' . substr($e->getMessage(), 0, 180));
    }

    $requiredFiles = ['index.php', 'config.php', 'sitemap.xml', 'robots.txt', 'admin/index.php', 'admin/performance.php'];
    foreach ($requiredFiles as $relative) {
        $exists = is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $items[] = ts_suite_status_item('Files', $relative, $exists ? 'pass' : 'warn', $exists ? 'Required file is present.' : 'Expected file was not found.');
    }

    $directories = ['assets', 'uploads', 'admin'];
    foreach ($directories as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . $relative;
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        $status = !$exists ? 'critical' : ($writable ? 'pass' : 'warn');
        $detail = !$exists ? 'Directory is missing.' : ($writable ? 'Directory exists and is writable by PHP.' : 'Directory exists but is not writable by PHP; some CMS features may need it.');
        $items[] = ts_suite_status_item('Directories', $relative, $status, $detail);
    }

    foreach (['/', '/about.php', '/services.php', '/projects.php', '/concepts.php', '/contact.php', '/sitemap.xml', '/robots.txt'] as $path) {
        try {
            $check = ts_suite_http_head('https://topstudiobd.com' . $path, 3);
            $statusCode = (int)$check['status'];
            $status = ($statusCode >= 200 && $statusCode < 400) ? 'pass' : (($statusCode === 0 || $statusCode >= 500) ? 'critical' : 'warn');
            $detail = 'HTTP ' . $statusCode . ($check['redirects'] ? ' · ' . $check['redirects'] . ' redirect(s)' : '');
            if ($check['error'] !== '') $detail .= ' · ' . $check['error'];
            $items[] = ts_suite_status_item('Public URLs', $path, $status, $detail);
        } catch (Throwable $e) {
            $items[] = ts_suite_status_item('Public URLs', $path, 'warn', $e->getMessage());
        }
    }

    $extensions = ['curl' => extension_loaded('curl'), 'openssl' => extension_loaded('openssl'), 'fileinfo' => extension_loaded('fileinfo'), 'json' => extension_loaded('json')];
    foreach ($extensions as $name => $enabled) {
        $items[] = ts_suite_status_item('PHP', 'Extension: ' . $name, $enabled ? 'pass' : 'warn', $enabled ? 'Enabled.' : 'Not enabled.');
    }
    $zip = extension_loaded('zip');
    $items[] = ts_suite_status_item('PHP', 'ZIP extension', $zip ? 'pass' : 'info', $zip ? 'Enabled; PHP ZIP operations may be available.' : 'Not enabled; use cPanel for full-account backup archives.');

    $free = @disk_free_space($root);
    $items[] = ts_suite_status_item('Hosting', 'Available disk space', is_numeric($free) ? ((float)$free > 104857600 ? 'pass' : 'warn') : 'info', is_numeric($free) ? number_format((float)$free / 1048576, 1) . ' MB free (hosting-reported filesystem space).' : 'Disk free space is unavailable to PHP.');

    $counts = ['critical' => 0, 'warn' => 0, 'pass' => 0, 'info' => 0];
    foreach ($items as $item) if (isset($counts[$item['status']])) $counts[$item['status']]++;
    $report = ['type' => 'health', 'scanned_at' => gmdate('c'), 'items' => $items, 'counts' => $counts];
    ts_suite_save_report('health', $report);
    ts_suite_audit_event('health_scan', ['critical' => $counts['critical'], 'warn' => $counts['warn'], 'pass' => $counts['pass']]);
    return $report;
}
