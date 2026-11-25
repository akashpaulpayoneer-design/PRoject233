<?php
require_once __DIR__ . '/_common.php';
$pdo = db();

$fields = [
    // Existing website identity and content settings.
    'site_title' => ['Website title', 'TOP STUDIO | Interior & Decor'],
    'meta_description' => ['SEO meta description', ''],
    'logo_path' => ['Logo path', 'assets/top-studio-logo-transparent.png'],
    'favicon_path' => ['Favicon path', ''],
    'phone' => ['Phone number', ''],
    'whatsapp' => ['WhatsApp number', ''],
    'email' => ['Public email address', ''],
    'address' => ['Office address', 'Bangladesh'],
    'facebook' => ['Facebook URL', ''],
    'instagram' => ['Instagram URL', ''],
    'youtube' => ['YouTube URL', ''],
    'footer_text' => ['Footer copyright text', '© ' . date('Y') . ' TOP STUDIO. ALL RIGHTS RESERVED.'],

    // Global SEO controls. These are stored in the existing site_settings table.
    'canonical_url' => ['Canonical URL', 'https://topstudiobd.com/'],
    'robots_index' => ['Search indexing', 'index'],
    'robots_follow' => ['Link following', 'follow'],
    'og_title' => ['Open Graph title', ''],
    'og_description' => ['Open Graph description', ''],
    'og_image' => ['Open Graph image path', ''],
    'twitter_card' => ['Twitter card type', 'summary_large_image'],
    'twitter_title' => ['Twitter title', ''],
    'twitter_description' => ['Twitter description', ''],
    'twitter_image' => ['Twitter image path', ''],
    'google_site_verification' => ['Google Search Console verification code', ''],
    'ga4_measurement_id' => ['Google Analytics 4 Measurement ID', ''],
    'ga4_property_id' => ['Google Analytics 4 Property ID', ''],
    'gtm_container_id' => ['Google Tag Manager Container ID', ''],
    'bing_site_verification' => ['Bing Webmaster verification code', ''],
    'google_business_profile_url' => ['Google Business Profile URL', ''],
    'google_maps_url' => ['Google Maps URL', ''],
    'local_business_schema_enabled' => ['Enable LocalBusiness schema', '0'],
    'business_description' => ['Business description for structured data', ''],
    'business_service_area' => ['Business service area', 'Bangladesh'],
];


$stringLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

$validHttpsUrl = static function (string $url): bool {
    if ($url === '') return true;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'https';
};

