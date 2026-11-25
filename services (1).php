<?php
require_once __DIR__ . '/_common.php';
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ts_admin_verify_post();
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM content_items WHERE id = ? AND section_key = 'services'");
            $stmt->execute([$id]);
            ts_admin_flash('success', $stmt->rowCount() ? 'Service removed from the CMS. Existing image files were kept.' : 'Service not found.');
            ts_admin_redirect('services.php');
        }
        if ($action === 'toggle') {
            $stmt = $pdo->prepare("UPDATE content_items SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END WHERE id = ? AND section_key = 'services'");
            $stmt->execute([$id]);
            ts_admin_flash('success', 'Service visibility updated.');
            ts_admin_redirect('services.php');
        }
        if ($action !== 'save') throw new RuntimeException('Unsupported action.');
        $title = trim((string)($_POST['title'] ?? ''));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $link = trim((string)($_POST['link'] ?? ''));
        $button = trim((string)($_POST['button_text'] ?? ''));
        $order = (int)($_POST['sort_order'] ?? 0);
        $published = isset($_POST['is_published']) ? 1 : 0;
        if ($title === '') throw new RuntimeException('Service title is required.');
        if ($link !== '' && !preg_match('~^(https?://|/)~i', $link)) throw new RuntimeException('Button link must begin with http://, https:// or /.');
        $newImage = ts_admin_upload_image('image', 'service');
        if ($id > 0) {
            $q = $pdo->prepare("SELECT image FROM content_items WHERE id = ? AND section_key = 'services' LIMIT 1");
            $q->execute([$id]); $old = $q->fetch();
            if (!$old) throw new RuntimeException('Service not found.');
            $image = $newImage ?? $old['image'];
            $q = $pdo->prepare("UPDATE content_items SET title=?, subtitle=?, description=?, image=?, link=?, button_text=?, sort_order=?, is_published=? WHERE id=? AND section_key='services'");
            $q->execute([$title, $subtitle, $description, $image, $link, $button, $order, $published, $id]);
            ts_admin_flash('success', 'Service updated successfully.');
        } else {
            $q = $pdo->prepare("INSERT INTO content_items (section_key,title,subtitle,description,image,link,button_text,sort_order,is_published) VALUES ('services',?,?,?,?,?,?,?,?)");
            $q->execute([$title, $subtitle, $description, $newImage, $link, $button, $order, $published]);
            ts_admin_flash('success', 'New service added successfully.');
        }
        ts_admin_redirect('services.php');
    } catch (Throwable $e) {
        error_log('TOP STUDIO service management error: ' . $e->getMessage());
        ts_admin_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The service could not be saved. Please try again.');
        ts_admin_redirect('services.php' . (!empty($_POST['id']) ? '?edit=' . (int)$_POST['id'] : ''));
    }
}
$editId = (int)($_GET['edit'] ?? 0);
$editing = ['id'=>0,'title'=>'','subtitle'=>'','description'=>'','image'=>'','link'=>'','button_text'=>'','sort_order'=>0,'is_published'=>1];
if ($editId > 0) {
    $q=$pdo->prepare("SELECT * FROM content_items WHERE id=? AND section_key='services' LIMIT 1"); $q->execute([$editId]); $editing=$q->fetch() ?: $editing;
}
$q=$pdo->query("SELECT * FROM content_items WHERE section_key='services' ORDER BY sort_order ASC,id ASC"); $rows=$q->fetchAll();
$publishedCount=count(array_filter($rows,static fn($r)=>(int)$r['is_published']===1));
ts_admin_page_start('Services', 'services', 'Manage the service cards that introduce your studio’s areas of expertise.');
?>
<div class="adm-toolbar-row"><div class="adm-inline-stats"><span><b><?=count($rows)?></b> TOTAL SERVICES</span><span><b><?=$publishedCount?></b> PUBLISHED</span></div><a class="adm-text-link" href="../index.php#services" target="_blank" rel="noopener">Preview on website ↗</a></div>
<div class="adm-content-grid adm-project-layout">
<section class="adm-panel adm-form-panel"><div class="adm-panel-heading"><div><p class="adm-eyebrow"><?=$editId?'EDIT SERVICE':'SERVICE EDITOR'?></p><h2><?=$editId?'Refine a service':'Add a service'?></h2></div><?php if($editId):?><a class="adm-text-link" href="services.php">＋ New</a><?php endif;?></div>
<form class="adm-form" method="post" enctype="multipart/form-data"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$editing['id']?>">
<label>Service title <span>*</span><input name="title" required value="<?=e($editing['title'])?>" placeholder="Living Room Design"></label>
<label>Subtitle<input name="subtitle" value="<?=e($editing['subtitle'])?>" placeholder="Residential / Commercial"></label>
<label>Description<textarea name="description" rows="5"><?=e($editing['description'])?></textarea></label>
<label>Service image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WEBP · Maximum 5 MB.</small></label>
<?php $img=ts_admin_image_url($editing['image']);if($img):?><div class="adm-current-image"><img src="<?=e($img)?>" alt="Current service image"><span>Current image · Upload a new one to replace it</span></div><?php endif;?>
<div class="adm-form-row"><label>Button link<input name="link" value="<?=e($editing['link'])?>" placeholder="#contact"></label><label>Button text<input name="button_text" value="<?=e($editing['button_text'])?>" placeholder="Explore service"></label></div>
<label>Display order<input type="number" name="sort_order" value="<?=(int)$editing['sort_order']?>"></label>
<label class="adm-check"><input type="checkbox" name="is_published" value="1" <?=((int)$editing['is_published']===1?'checked':'')?>><span>Publish this service on the homepage</span></label>
<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit"><?=$editId?'Save service':'＋ Add service'?></button><?php if($editId):?><a class="adm-button adm-button-quiet" href="services.php">Cancel</a><?php endif;?></div>
</form></section>
<section class="adm-panel adm-table-panel"><div class="adm-panel-heading"><div><p class="adm-eyebrow">SERVICE LIBRARY</p><h2>All services <span class="adm-count-pill"><?=count($rows)?></span></h2></div></div>
<?php if(!$rows):?><div class="adm-empty-state"><span>◇</span><b>No services added</b><p>Add your services here. The homepage will keep its built-in examples until your first published CMS service is available.</p></div><?php else:?><div class="adm-project-table-wrap"><table class="adm-data-table"><thead><tr><th>SERVICE</th><th>ORDER</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><div class="adm-table-project"><?php $src=ts_admin_image_url($r['image']);if($src):?><img src="<?=e($src)?>" alt=""><?php else:?><span class="adm-thumb-placeholder">TS</span><?php endif;?><span><b><?=e($r['title'])?></b><small><?=e($r['subtitle'])?></small></span></div></td><td><?=(int)$r['sort_order']?></td><td><?=ts_admin_status_badge((int)$r['is_published']===1)?></td><td><div class="adm-row-actions"><a class="adm-action-link" href="services.php?edit=<?=(int)$r['id']?>">Edit</a><form method="post" onsubmit="return confirm('Change service visibility?')"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="adm-action-link" type="submit"><?=((int)$r['is_published']===1?'Unpublish':'Publish')?></button></form><form method="post" onsubmit="return confirm('Delete this service entry? Its image file will be kept.')"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="adm-action-link is-danger" type="submit">Delete</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
</div>
<?php ts_admin_page_end(); ?>
