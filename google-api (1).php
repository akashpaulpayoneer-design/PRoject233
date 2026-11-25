<?php
/**
 * TOP STUDIO Google reporting API helper.
 * Requires admin/_common.php to be loaded first (admin session + CSRF helpers).
 * OAuth credentials and tokens are stored outside public_html.
 */

function ts_google_private_dir(): string
{
    // For /home/ACCOUNT/public_html/admin this resolves to /home/ACCOUNT/topstudio_private.
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'topstudio_private';
}

function ts_google_private_config_path(): string
{
    return ts_google_private_dir() . DIRECTORY_SEPARATOR . 'google_oauth_config.php';
}

function ts_google_config(): array
{
    static $config = null;
    if (is_array($config)) return $config;

    $path = ts_google_private_config_path();
    if (!is_file($path)) {
        throw new RuntimeException('Private Google OAuth config is missing. Create /topstudio_private/google_oauth_config.php outside public_html.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) throw new RuntimeException('Google OAuth config must return a PHP array.');

    $clientId = trim((string)($loaded['client_id'] ?? ''));
    $clientSecret = trim((string)($loaded['client_secret'] ?? ''));
    $redirectUri = trim((string)($loaded['redirect_uri'] ?? ''));
    $searchProperty = trim((string)($loaded['search_console_property'] ?? 'sc-domain:topstudiobd.com'));

    if ($clientId === '' || strpos($clientId, '.apps.googleusercontent.com') === false) {
        throw new RuntimeException('Google OAuth Client ID is missing or invalid in the private config.');
    }
    if ($clientSecret === '' || $clientSecret === 'PASTE_CLIENT_SECRET_HERE') {
        throw new RuntimeException('Google OAuth Client Secret has not been added to the private config.');
    }
    $expectedRedirect = 'https://topstudiobd.com/admin/google-oauth-callback.php';
    if ($redirectUri !== $expectedRedirect) {
        throw new RuntimeException('Redirect URI in the private config must exactly match ' . $expectedRedirect);
    }
    if ($searchProperty !== 'sc-domain:topstudiobd.com' && $searchProperty !== 'https://topstudiobd.com/') {
        throw new RuntimeException('Search Console property must match the verified TOP STUDIO property.');
    }

    $config = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'search_console_property' => $searchProperty,
        'private_dir' => ts_google_private_dir(),
        'token_file' => ts_google_private_dir() . DIRECTORY_SEPARATOR . 'google_oauth_tokens.enc',
        'cache_file' => ts_google_private_dir() . DIRECTORY_SEPARATOR . 'google_report_cache.json',
    ];
    return $config;
}

function ts_google_has_private_config(): bool
{
    return is_file(ts_google_private_config_path());
}

function ts_google_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ts_google_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $method = strtoupper($method);
    $responseBody = '';
    $status = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Unable to initialize cURL.');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        if ($result === false) $error = (string)curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseBody = is_string($result) ? $result : '';
        curl_close($ch);
    } else {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('PHP cURL is not enabled and allow_url_fopen is disabled. Enable the PHP cURL extension in cPanel.');
        }
        $headerText = implode("\r\n", $headers);
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headerText,
            'content' => $body ?? '',
            'timeout' => 35,
            'ignore_errors' => true,
            'protocol_version' => 1.1,
        ], 'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);
        $result = @file_get_contents($url, false, $context);
        $responseBody = is_string($result) ? $result : '';
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $headerLine, $match)) $status = (int)$match[1];
            }
        }
        if ($result === false) {
            $last = error_get_last();
            $error = is_array($last) ? (string)($last['message'] ?? 'HTTP request failed.') : 'HTTP request failed.';
        }
    }

    return ['status' => $status, 'body' => $responseBody, 'error' => $error];
}

function ts_google_private_write(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        throw new RuntimeException('Cannot create the private token directory. Create /topstudio_private outside public_html and make it writable by PHP.');
    }
    @chmod($dir, 0700);
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(5));
    $written = @file_put_contents($tmp, $contents, LOCK_EX);
    if ($written === false) throw new RuntimeException('Cannot write the private Google token/cache file. Check folder permissions.');
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot safely replace the private Google token/cache file.');
    }
    @chmod($path, 0600);
}

