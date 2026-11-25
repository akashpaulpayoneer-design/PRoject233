<?php
require_once __DIR__ . '/_common.php';
$pdo = db();
$stats = ['projects' => 0, 'published' => 0, 'inquiries' => 0, 'new' => 0, 'services' => 0, 'content' => 0];
$recentInquiries = [];
$recentProjects = [];
try {
    $stats['projects'] = (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $stats['published'] = (int)$pdo->query('SELECT COUNT(*) FROM projects WHERE is_published = 1')->fetchColumn();
    $stats['inquiries'] = (int)$pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
    $stats['new'] = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $stats['services'] = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE section_key = 'services'")->fetchColumn();
    $stats['content'] = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE section_key <> 'services'")->fetchColumn();
    $recentInquiries = $pdo->query('SELECT id,name,email,phone,message,status,created_at FROM inquiries ORDER BY created_at DESC LIMIT 6')->fetchAll();
    $recentProjects = $pdo->query('SELECT id,title,category,image,is_published,created_at FROM projects ORDER BY created_at DESC LIMIT 4')->fetchAll();
} catch (Throwable $e) {
    error_log('TOP STUDIO admin dashboard query error: ' . $e->getMessage());
    ts_admin_flash('error', 'Some dashboard statistics could not be loaded. Please check the database connection.');
}

// Local SEO configuration checks. Search/Analytics performance data is not
// fabricated: a verification tag or GA4 ID alone does not grant reporting API access.
$seo = [
    'title' => ts_admin_setting($pdo, 'site_title', ''),
    'description' => ts_admin_setting($pdo, 'meta_description', ''),
    'canonical' => ts_admin_setting($pdo, 'canonical_url', 'https://topstudiobd.com/'),
    'robots_index' => ts_admin_setting($pdo, 'robots_index', 'index'),
    'robots_follow' => ts_admin_setting($pdo, 'robots_follow', 'follow'),
    'og_title' => ts_admin_setting($pdo, 'og_title', ''),
    'og_description' => ts_admin_setting($pdo, 'og_description', ''),
    'og_image' => ts_admin_setting($pdo, 'og_image', ''),
    'gsc' => ts_admin_setting($pdo, 'google_site_verification', ''),
    'ga4' => ts_admin_setting($pdo, 'ga4_measurement_id', ''),
    'ga4_property_id' => ts_admin_setting($pdo, 'ga4_property_id', ''),
    'gtm' => ts_admin_setting($pdo, 'gtm_container_id', ''),
    'bing' => ts_admin_setting($pdo, 'bing_site_verification', ''),
    'local_schema' => ts_admin_setting($pdo, 'local_business_schema_enabled', '0'),
];
$rootPath = dirname(__DIR__);
$sitemapExists = is_file($rootPath . '/sitemap.xml');
$robotsExists = is_file($rootPath . '/robots.txt');
$canonicalValid = (bool)filter_var($seo['canonical'], FILTER_VALIDATE_URL)
    && strtolower((string)parse_url($seo['canonical'], PHP_URL_SCHEME)) === 'https';

$seoStringLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};
$seoChecks = [
    ['Homepage SEO title', trim($seo['title']) !== '', trim($seo['title']) !== '' ? $seoStringLength($seo['title']) . ' characters saved' : 'Add a website title'],
    ['Homepage meta description', trim($seo['description']) !== '', trim($seo['description']) !== '' ? $seoStringLength($seo['description']) . ' characters saved' : 'Add a meta description'],
    ['HTTPS canonical URL', $canonicalValid, $canonicalValid ? $seo['canonical'] : 'Check the canonical URL'],
    ['Indexing directive', $seo['robots_index'] === 'index', $seo['robots_index'] === 'index' ? 'Index enabled' : 'Homepage is set to noindex'],
    ['Link following directive', $seo['robots_follow'] === 'follow', $seo['robots_follow'] === 'follow' ? 'Follow enabled' : 'Homepage is set to nofollow'],
    ['Open Graph share image', trim($seo['og_image']) !== '', trim($seo['og_image']) !== '' ? 'Share image path is configured' : 'Add an Open Graph share image in Website Settings'],
    ['XML sitemap file', $sitemapExists, $sitemapExists ? 'sitemap.xml found in public_html' : 'sitemap.xml not found in public_html'],
    ['robots.txt file', $robotsExists, $robotsExists ? 'robots.txt found in public_html' : 'robots.txt not found in public_html'],
];
$seoPassed = 0;
foreach ($seoChecks as $check) if ($check[1]) $seoPassed++;
$seoTotal = count($seoChecks);

