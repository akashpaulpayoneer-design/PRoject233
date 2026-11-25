<?php
/**
 * TOP STUDIO Admin bootstrap and shared helpers.
 * Requires the existing root config.php. No database schema changes are made.
 */
require_once __DIR__ . '/../config.php';
require_admin();

function ts_admin_redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function ts_admin_flash(string $type, ?string $message = null): ?array {
    if ($message !== null) {
        $_SESSION['ts_admin_flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['ts_admin_flash'] ?? null;
    unset($_SESSION['ts_admin_flash']);
    return is_array($flash) ? $flash : null;
}

function ts_admin_csrf_field(): void {
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function ts_admin_verify_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verify_csrf();
}

function ts_admin_upload_image(string $field, string $prefix = 'cms'): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name']) || (int)$file['size'] <= 0) {
        throw new RuntimeException('Invalid uploaded file.');
    }
    if ((int)$file['size'] > (defined('UPLOAD_MAX_BYTES') ? UPLOAD_MAX_BYTES : 5242880)) {
        throw new RuntimeException('Image size must not exceed 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Only valid JPG, PNG or WEBP images are allowed.');
    }

    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('The uploads folder could not be created.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('The uploads folder is not writable.');
    }

    $prefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'cms';
    $filename = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return $filename;
}

function ts_admin_image_url(?string $image): string {
    $image = trim((string)$image);
    if ($image === '') return '';
    if (preg_match('~^https?://~i', $image)) return $image;
    if (strpos($image, '/') === 0) return $image;
    if (strpos($image, 'assets/') === 0) return '../' . $image;
    if (strpos($image, 'uploads/') === 0) return '../' . $image;
    return '../uploads/' . rawurlencode(basename($image));
}

function ts_admin_status_badge(bool $active, string $yes = 'Published', string $no = 'Draft'): string {
    return '<span class="adm-status ' . ($active ? 'is-live' : 'is-muted') . '">' . e($active ? $yes : $no) . '</span>';
}

function ts_admin_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

require_once __DIR__ . '/_layout.php';