function ts_google_token_key(array $config): string
{
    return hash('sha256', 'TOP-STUDIO-GOOGLE-TOKEN|' . $config['client_secret'] . '|' . $config['client_id'], true);
}

function ts_google_read_tokens(array $config): array
{
    $path = $config['token_file'];
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') return [];
    $box = json_decode($raw, true);
    if (!is_array($box) || empty($box['iv']) || empty($box['tag']) || !isset($box['data'])) {
        error_log('TOP STUDIO Google token store format is invalid.');
        return [];
    }
    $plain = openssl_decrypt(
        base64_decode((string)$box['data'], true) ?: '',
        'aes-256-gcm',
        ts_google_token_key($config),
        OPENSSL_RAW_DATA,
        base64_decode((string)$box['iv'], true) ?: '',
        base64_decode((string)$box['tag'], true) ?: ''
    );
    if (!is_string($plain)) {
        error_log('TOP STUDIO Google token store could not be decrypted. Reconnect Google if the OAuth Client Secret was rotated.');
        return [];
    }
    $tokens = json_decode($plain, true);
    return is_array($tokens) ? $tokens : [];
}

function ts_google_write_tokens(array $config, array $tokens): void
{
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('PHP OpenSSL extension is required for secure token storage.');
    $iv = random_bytes(12);
    $tag = '';
    $plain = json_encode($tokens, JSON_UNESCAPED_SLASHES);
    if (!is_string($plain)) throw new RuntimeException('Could not encode Google OAuth tokens.');
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', ts_google_token_key($config), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher)) throw new RuntimeException('Could not encrypt Google OAuth tokens.');
    $box = json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'data' => base64_encode($cipher),
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($box)) throw new RuntimeException('Could not encode encrypted Google token store.');
    ts_google_private_write($config['token_file'], $box);
}

function ts_google_token_response(array $response): array
{
    $decoded = json_decode((string)$response['body'], true);
    if (!is_array($decoded)) $decoded = [];
    if ($response['status'] < 200 || $response['status'] >= 300 || empty($decoded['access_token'])) {
        $description = (string)($decoded['error_description'] ?? $decoded['error'] ?? $response['error'] ?? 'Google token request failed.');
        throw new RuntimeException('Google OAuth token error: ' . substr($description, 0, 240));
    }
    return $decoded;
}

function ts_google_refresh_access_token(array $config, array $tokens): array
{
    if (empty($tokens['refresh_token'])) throw new RuntimeException('Google is not connected or the refresh token is missing. Connect the Google account again.');
    $body = http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $tokens['refresh_token'],
        'grant_type' => 'refresh_token',
    ]);
    $response = ts_google_http_request('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], $body);
    $fresh = ts_google_token_response($response);
    $tokens['access_token'] = (string)$fresh['access_token'];
    $tokens['expires_at'] = time() + (int)($fresh['expires_in'] ?? 3600);
    $tokens['token_type'] = (string)($fresh['token_type'] ?? 'Bearer');
    $tokens['updated_at'] = gmdate('c');
    ts_google_write_tokens($config, $tokens);
    return $tokens;
}

function ts_google_access_token(array $config, bool $forceRefresh = false): string
{
    $tokens = ts_google_read_tokens($config);
    if (!$forceRefresh && !empty($tokens['access_token']) && (int)($tokens['expires_at'] ?? 0) > time() + 120) {
        return (string)$tokens['access_token'];
    }
    $tokens = ts_google_refresh_access_token($config, $tokens);
    return (string)$tokens['access_token'];
}