$gscConfigured = trim($seo['gsc']) !== '';
$analyticsConfigured = trim($seo['ga4']) !== '' || trim($seo['gtm']) !== '';
$seoLocalSchemaEnabled = $seo['local_schema'] === '1';

// Compact Performance Monitor summary. Failure to create/read its optional
// tables must never prevent the existing admin Overview from loading.
$performanceMonitorReady = false;
$performanceLatest = ['desktop' => null, 'mobile' => null];
try {
    require_once __DIR__ . '/performance-lib.php';
    // Overview is read-only: do not run DDL/table creation on every Admin visit.
    if (ts_perf_schema_exists($pdo)) {
        $performanceLatest['desktop'] = ts_perf_latest($pdo, 'desktop');
        $performanceLatest['mobile'] = ts_perf_latest($pdo, 'mobile');
        $performanceMonitorReady = true;
    }
} catch (Throwable $e) {
    error_log('TOP STUDIO Performance Monitor overview is unavailable: ' . $e->getMessage());
}

require_once __DIR__ . '/google-api.php';
$googleReports = ts_google_collect_reports($pdo, false, false); // cache-only: never wait for Google APIs during normal Dashboard load
$googleConnected = !empty($googleReports['connected']);
$gscReportOk = !empty($googleReports['gsc']['ok']);
$ga4ReportOk = !empty($googleReports['ga4']['ok']);
$gscIndexedDisplay = $gscReportOk && (int)($googleReports['gsc']['checked_urls'] ?? 0) > 0
    ? number_format((int)$googleReports['gsc']['indexed_pages']) . ' / ' . number_format((int)$googleReports['gsc']['checked_urls'])
    : '—';
$gscClicksDisplay = $gscReportOk ? number_format((int)$googleReports['gsc']['clicks']) : '—';
$gscImpressionsDisplay = $gscReportOk ? number_format((int)$googleReports['gsc']['impressions']) : '—';
$gscCtrDisplay = $gscReportOk ? number_format(((float)$googleReports['gsc']['ctr']) * 100, 2) . '%' : '—';
$gscPositionDisplay = $gscReportOk && $googleReports['gsc']['position'] !== null ? number_format((float)$googleReports['gsc']['position'], 1) : '—';
$ga4VisitorsDisplay = $ga4ReportOk ? number_format((int)$googleReports['ga4']['active_users']) : '—';
$ga4SessionsDisplay = $ga4ReportOk ? number_format((int)$googleReports['ga4']['sessions']) : '—';
$ga4PageViewsDisplay = $ga4ReportOk ? number_format((int)$googleReports['ga4']['page_views']) : '—';
$googleLastUpdated = !empty($googleReports['fetched_at']) ? date('d M Y, h:i A', (int)$googleReports['fetched_at']) : '';
$googleConfigAvailable = ts_google_has_private_config();

ts_admin_page_start('Dashboard', 'overview', 'A clear view of your portfolio, website content and client conversations.');
?>
<section class="adm-welcome-panel">
  <div><p class="adm-eyebrow">WELCOME BACK</p><h2>Good to see you, <em><?=e($_SESSION['admin_name'] ?? 'Admin')?>.</em></h2><p>Your studio workspace is ready. Keep every detail of the TOP STUDIO experience considered.</p></div>
  <a class="adm-button adm-button-gold" href="projects.php">＋&nbsp; Add a project</a>
</section>

<section class="adm-stat-grid">
  <a class="adm-stat-card" href="projects.php"><span class="adm-stat-icon">▧</span><span class="adm-stat-label">TOTAL PROJECTS</span><strong><?=number_format($stats['projects'])?></strong><small><?=number_format($stats['published'])?> currently published</small></a>
  <a class="adm-stat-card" href="inquiries.php"><span class="adm-stat-icon">✉</span><span class="adm-stat-label">CLIENT INQUIRIES</span><strong><?=number_format($stats['inquiries'])?></strong><small><?=number_format($stats['new'])?> awaiting attention</small></a>
  <a class="adm-stat-card" href="services.php"><span class="adm-stat-icon">◇</span><span class="adm-stat-label">SERVICES</span><strong><?=number_format($stats['services'])?></strong><small>Service cards in CMS</small></a>
  <a class="adm-stat-card" href="content-manager.php?section=hero"><span class="adm-stat-icon">✳</span><span class="adm-stat-label">CONTENT ITEMS</span><strong><?=number_format($stats['content'])?></strong><small>Homepage content records</small></a>
</section>