$validImageReference = static function (string $value): bool {
    if ($value === '') return true;
    if (preg_match('~^https://~i', $value)) {
        return (bool)filter_var($value, FILTER_VALIDATE_URL);
    }
    return (bool)preg_match('~^(?:/|assets/|uploads/)[A-Za-z0-9._/-]+$~', $value)
        && strpos($value, '..') === false;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ts_admin_verify_post();

        $values = [];
        foreach ($fields as $key => $meta) {
            $values[$key] = trim((string)($_POST[$key] ?? $meta[1]));
        }
        $values['local_business_schema_enabled'] = isset($_POST['local_business_schema_enabled']) ? '1' : '0';

        if ($values['site_title'] === '') {
            throw new RuntimeException('Website title is required.');
        }
        if ($stringLength($values['site_title']) > 180) {
            throw new RuntimeException('Website title must be 180 characters or fewer.');
        }
        if ($stringLength($values['meta_description']) > 320) {
            throw new RuntimeException('SEO meta description must be 320 characters or fewer.');
        }
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid public email address.');
        }

        foreach (['facebook', 'instagram', 'youtube', 'google_business_profile_url', 'google_maps_url'] as $key) {
            if ($values[$key] !== '' && !filter_var($values[$key], FILTER_VALIDATE_URL)) {
                throw new RuntimeException(ucfirst($key) . ' must be a valid URL.');
            }
            if ($values[$key] !== '' && !in_array(strtolower((string)parse_url($values[$key], PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new RuntimeException(ucfirst($key) . ' must use HTTP or HTTPS.');
            }
        }

        if (!$validHttpsUrl($values['canonical_url'])) {
            throw new RuntimeException('Canonical URL must be a valid HTTPS URL.');
        }
        if (!in_array($values['robots_index'], ['index', 'noindex'], true)) {
            throw new RuntimeException('Choose a valid search indexing setting.');
        }
        if (!in_array($values['robots_follow'], ['follow', 'nofollow'], true)) {
            throw new RuntimeException('Choose a valid link following setting.');
        }
        if (!in_array($values['twitter_card'], ['summary', 'summary_large_image'], true)) {
            throw new RuntimeException('Choose a valid Twitter card type.');
        }
        foreach (['og_image', 'twitter_image'] as $key) {
            if (!$validImageReference($values[$key])) {
                throw new RuntimeException(ucfirst(str_replace('_', ' ', $key)) . ' must be an HTTPS image URL or a site-relative assets/uploads path.');
            }
        }

        if ($values['google_site_verification'] !== '' && !preg_match('/^[A-Za-z0-9._-]{1,255}$/', $values['google_site_verification'])) {
            throw new RuntimeException('Google verification code contains unsupported characters. Paste only the content value from Google.');
        }
        if ($values['bing_site_verification'] !== '' && !preg_match('/^[A-Za-z0-9._-]{1,255}$/', $values['bing_site_verification'])) {
            throw new RuntimeException('Bing verification code contains unsupported characters.');
        }
        if ($values['ga4_measurement_id'] !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/i', $values['ga4_measurement_id'])) {
            throw new RuntimeException('GA4 Measurement ID should look like G-XXXXXXXXXX.');
        }
        if ($values['gtm_container_id'] !== '' && !preg_match('/^GTM-[A-Z0-9]{4,20}$/i', $values['gtm_container_id'])) {
            throw new RuntimeException('Google Tag Manager ID should look like GTM-XXXXXXX.');
        }
        if ($values['ga4_property_id'] !== '' && !preg_match('/^[0-9]{5,20}$/', $values['ga4_property_id'])) {
            throw new RuntimeException('GA4 Property ID must be the numeric Property ID from Google Analytics, not the G- Measurement ID.');
        }
        if ($values['local_business_schema_enabled'] === '1') {
            if ($values['address'] === '' || strcasecmp($values['address'], 'Bangladesh') === 0) {
                throw new RuntimeException('To enable LocalBusiness schema, enter the complete verified business address first.');
            }
            if ($values['phone'] === '') {
                throw new RuntimeException('To enable LocalBusiness schema, enter the verified business phone number first.');
            }
        }

        $logo = ts_admin_upload_image('logo_upload', 'brand');
        if ($logo !== null) {
            $values['logo_path'] = 'uploads/' . $logo;
        }
        $ogImage = ts_admin_upload_image('og_image_upload', 'og');
        if ($ogImage !== null) {
            $values['og_image'] = 'uploads/' . $ogImage;
        }
        $twitterImage = ts_admin_upload_image('twitter_image_upload', 'twitter');
        if ($twitterImage !== null) {
            $values['twitter_image'] = 'uploads/' . $twitterImage;
        }

        $pdo->beginTransaction();
        $exists = $pdo->prepare('SELECT setting_key FROM site_settings WHERE setting_key = ? LIMIT 1');
        $update = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
        $insert = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($values as $key => $value) {
            $exists->execute([$key]);
            if ($exists->fetchColumn()) {
                $update->execute([$value, $key]);
            } else {
                $insert->execute([$key, $value]);
            }
        }
        $pdo->commit();

        ts_admin_flash('success', 'Website and SEO settings saved successfully.');
        ts_admin_redirect('settings.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('TOP STUDIO settings error: ' . $e->getMessage());
        ts_admin_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Settings could not be saved. Please check the server error log.');
        ts_admin_redirect('settings.php');
    }
}

$values = [];
foreach ($fields as $key => $meta) {
    $values[$key] = ts_admin_setting($pdo, $key, $meta[1]);
}

$logoPreview = ts_admin_image_url($values['logo_path']);
$ogPreview = ts_admin_image_url($values['og_image']);
$twitterPreview = ts_admin_image_url($values['twitter_image']);
$siteBase = 'https://topstudiobd.com/';
ts_admin_page_start('Website Settings', 'settings', 'Manage your public brand identity, contact details, social channels and search optimization.');
?>
<form class="adm-panel adm-form adm-settings-form" method="post" enctype="multipart/form-data">
<?php ts_admin_csrf_field(); ?>

<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">01 / BRAND IDENTITY</p><h2>Website identity</h2><p class="adm-help">These details identify TOP STUDIO across the website and browser results.</p></div></div>
  <label>Website title<input name="site_title" required maxlength="180" value="<?=e($values['site_title'])?>"></label>
  <label>SEO meta description<textarea name="meta_description" rows="3" maxlength="320"><?=e($values['meta_description'])?></textarea><small>Write a useful, unique summary for the homepage. Avoid keyword stuffing.</small></label>
  <div class="adm-form-row"><label>Logo path<input name="logo_path" value="<?=e($values['logo_path'])?>"><small>Current saved logo path.</small></label><label>Favicon path<input name="favicon_path" value="<?=e($values['favicon_path'])?>"></label></div>
  <label>Upload a replacement logo<input type="file" name="logo_upload" accept="image/jpeg,image/png,image/webp"><small>Optional. Uploading a file saves it in /uploads and updates the logo path.</small></label>
  <?php if ($logoPreview): ?><div class="adm-current-image"><img src="<?=e($logoPreview)?>" alt="Current website logo"><span>Current logo preview</span></div><?php endif; ?>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">02 / CLIENT CONTACT</p><h2>Contact information</h2></div></div>
  <div class="adm-form-row"><label>Phone number<input name="phone" value="<?=e($values['phone'])?>" placeholder="+880..."></label><label>WhatsApp number<input name="whatsapp" value="<?=e($values['whatsapp'])?>" placeholder="8801XXXXXXXXX"></label></div>
  <div class="adm-form-row"><label>Public email<input type="email" name="email" value="<?=e($values['email'])?>"></label><label>Office address<input name="address" value="<?=e($values['address'])?>"></label></div>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">03 / SOCIAL PRESENCE</p><h2>Social media links</h2></div></div>
  <label>Facebook URL<input type="url" name="facebook" value="<?=e($values['facebook'])?>" placeholder="https://facebook.com/..."></label>
  <label>Instagram URL<input type="url" name="instagram" value="<?=e($values['instagram'])?>" placeholder="https://instagram.com/..."></label>
  <label>YouTube URL<input type="url" name="youtube" value="<?=e($values['youtube'])?>" placeholder="https://youtube.com/..."></label>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">04 / SEARCH ENGINE BASICS</p><h2>SEO settings</h2><p class="adm-help">These global settings currently apply to the public homepage. Individual service/project page metadata can be added when those pages have their own URLs.</p></div></div>
  <label>Preferred canonical URL<input type="url" name="canonical_url" value="<?=e($values['canonical_url'])?>" placeholder="https://topstudiobd.com/"><small>Use the preferred HTTPS homepage URL. Do not enter a different page URL here.</small></label>
  <div class="adm-form-row">
    <label>Search indexing<select name="robots_index"><option value="index" <?=($values['robots_index']==='index'?'selected':'')?>>Index — allow search indexing</option><option value="noindex" <?=($values['robots_index']==='noindex'?'selected':'')?>>Noindex — keep page out of search</option></select></label>
    <label>Link following<select name="robots_follow"><option value="follow" <?=($values['robots_follow']==='follow'?'selected':'')?>>Follow — allow link discovery</option><option value="nofollow" <?=($values['robots_follow']==='nofollow'?'selected':'')?>>Nofollow</option></select></label>
  </div>
  <small>Recommended default for the public homepage: Index + Follow. Do not select Noindex unless you intentionally want the homepage excluded from search.</small>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">05 / SOCIAL SEARCH PREVIEW</p><h2>Open Graph &amp; Twitter Cards</h2><p class="adm-help">Used when the website is shared on social platforms. Blank title/description fields fall back to the website title and meta description.</p></div></div>
  <label>Open Graph title<input name="og_title" maxlength="180" value="<?=e($values['og_title'])?>" placeholder="Leave blank to use Website Title"></label>
  <label>Open Graph description<textarea name="og_description" rows="3" maxlength="320"><?=e($values['og_description'])?></textarea><small>Leave blank to use SEO meta description.</small></label>
  <label>Open Graph image path or HTTPS URL<input name="og_image" value="<?=e($values['og_image'])?>" placeholder="uploads/social-share-image.jpg"><small>Recommended landscape share artwork. Use a site-relative path or HTTPS image URL.</small></label>
  <label>Upload Open Graph image<input type="file" name="og_image_upload" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WEBP. Existing image remains unchanged unless you enter a new path or upload a replacement.</small></label>
  <?php if ($ogPreview): ?><div class="adm-current-image"><img src="<?=e($ogPreview)?>" alt="Current Open Graph image preview"><span>Current Open Graph image</span></div><?php endif; ?>
  <label>Twitter / X card type<select name="twitter_card"><option value="summary_large_image" <?=($values['twitter_card']==='summary_large_image'?'selected':'')?>>Summary with large image</option><option value="summary" <?=($values['twitter_card']==='summary'?'selected':'')?>>Summary</option></select></label>
  <label>Twitter / X title<input name="twitter_title" maxlength="180" value="<?=e($values['twitter_title'])?>" placeholder="Leave blank to use Open Graph title"></label>
  <label>Twitter / X description<textarea name="twitter_description" rows="3" maxlength="320"><?=e($values['twitter_description'])?></textarea><small>Leave blank to use Open Graph description.</small></label>
  <label>Twitter / X image path or HTTPS URL<input name="twitter_image" value="<?=e($values['twitter_image'])?>" placeholder="Leave blank to use Open Graph image"></label>
  <label>Upload Twitter / X image<input type="file" name="twitter_image_upload" accept="image/jpeg,image/png,image/webp"></label>
  <?php if ($twitterPreview): ?><div class="adm-current-image"><img src="<?=e($twitterPreview)?>" alt="Current Twitter image preview"><span>Current Twitter image</span></div><?php endif; ?>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">06 / GOOGLE TOOLS</p><h2>Search Console &amp; Analytics</h2><p class="adm-help">Enter only IDs/codes issued by Google. GA4 dashboard reports also require the numeric Property ID and a separate read-only OAuth connection.</p></div></div>
  <label>Google Search Console verification code<input name="google_site_verification" value="<?=e($values['google_site_verification'])?>" maxlength="255" placeholder="Paste the content value only"><small>From the HTML tag verification method: paste only the value inside the content="..." attribute.</small></label>
  <label>Google Analytics 4 Measurement ID<input name="ga4_measurement_id" value="<?=e($values['ga4_measurement_id'])?>" maxlength="30" placeholder="G-XXXXXXXXXX"><small>Keep this blank when GA4 is loaded through your GTM container to avoid duplicate tracking.</small></label>
  <label>Google Analytics 4 Property ID <span class="adm-help">(for dashboard reports)</span><input name="ga4_property_id" value="<?=e($values['ga4_property_id'])?>" inputmode="numeric" maxlength="20" placeholder="123456789"><small>Enter the numeric Property ID from Google Analytics → Admin → Property details. This is not the G-XXXXXXXXXX Measurement ID.</small></label>
  <label>Google Tag Manager Container ID <span class="adm-help">(optional)</span><input name="gtm_container_id" value="<?=e($values['gtm_container_id'])?>" maxlength="30" placeholder="GTM-XXXXXXX"><small>If you use GTM to load GA4, leave the direct GA4 field empty to avoid duplicate tracking.</small></label>
  <label>Bing Webmaster verification code <span class="adm-help">(optional)</span><input name="bing_site_verification" value="<?=e($values['bing_site_verification'])?>" maxlength="255" placeholder="Paste the verification content value"></label>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">07 / STRUCTURED BUSINESS DATA</p><h2>Organization &amp; LocalBusiness schema</h2><p class="adm-help">Organization markup is generated automatically. LocalBusiness markup is optional and must only be enabled when the address and phone below are real, current business details.</p></div></div>
  <label>Business description<textarea name="business_description" rows="3" maxlength="500"><?=e($values['business_description'])?></textarea><small>Leave blank to use the homepage meta description.</small></label>
  <label>Primary service area<input name="business_service_area" value="<?=e($values['business_service_area'])?>" maxlength="180" placeholder="Bangladesh"></label>
  <label>Google Business Profile URL<input type="url" name="google_business_profile_url" value="<?=e($values['google_business_profile_url'])?>" placeholder="https://..."></label>
  <label>Google Maps URL <span class="adm-help">(optional)</span><input type="url" name="google_maps_url" value="<?=e($values['google_maps_url'])?>" placeholder="https://maps.google.com/..."></label>
  <label class="adm-check"><input type="checkbox" name="local_business_schema_enabled" value="1" <?=($values['local_business_schema_enabled']==='1'?'checked':'')?>><span>Enable LocalBusiness structured data using the verified contact details above</span></label>
  <small>Enabling this requires a complete physical business address and verified phone number. No reviews, ratings, coordinates or opening hours are invented.</small>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">08 / CRAWLING &amp; DISCOVERY</p><h2>Sitemap &amp; robots.txt</h2><p class="adm-help">These are root-level files in public_html, not editable text boxes. Keep them available to search crawlers and update the sitemap when separate service/project pages are published.</p></div></div>
  <label>Sitemap URL<input value="https://topstudiobd.com/sitemap.xml" readonly></label>
  <label>Robots.txt URL<input value="https://topstudiobd.com/robots.txt" readonly></label>
  <small>After uploading the files, submit the sitemap URL in Google Search Console. The current sitemap includes the homepage only.</small>
</section>

<div class="adm-divider"></div>
<section class="adm-settings-section">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">09 / FOOTER</p><h2>Copyright line</h2></div></div>
  <label>Footer text<input name="footer_text" value="<?=e($values['footer_text'])?>"></label>
</section>

<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit">Save website &amp; SEO settings</button><a class="adm-button adm-button-outline" href="../index.php" target="_blank" rel="noopener">Preview website ↗</a></div>
</form>
<?php ts_admin_page_end(); ?>