function ts_google_api_json(array $config, string $method, string $url, ?array $payload = null): array
{
    $attempt = 0;
    do {
        $accessToken = ts_google_access_token($config, $attempt > 0);
        $headers = ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'];
        $body = null;
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) throw new RuntimeException('Could not encode Google API request.');
        }
        $response = ts_google_http_request($method, $url, $headers, $body);
        if ($response['status'] === 401 && $attempt === 0) {
            $attempt++;
            continue;
        }
        $decoded = json_decode((string)$response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($decoded)) {
            $apiMessage = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['error_description'] ?? '') : '';
            if ($apiMessage === '') $apiMessage = (string)($response['error'] ?: 'Google API request failed.');
            throw new RuntimeException('Google API error (' . (int)$response['status'] . '): ' . substr($apiMessage, 0, 240));
        }
        return $decoded;
    } while ($attempt <= 1);
    throw new RuntimeException('Google API authorization failed. Reconnect the Google account.');
}

function ts_google_sitemap_urls(): array
{
    $sitemap = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'sitemap.xml';
    if (!is_file($sitemap)) return [];
    $xml = @file_get_contents($sitemap);
    if (!is_string($xml) || $xml === '') return [];
    if (!preg_match_all('~<loc>\s*(.*?)\s*</loc>~is', $xml, $matches)) return [];
    $urls = [];
    foreach ($matches[1] as $candidate) {
        $url = trim(html_entity_decode(strip_tags((string)$candidate), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
            $urls[] = $url;
        }
    }
    return array_values(array_unique($urls));
}

function ts_google_fetch_search_console(array $config): array
{
    $property = (string)$config['search_console_property'];
    $timezone = new DateTimeZone('Asia/Dhaka');
    $end = (new DateTimeImmutable('today', $timezone))->modify('-3 days');
    $start = $end->modify('-27 days');
    $site = rawurlencode($property);
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . $site . '/searchAnalytics/query';
    $data = ts_google_api_json($config, 'POST', $url, [
        'startDate' => $start->format('Y-m-d'),
        'endDate' => $end->format('Y-m-d'),
        'type' => 'web',
        'dataState' => 'final',
        'rowLimit' => 1,
    ]);
    $row = $data['rows'][0] ?? [];
    $urls = array_slice(ts_google_sitemap_urls(), 0, 15);
    $indexed = 0;
    $checked = 0;
    $inspectionErrors = [];
    foreach ($urls as $pageUrl) {
        try {
            $inspection = ts_google_api_json($config, 'POST', 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $pageUrl,
                'siteUrl' => $property,
                'languageCode' => 'en-US',
            ]);
            $status = (string)($inspection['inspectionResult']['indexStatusResult']['verdict'] ?? 'VERDICT_UNSPECIFIED');
            $checked++;
            if ($status === 'PASS') $indexed++;
        } catch (Throwable $e) {
            $inspectionErrors[] = $e->getMessage();
        }
    }

    return [
        'ok' => true,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'clicks' => (int)round((float)($row['clicks'] ?? 0)),
        'impressions' => (int)round((float)($row['impressions'] ?? 0)),
        'ctr' => (float)($row['ctr'] ?? 0),
        'position' => isset($row['position']) ? (float)$row['position'] : null,
        'indexed_pages' => $indexed,
        'checked_urls' => $checked,
        'sitemap_urls' => count($urls),
        'inspection_note' => $inspectionErrors ? substr(implode(' | ', $inspectionErrors), 0, 220) : '',
    ];
}

function ts_google_fetch_ga4(array $config, string $propertyId): array
{
    if (!preg_match('/^[0-9]{5,20}$/', $propertyId)) {
        throw new RuntimeException('Enter the numeric GA4 Property ID in Website Settings. The G-XXXXXXXXXX Measurement ID is not the Property ID.');
    }
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($propertyId) . ':runReport';
    $data = ts_google_api_json($config, 'POST', $url, [
        'dateRanges' => [['startDate' => '28daysAgo', 'endDate' => 'yesterday']],
        'metrics' => [
            ['name' => 'activeUsers'],
            ['name' => 'sessions'],
            ['name' => 'screenPageViews'],
        ],
    ]);
    $values = $data['rows'][0]['metricValues'] ?? [];
    return [
        'ok' => true,
        'active_users' => (int)($values[0]['value'] ?? 0),
        'sessions' => (int)($values[1]['value'] ?? 0),
        'page_views' => (int)($values[2]['value'] ?? 0),
        'date_range' => 'Last 28 complete days (through yesterday)',
    ];
}

