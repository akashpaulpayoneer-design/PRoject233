<?php
require_once __DIR__ . '/_common.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ts_admin_verify_post();
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id < 1) throw new RuntimeException('Invalid project selection.');
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
            $stmt->execute([$id]);
            ts_admin_flash('success', $stmt->rowCount() ? 'Project record deleted. Its uploaded image was left untouched.' : 'Project not found.');
            ts_admin_redirect('projects.php');
        }

        if ($action === 'toggle') {
            if ($id < 1) throw new RuntimeException('Invalid project selection.');
            $stmt = $pdo->prepare('UPDATE projects SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $stmt->execute([$id]);
            ts_admin_flash('success', 'Project visibility updated.');
            ts_admin_redirect('projects.php');
        }

        if ($action !== 'save') throw new RuntimeException('Unsupported action.');
        $title = trim((string)($_POST['title'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $published = isset($_POST['is_published']) ? 1 : 0;
        if ($title === '' || $category === '') throw new RuntimeException('Project title and category are required.');
        if (strlen($title) > 180 || strlen($category) > 100) throw new RuntimeException('Title or category is too long.');

        $newImage = ts_admin_upload_image('image', 'project');
        if ($id > 0) {
            $oldStmt = $pdo->prepare('SELECT image FROM projects WHERE id = ? LIMIT 1');
            $oldStmt->execute([$id]);
            $existing = $oldStmt->fetch();
            if (!$existing) throw new RuntimeException('Project not found.');
            $image = $newImage ?? $existing['image'];
            $stmt = $pdo->prepare('UPDATE projects SET title = ?, category = ?, description = ?, image = ?, is_published = ? WHERE id = ?');
            $stmt->execute([$title, $category, $description, $image, $published, $id]);
            ts_admin_flash('success', 'Project updated successfully.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO projects (title, category, description, image, is_published) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $category, $description, $newImage, $published]);
            ts_admin_flash('success', 'New project added to your portfolio.');
        }
        ts_admin_redirect('projects.php');
    } catch (Throwable $e) {
        error_log('TOP STUDIO project management error: ' . $e->getMessage());
        ts_admin_flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The project could not be saved. Please check the fields and try again.');
        $redirect = 'projects.php' . (!empty($_POST['id']) ? '?edit=' . (int)$_POST['id'] : '');
        ts_admin_redirect($redirect);
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = ['id' => 0, 'title' => '', 'category' => '', 'description' => '', 'image' => '', 'is_published' => 1];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: $editing;
}
$rows = $pdo->query('SELECT * FROM projects ORDER BY created_at DESC')->fetchAll();
$publishedCount = count(array_filter($rows, static fn($r) => (int)$r['is_published'] === 1));
ts_admin_page_start('Project Portfolio', 'projects', 'Create, edit and curate the projects presented as completed TOP STUDIO work.');
?>
<div class="adm-toolbar-row"><div class="adm-inline-stats"><span><b><?=count($rows)?></b> TOTAL PROJECTS</span><span><b><?=$publishedCount?></b> PUBLISHED</span></div><a class="adm-button adm-button-outline" href="../index.php#projects" target="_blank" rel="noopener">Preview portfolio ↗</a></div>
<div class="adm-content-grid adm-project-layout">
  <section class="adm-panel adm-form-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow"><?= $editId ? 'EDIT PORTFOLIO ENTRY' : 'NEW PORTFOLIO ENTRY' ?></p><h2><?= $editId ? 'Refine this project' : 'Add a project' ?></h2></div><?php if ($editId): ?><a class="adm-text-link" href="projects.php">＋ New</a><?php endif; ?></div>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$editing['id']?>">
      <label>Project title <span>*</span><input name="title" maxlength="180" required value="<?=e($editing['title'])?>" placeholder="e.g. The Meridian Residence"></label>
      <label>Category <span>*</span><input name="category" maxlength="100" required value="<?=e($editing['category'])?>" placeholder="Residential / Commercial / Hospitality"></label>
      <label>Project description<textarea name="description" rows="5" placeholder="Describe the design direction, materials and spatial experience."><?=e($editing['description'])?></textarea></label>
      <label>Cover image <input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WEBP · Maximum 5 MB. Landscape images work best.</small></label>
      <?php $editImage = ts_admin_image_url($editing['image'] ?? ''); if ($editImage): ?><div class="adm-current-image"><img src="<?=e($editImage)?>" alt="Current project image"><span>Current cover image · Leave empty to keep it</span></div><?php endif; ?>
      <label class="adm-check"><input type="checkbox" name="is_published" value="1" <?=((int)$editing['is_published'] === 1 ? 'checked' : '')?>> <span>Publish this project on the website</span></label>
      <div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit"><?=$editId ? 'Save project changes' : '＋ Add to portfolio'?></button><?php if ($editId): ?><a class="adm-button adm-button-quiet" href="projects.php">Cancel</a><?php endif; ?></div>
    </form>
  </section>

  <section class="adm-panel adm-table-panel">
    <div class="adm-panel-heading"><div><p class="adm-eyebrow">YOUR WORK</p><h2>All projects <span class="adm-count-pill"><?=count($rows)?></span></h2></div></div>
    <?php if (!$rows): ?><div class="adm-empty-state"><span>▧</span><b>No portfolio entries yet</b><p>Add your first genuine completed project when its imagery and project details are ready.</p></div>
    <?php else: ?><div class="adm-project-table-wrap"><table class="adm-data-table"><thead><tr><th>PROJECT</th><th>CATEGORY</th><th>VISIBILITY</th><th>MANAGE</th></tr></thead><tbody>
      <?php foreach ($rows as $row): $src = ts_admin_image_url($row['image'] ?? ''); ?><tr>
        <td><div class="adm-table-project"><?php if ($src): ?><img src="<?=e($src)?>" alt=""><?php else: ?><span class="adm-thumb-placeholder">TS</span><?php endif; ?><span><b><?=e($row['title'])?></b><small><?=e(date('d M Y', strtotime((string)$row['created_at'])))?></small></span></div></td>
        <td><?=e($row['category'])?></td><td><?=ts_admin_status_badge((int)$row['is_published'] === 1)?></td>
        <td><div class="adm-row-actions"><a class="adm-action-link" href="projects.php?edit=<?=(int)$row['id']?>">Edit</a><form method="post" onsubmit="return confirm('Change project visibility?')"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="adm-action-link" type="submit"><?=((int)$row['is_published'] === 1 ? 'Unpublish' : 'Publish')?></button></form><form method="post" onsubmit="return confirm('Delete this project record? The uploaded image file will be kept.')"><?php ts_admin_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="adm-action-link is-danger" type="submit">Delete</button></form></div></td>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
</div>
<?php ts_admin_page_end(); ?>