<!-- Small Performance & Boost summary; detailed tools live on a separate page. -->
<section class="adm-panel adm-panel-wide" style="margin-bottom:24px">
  <div class="adm-panel-heading">
    <div><p class="adm-eyebrow">WEBSITE HEALTH / SAFE OPTIMIZATION</p><h2>Performance &amp; Boost Center</h2><p class="adm-help">Compact PC and Mobile PageSpeed history with backup-first, reversible static optimization.</p></div>
    <a class="adm-text-link" href="performance.php">Open Performance Monitor ↗</a>
  </div>
  <section class="adm-stat-grid">
    <?php foreach (['desktop' => 'PC / DESKTOP', 'mobile' => 'MOBILE'] as $perfKey => $perfLabel): $perfRow = $performanceLatest[$perfKey] ?? null; ?>
      <a class="adm-stat-card" href="performance.php">
        <span class="adm-stat-icon"><?= $perfKey === 'desktop' ? '▣' : '▯' ?></span>
        <span class="adm-stat-label"><?=e($perfLabel)?> PERFORMANCE</span>
        <strong><?= $perfRow && $perfRow['performance_score'] !== null ? (int)$perfRow['performance_score'] . '/100' : '—' ?></strong>
        <small><?= $perfRow ? 'Last test: ' . e(date('d M, h:i A', strtotime((string)$perfRow['created_at']))) : 'Run the first PageSpeed test' ?></small>
      </a>
    <?php endforeach; ?>
    <a class="adm-stat-card" href="performance.php#boost"><span class="adm-stat-icon">↗</span><span class="adm-stat-label">SAFE WEBSITE BOOST</span><strong>Backup first</strong><small>Review and apply only approved static optimization</small></a>
    <a class="adm-stat-card" href="performance.php#history"><span class="adm-stat-icon">◷</span><span class="adm-stat-label">TEST HISTORY</span><strong>Monitor</strong><small>Compare past PC and Mobile results</small></a>
  </section>
  <?php if (!$performanceMonitorReady): ?><p class="adm-help">Performance Monitor storage is not ready yet. Open the monitor page to check database permissions. Existing dashboard functions are unaffected.</p><?php endif; ?>
</section>