function ts_google_read_cache(array $config): array
{
    if (!is_file($config['cache_file'])) return [];
    $raw = @file_get_contents($config['cache_file']);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function ts_google_collect_reports(PDO $pdo, bool $forceRefresh = false, bool $allowNetwork = true): array
{
    $empty = [
        'connected' => false,
        'gsc' => ['ok' => false],
        'ga4' => ['ok' => false],
        'errors' => [],
        'fetched_at' => null,
    ];
    try {
        $config = ts_google_config();
        $tokens = ts_google_read_tokens($config);
        if (empty($tokens['refresh_token'])) return $empty;

        $cache = ts_google_read_cache($config);
        $cacheFresh = !empty($cache['fetched_at']) && (time() - (int)$cache['fetched_at']) < 1800;
        if (!$forceRefresh && $cacheFresh) {
            $cache['connected'] = empty($cache['auth_expired']) && !empty($tokens['refresh_token']);
            $cache['cache_stale'] = false;
            return $cache;
        }
        // The Admin Overview must never make serial live Google API calls.
        // It reads the last cached report; Refresh Google Reports remains the explicit sync action.
        if (!$allowNetwork && !$forceRefresh) {
            if ($cache) {
                $cache['connected'] = empty($cache['auth_expired']) && !empty($tokens['refresh_token']);
                $cache['cache_stale'] = true;
                return $cache;
            }
            $empty['connected'] = !empty($tokens['refresh_token']);
            $empty['cache_stale'] = true;
            $empty['cache_message'] = 'No cached Google report yet. Use Refresh Google Reports to fetch data.';
            return $empty;
        }

        $propertyId = ts_admin_setting($pdo, 'ga4_property_id', '');
        $result = [
            'connected' => true,
            'gsc' => ['ok' => false],
            'ga4' => ['ok' => false],
            'errors' => [],
            'fetched_at' => time(),
            'cache_stale' => false,
        ];
        try {
            $result['gsc'] = ts_google_fetch_search_console($config);
        } catch (Throwable $e) {
            $result['errors']['gsc'] = $e->getMessage();
            error_log('TOP STUDIO Search Console API error: ' . $e->getMessage());
        }
        if (trim($propertyId) !== '') {
            try {
                $result['ga4'] = ts_google_fetch_ga4($config, trim($propertyId));
            } catch (Throwable $e) {
                $result['errors']['ga4'] = $e->getMessage();
                error_log('TOP STUDIO GA4 Data API error: ' . $e->getMessage());
            }
        } else {
            $result['errors']['ga4'] = 'Add the numeric GA4 Property ID in Website Settings → Google Tools.';
        }
        $result['auth_expired'] = false;
        foreach ($result['errors'] as $apiError) {
            if (stripos((string)$apiError, 'invalid_grant') !== false || stripos((string)$apiError, 'refresh token is missing') !== false) {
                $result['auth_expired'] = true;
                $result['connected'] = false;
                break;
            }
        }
        $cacheJson = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($cacheJson)) throw new RuntimeException('Could not encode Google report cache.');
        ts_google_private_write($config['cache_file'], $cacheJson);
        return $result;
    } catch (Throwable $e) {
        $empty['errors']['connection'] = $e->getMessage();
        return $empty;
    }
}

function ts_google_delete_tokens_and_cache(array $config): void
{
    $tokens = ts_google_read_tokens($config);
    if (!empty($tokens['refresh_token'])) {
        $body = http_build_query(['token' => (string)$tokens['refresh_token']]);
        try {
            ts_google_http_request('POST', 'https://oauth2.googleapis.com/revoke', ['Content-Type: application/x-www-form-urlencoded'], $body);
        } catch (Throwable $e) {
            error_log('TOP STUDIO Google token revoke request failed: ' . $e->getMessage());
        }
    }
    if (is_file($config['token_file'])) @unlink($config['token_file']);
    if (is_file($config['cache_file'])) @unlink($config['cache_file']);
}
