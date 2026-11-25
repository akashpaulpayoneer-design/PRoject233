<?php
require_once __DIR__ . '/_common.php';
$pdo=db();
$sections=[
 'hero'=>['Homepage Hero','Opening statement and primary call to action.'],
 'services'=>['Services','The five service categories or service cards.'],
 'about'=>['About Studio','Your studio story, image and principles.'],
 'expertise'=>['Expertise','Areas of design knowledge and capability.'],
 'projects'=>['Project Portfolio','Published completed projects from the portfolio.'],
 'concepts'=>['Design Inspiration','Illustrative concepts and visual references.'],
 'process'=>['Our Process','The steps followed from discovery to design.'],
 'testimonials'=>['Testimonials','Client feedback and endorsements.'],
 'contact'=>['Contact Section','The project inquiry form and contact details.'],
];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  ts_admin_verify_post();$pdo->beginTransaction();
  foreach($sections as $key=>$meta){$enabled=isset($_POST['sections'][$key])?1:0;$q=$pdo->prepare('SELECT section_key FROM page_sections WHERE section_key=? LIMIT 1');$q->execute([$key]);if($q->fetchColumn()){$s=$pdo->prepare('UPDATE page_sections SET is_enabled=? WHERE section_key=?');$s->execute([$enabled,$key]);}else{$s=$pdo->prepare('INSERT INTO page_sections(section_key,is_enabled) VALUES(?,?)');$s->execute([$key,$enabled]);}}
  $pdo->commit();ts_admin_flash('success','Homepage section visibility saved.');ts_admin_redirect('sections.php');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('TOP STUDIO section visibility error: '.$e->getMessage());ts_admin_flash('error','Section visibility could not be saved. Please try again.');ts_admin_redirect('sections.php');}
}
$current=[];$rows=$pdo->query('SELECT section_key,is_enabled FROM page_sections')->fetchAll();foreach($rows as $r)$current[$r['section_key']]=(int)$r['is_enabled'];
ts_admin_page_start('Homepage Sections','sections','Choose which homepage sections are visible. Hidden sections remain saved in the CMS.');
?>
<form class="adm-panel adm-form adm-section-form" method="post"><?php ts_admin_csrf_field();?>
<div class="adm-panel-heading"><div><p class="adm-eyebrow">HOMEPAGE EXPERIENCE</p><h2>Control section visibility</h2><p class="adm-help">Switch a section off to hide it from visitors without deleting its content.</p></div></div>
<div class="adm-visibility-list">
<?php foreach($sections as $key=>$meta):$on=!array_key_exists($key,$current)||$current[$key]===1;?><label class="adm-visibility-item"><span class="adm-visibility-copy"><b><?=e($meta[0])?></b><small><?=e($meta[1])?></small></span><span class="adm-switch"><input type="checkbox" name="sections[<?=e($key)?>]" value="1" <?=$on?'checked':''?>><i></i></span></label><?php endforeach;?>
</div>
<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit">Save visibility settings</button><a class="adm-button adm-button-outline" href="../index.php" target="_blank" rel="noopener">Preview website ↗</a></div>
</form>
<?php ts_admin_page_end(); ?>