<!-- SEO report is displayed here on the existing Overview page; no new menu is added. -->
<section class="adm-panel adm-panel-wide" style="margin-bottom:24px">
  <div class="adm-panel-heading">
    <div><p class="adm-eyebrow">SEARCH VISIBILITY / WEBSITE HEALTH</p><h2>SEO Overview</h2><p class="adm-help">Google Search Console and Analytics reporting for TOP STUDIO. Data is refreshed on demand and cached briefly.</p></div>
    <a class="adm-text-link" href="settings.php">Manage SEO settings ↗</a>
  </div>
  <section class="adm-stat-grid">
    <div class="adm-stat-card"><span class="adm-stat-icon">✓</span><span class="adm-stat-label">SEO SETUP CHECKS</span><strong><?=number_format($seoPassed)?> / <?=number_format($seoTotal)?></strong><small>Local configuration checks passed</small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">⌕</span><span class="adm-stat-label">INDEXED SITEMAP URLS</span><strong><?=e($gscIndexedDisplay)?></strong><small><?= $gscReportOk ? 'Google index status · checked sitemap URLs' : 'Search Console API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">◉</span><span class="adm-stat-label">SEARCH CLICKS</span><strong><?=e($gscClicksDisplay)?></strong><small><?= $gscReportOk ? 'Last 28 complete days' : 'Search Console API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">◉</span><span class="adm-stat-label">IMPRESSIONS</span><strong><?=e($gscImpressionsDisplay)?></strong><small><?= $gscReportOk ? 'Last 28 complete days' : 'Search Console API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">% </span><span class="adm-stat-label">AVERAGE CTR</span><strong><?=e($gscCtrDisplay)?></strong><small><?= $gscReportOk ? 'Search result click-through rate' : 'Search Console API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">↗</span><span class="adm-stat-label">AVERAGE POSITION</span><strong><?=e($gscPositionDisplay)?></strong><small><?= $gscReportOk ? 'Search result average position' : 'Search Console API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">◫</span><span class="adm-stat-label">GA4 ACTIVE USERS</span><strong><?=e($ga4VisitorsDisplay)?></strong><small><?= $ga4ReportOk ? 'Last 28 complete days' : 'Analytics reporting API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">↗</span><span class="adm-stat-label">GA4 SESSIONS</span><strong><?=e($ga4SessionsDisplay)?></strong><small><?= $ga4ReportOk ? 'Last 28 complete days' : 'Analytics reporting API not connected' ?></small></div>
    <div class="adm-stat-card"><span class="adm-stat-icon">▤</span><span class="adm-stat-label">GA4 PAGE VIEWS</span><strong><?=e($ga4PageViewsDisplay)?></strong><small><?= $ga4ReportOk ? 'Last 28 complete days' : 'Analytics reporting API not connected' ?></small></div>
  </section>

  <div class="adm-content-grid" style="margin-top:22px">
    <section class="adm-panel adm-panel-wide">
      <div class="adm-panel-heading"><div><p class="adm-eyebrow">TECHNICAL CHECKLIST</p><h2>Homepage SEO checks</h2></div></div>
      <div class="adm-list-table"><table><thead><tr><th>CHECK</th><th>RESULT</th><th>DETAIL</th></tr></thead><tbody>
      <?php foreach ($seoChecks as $check): ?>
        <tr><td><b><?=e($check[0])?></b></td><td><?=ts_admin_status_badge((bool)$check[1], 'Ready', 'Needs attention')?></td><td><?=e($check[2])?></td></tr>
      <?php endforeach; ?>
      <tr><td><b>Google Search Console ownership / API</b></td><td><?=ts_admin_status_badge($gscReportOk, 'API connected', $googleConnected ? 'API needs attention' : 'Not connected')?></td><td><?= $gscReportOk ? 'Search Console report received successfully.' : 'Connect the Google account with read-only Search Console permission.' ?></td></tr>
      <tr><td><b>Bing Webmaster tag</b></td><td><?=ts_admin_status_badge(trim($seo['bing']) !== '', 'Code saved', 'Not configured')?></td><td><?=trim($seo['bing']) !== '' ? 'Verification code is saved; finish verification in Bing Webmaster Tools.' : 'Optional. Add the Bing verification code in Website Settings.'?></td></tr>
      <tr><td><b>GA4 reporting API</b></td><td><?=ts_admin_status_badge($ga4ReportOk, 'API connected', $googleConnected ? 'Needs configuration' : 'Not connected')?></td><td><?= $ga4ReportOk ? 'GA4 reporting data received successfully.' : (trim($seo['ga4_property_id']) === '' ? 'Add the numeric GA4 Property ID in Website Settings.' : 'Connect the Google account and verify GA4 read-only access.') ?></td></tr>
      <tr><td><b>Organization structured data</b></td><td><?=ts_admin_status_badge(true, 'Enabled', 'Not enabled')?></td><td>Public homepage generates Organization and WebSite JSON-LD.</td></tr>
      <tr><td><b>LocalBusiness structured data</b></td><td><?=ts_admin_status_badge($seoLocalSchemaEnabled, 'Enabled', 'Disabled')?></td><td><?= $seoLocalSchemaEnabled ? 'Configured to use your saved business address and phone; validate the live markup.' : 'Disabled until verified physical business details are ready.' ?></td></tr>
      </tbody></table></div>
    </section>
    <section class="adm-panel">
      <div class="adm-panel-heading"><div><p class="adm-eyebrow">SEARCH DATA CONNECTIONS</p><h2>Reporting status</h2></div></div>
      <div class="adm-list-table"><table><thead><tr><th>DATA SOURCE</th><th>STATUS</th><th>NEXT STEP</th></tr></thead><tbody>
        <tr><td><b>Google Search Console</b></td><td><?=ts_admin_status_badge($gscReportOk, 'Connected', $googleConnected ? 'API error' : 'Not connected')?></td><td><?= $gscReportOk ? 'Read-only Search Console report available.' : 'Authorize the Google account with Search Console read-only access.' ?></td></tr>
        <tr><td><b>Google Analytics 4</b></td><td><?=ts_admin_status_badge($ga4ReportOk, 'Connected', $googleConnected ? 'Needs setup' : 'Not connected')?></td><td><?= $ga4ReportOk ? 'Read-only GA4 report available.' : (trim($seo['ga4_property_id']) === '' ? 'Add the numeric GA4 Property ID in Website Settings.' : 'Authorize the Google account with Analytics read-only access.') ?></td></tr>
        <tr><td><b>Core Web Vitals</b></td><td><?=ts_admin_status_badge(false, 'Measured', 'Not measured')?></td><td>Run the PageSpeed Insights test for current field/lab metrics.</td></tr>
      </tbody></table></div>
      <div class="adm-form-actions">
        <?php if (!$googleConnected): ?>
          <?php if ($googleConfigAvailable): ?><a class="adm-button adm-button-gold" href="google-connect.php">Connect Google Account ↗</a><?php else: ?><span class="adm-help">Install the private Google OAuth config outside public_html before connecting.</span><?php endif; ?>
        <?php else: ?>
          <form method="post" action="google-sync.php" style="display:inline"><?php ts_admin_csrf_field(); ?><button class="adm-button adm-button-gold" type="submit">Refresh Google Reports</button></form>
          <form method="post" action="google-disconnect.php" style="display:inline" onsubmit="return confirm('Disconnect Google reporting and remove the stored token?')"><?php ts_admin_csrf_field(); ?><button class="adm-button adm-button-quiet" type="submit">Disconnect</button></form>
        <?php endif; ?>
        <a class="adm-button adm-button-outline" href="google-api-check.php">Check API setup</a>
        <a class="adm-button adm-button-outline" href="https://search.google.com/search-console?resource_id=sc-domain%3Atopstudiobd.com" target="_blank" rel="noopener">Open Search Console ↗</a>
        <a class="adm-button adm-button-outline" href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Ftopstudiobd.com%2F" target="_blank" rel="noopener">Test PageSpeed ↗</a>
      </div>
      <?php if ($googleLastUpdated !== ''): ?><small class="adm-help">Last refreshed: <?=e($googleLastUpdated)?> · Google reports are cached for 30 minutes.</small><?php endif; ?>
      <?php if (!$googleConnected && !empty($googleReports['errors']['connection'])): ?><p class="adm-help"><?=e($googleReports['errors']['connection'])?></p><?php endif; ?>
      <?php if ($googleConnected && !empty($googleReports['errors'])): ?><p class="adm-help">Some reports need attention: <?=e(implode(' · ', array_map('strval', $googleReports['errors'])))?></p><?php endif; ?>
    </section>
  </div>
