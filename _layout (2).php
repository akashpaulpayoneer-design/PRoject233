<?php
/** Shared TOP STUDIO luxury admin layout. */
function ts_admin_page_start(string $title, string $active, string $description = ''): void {
    $adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
    $nav = [
        'overview' => ['Overview', 'index.php', '⌂', 'WORKSPACE'],
        'control-center' => ['Control Center', 'control-center.php', '✦', 'WORKSPACE'],
        'projects' => ['Projects', 'projects.php', '▧', 'PORTFOLIO'],
        'services' => ['Services', 'services.php', '◇', 'PORTFOLIO'],
        'hero' => ['Homepage Content', 'content-manager.php?section=hero', '✳', 'WEBSITE CONTENT'],
        'about' => ['About Studio', 'about.php', '◈', 'WEBSITE CONTENT'],
        'expertise' => ['Expertise', 'content-manager.php?section=expertise', '✧', 'WEBSITE CONTENT'],
        'concepts' => ['Concept Gallery', 'content-manager.php?section=concepts', '▤', 'WEBSITE CONTENT'],
        'process' => ['Our Process', 'content-manager.php?section=process', '↗', 'WEBSITE CONTENT'],
        'testimonials' => ['Testimonials', 'content-manager.php?section=testimonials', '❝', 'WEBSITE CONTENT'],
        'contact-content' => ['Contact Section', 'content-manager.php?section=contact', '✉', 'WEBSITE CONTENT'],
        'sections' => ['Section Visibility', 'sections.php', '◉', 'WEBSITE CONTENT'],
        'inquiries' => ['Inquiries', 'inquiries.php', '▱', 'CLIENT RELATIONS'],
        'live-chat' => ['Live Chat', 'live-chat.php', '◉', 'CLIENT RELATIONS'],
        'settings' => ['Site Settings', 'settings.php', '⚙', 'SYSTEM'],
        'profile' => ['Admin Security', 'profile.php', '♙', 'SYSTEM'],
    ];
    $groups = ['WORKSPACE', 'PORTFOLIO', 'WEBSITE CONTENT', 'CLIENT RELATIONS', 'SYSTEM'];
    $flash = ts_admin_flash('get');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=e($title)?> | TOP STUDIO Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin.css?v=20261031-system1">
</head>
<body class="admin-body">
<div class="adm-app">
  <aside class="adm-sidebar" id="adm-sidebar">
    <a class="adm-brand" href="index.php" aria-label="TOP STUDIO Admin home">
      <span class="adm-brand-mark">TS</span>
      <span class="adm-brand-copy"><strong>TOP STUDIO</strong><small>PRIVATE ADMINISTRATION</small></span>
    </a>
    <div class="adm-side-label">CONTROL CENTRE</div>
    <nav class="adm-nav">
      <?php foreach ($groups as $group): ?>
        <div class="adm-nav-group">
          <div class="adm-nav-heading"><?=e($group)?></div>
          <?php foreach ($nav as $key => $item): if ($item[3] !== $group) continue; ?>
            <a class="adm-nav-link <?=$active === $key ? 'is-active' : ''?>" href="<?=$item[1]?>">
              <span class="adm-nav-icon"><?=e($item[2])?></span><span><?=e($item[0])?></span>
              <?php if ($key === 'inquiries'): ?><span class="adm-nav-dot" aria-hidden="true"></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="adm-sidebar-bottom">
      <a class="adm-public-link" href="../index.php" target="_blank" rel="noopener">↗&nbsp; View live website</a>
      <form method="post" action="logout.php" class="adm-logout-form">
        <?php ts_admin_csrf_field(); ?>
        <button type="submit" class="adm-logout">⇥&nbsp; Sign out</button>
      </form>
      <div class="adm-side-foot">TOP STUDIO · INTERIOR &amp; DECOR</div>
    </div>
  </aside>

  <div class="adm-main-wrap">
    <header class="adm-topbar">
      <button class="adm-menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" onclick="tsToggleAdminNav()">☰</button>
      <div class="adm-topbar-context"><span>TOP STUDIO</span><i>/</i><strong><?=e($title)?></strong></div>
      <div class="adm-user"><span class="adm-user-avatar"><?=e(strtoupper(substr($adminName, 0, 1)))?></span><span><b><?=e($adminName)?></b><small>Administrator</small></span></div>
    </header>

    <main class="adm-main">
      <div class="adm-page-heading">
        <div><p class="adm-eyebrow">TOP STUDIO / MANAGEMENT</p><h1><?=e($title)?></h1><?php if ($description !== ''): ?><p class="adm-page-description"><?=e($description)?></p><?php endif; ?></div>
        <div class="adm-page-date"><?=e(date('D, d M Y'))?></div>
      </div>
      <?php if ($flash): ?><div class="adm-alert <?=($flash['type'] ?? '') === 'error' ? 'is-error' : 'is-success'?>"><?=e($flash['message'] ?? '')?></div><?php endif; ?>
<?php
}

function ts_admin_page_end(): void {
    ?>
    </main>
    <footer class="adm-footer"><span>© <?=date('Y')?> TOP STUDIO</span><span>Thoughtfully designed. Carefully managed.</span></footer>
  </div>
</div>
<script>
function tsToggleAdminNav(){
  const side=document.getElementById('adm-sidebar');
  const btn=document.querySelector('.adm-menu-toggle');
  if(!side)return;
  const open=side.classList.toggle('is-open');
  if(btn)btn.setAttribute('aria-expanded',open?'true':'false');
}
document.addEventListener('click',function(e){
  const side=document.getElementById('adm-sidebar');
  const btn=document.querySelector('.adm-menu-toggle');
  if(side&&side.classList.contains('is-open')&&!side.contains(e.target)&&btn&&!btn.contains(e.target)){
    side.classList.remove('is-open');btn.setAttribute('aria-expanded','false');
  }
});
</script>
</body>
</html>
<?php
}