</section>

<div class="adm-content-grid">
  <section class="adm-panel adm-panel-wide">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">CLIENT RELATIONS</p><h2>Recent inquiries</h2></div><a class="adm-text-link" href="inquiries.php">View all ↗</a></div>
    <?php if (!$recentInquiries): ?><div class="adm-empty-state"><span>✉</span><b>No inquiries yet</b><p>New client messages submitted through the website will appear here.</p></div>
    <?php else: ?>
      <div class="adm-list-table"><table><thead><tr><th>CLIENT</th><th>MESSAGE</th><th>RECEIVED</th><th>STATUS</th></tr></thead><tbody>
      <?php foreach ($recentInquiries as $row): ?><tr><td><b><?=e($row['name'])?></b><small><?=e($row['email'])?></small></td><td class="adm-message-cell"><?=e((function_exists('mb_strimwidth') ? mb_strimwidth((string)$row['message'], 0, 88, '…') : substr((string)$row['message'], 0, 88)))?></td><td><?=e(date('d M Y', strtotime((string)$row['created_at'])))?></td><td><?=ts_admin_status_badge(($row['status'] ?? 'New') === 'New', 'New', 'Contacted')?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="adm-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">QUICK ACCESS</p><h2>Studio controls</h2></div></div>
    <div class="adm-quick-links">
      <a href="settings.php"><span>⚙</span><b>Website settings</b><small>Identity, contact, SEO and socials</small><i>↗</i></a>
      <a href="sections.php"><span>◉</span><b>Section visibility</b><small>Choose what appears on the homepage</small><i>↗</i></a>
      <a href="about.php"><span>◈</span><b>About the studio</b><small>Story, image and studio principles</small><i>↗</i></a>
      <a href="profile.php"><span>♙</span><b>Admin security</b><small>Change your administrator password</small><i>↗</i></a>
    </div>
  </section>

  <section class="adm-panel adm-panel-wide">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">PORTFOLIO</p><h2>Recently added projects</h2></div><a class="adm-text-link" href="projects.php">Manage portfolio ↗</a></div>
    <?php if (!$recentProjects): ?><div class="adm-empty-state"><span>▧</span><b>Your portfolio starts here</b><p>Add a project to build a curated record of the spaces you create.</p><a class="adm-button adm-button-outline" href="projects.php">Add first project</a></div>
    <?php else: ?><div class="adm-project-strip">
      <?php foreach ($recentProjects as $project): ?><a class="adm-project-mini" href="projects.php?edit=<?=(int)$project['id']?>">
        <?php $thumb = ts_admin_image_url($project['image'] ?? ''); ?>
        <?php if ($thumb): ?><img src="<?=e($thumb)?>" alt=""><?php else: ?><span class="adm-project-placeholder">TS</span><?php endif; ?>
        <span><b><?=e($project['title'])?></b><small><?=e($project['category'])?> · <?=((int)$project['is_published'] === 1 ? 'Published' : 'Draft')?></small></span>
      </a><?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</div>
<?php ts_admin_page_end(); ?>