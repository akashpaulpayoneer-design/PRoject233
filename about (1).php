<?php
require_once __DIR__ . '/_common.php';
$pdo=db();
$getAboutItem = static function(string $key) use ($pdo): ?array {
    $q=$pdo->prepare("SELECT * FROM content_items WHERE section_key='about' AND item_key=? ORDER BY id ASC LIMIT 1");
    $q->execute([$key]); return $q->fetch() ?: null;
};
$main=$getAboutItem('main');
$featureDefaults=[
 1=>['title'=>'Creative design solutions','description'=>'Ideas shaped around your space and needs.'],
 2=>['title'=>'Materials with purpose','description'=>'A considered balance of texture, tone and function.'],
 3=>['title'=>'Personal attention','description'=>'A collaborative approach from first discussion.']
];
$features=[]; for($i=1;$i<=3;$i++) $features[$i]=$getAboutItem('feature_'.$i);

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  ts_admin_verify_post();
  $image=ts_admin_upload_image('image','about');
  if($image===null) $image=(string)($main['image']??'');
  $enabled=isset($_POST['is_published'])?1:0;
  $pdo->beginTransaction();
  $saveItem=static function(string $key,array $d) use($pdo):void{
   $q=$pdo->prepare("SELECT id FROM content_items WHERE section_key='about' AND item_key=? ORDER BY id ASC LIMIT 1");$q->execute([$key]);$id=(int)$q->fetchColumn();
   if($id){$s=$pdo->prepare('UPDATE content_items SET title=?,subtitle=?,description=?,image=?,link=?,button_text=?,sort_order=?,is_published=? WHERE id=?');$s->execute([$d['title'],$d['subtitle'],$d['description'],$d['image'],$d['link'],$d['button_text'],$d['sort_order'],$d['is_published'],$id]);}
   else{$s=$pdo->prepare("INSERT INTO content_items(section_key,item_key,title,subtitle,description,image,link,button_text,sort_order,is_published) VALUES('about',?,?,?,?,?,?,?,?,?)");$s->execute([$key,$d['title'],$d['subtitle'],$d['description'],$d['image'],$d['link'],$d['button_text'],$d['sort_order'],$d['is_published']]);}
  };
  $saveItem('main',['title'=>trim((string)($_POST['title']??'')),'subtitle'=>trim((string)($_POST['subtitle']??'')),'description'=>trim((string)($_POST['description']??'')),'image'=>$image,'link'=>trim((string)($_POST['image_caption']??'')),'button_text'=>trim((string)($_POST['eyebrow']??'')),'sort_order'=>1,'is_published'=>$enabled]);
  for($i=1;$i<=3;$i++) $saveItem('feature_'.$i,['title'=>trim((string)($_POST['feature_title_'.$i]??'')),'subtitle'=>'','description'=>trim((string)($_POST['feature_description_'.$i]??'')),'image'=>'','link'=>'','button_text'=>'','sort_order'=>$i,'is_published'=>$enabled]);
  $pdo->commit(); ts_admin_flash('success','About section saved successfully.'); ts_admin_redirect('about.php');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('TOP STUDIO about manager error: '.$e->getMessage());ts_admin_flash('error',$e instanceof RuntimeException?$e->getMessage():'The About section could not be saved.');ts_admin_redirect('about.php');}
}
$main=$getAboutItem('main');$features=[];for($i=1;$i<=3;$i++)$features[$i]=$getAboutItem('feature_'.$i);
$currentImage=ts_admin_image_url($main['image']??'');
ts_admin_page_start('About the Studio','about','Shape the story, philosophy and principles behind TOP STUDIO.');
?>
<section class="adm-panel adm-form-panel adm-single-form"><div class="adm-panel-heading"><div><p class="adm-eyebrow">STUDIO STORY</p><h2>About section content</h2></div><?=ts_admin_status_badge(!isset($main['is_published'])||(int)$main['is_published']===1,'Visible','Hidden')?></div>
<form class="adm-form" method="post" enctype="multipart/form-data"><?php ts_admin_csrf_field();?>
<label>Eyebrow / overline<input name="eyebrow" value="<?=e($main['button_text']??'A MORE THOUGHTFUL APPROACH')?>" placeholder="A MORE THOUGHTFUL APPROACH"></label>
<div class="adm-form-row"><label>Heading — first line<input name="title" value="<?=e($main['title']??'A considered space.')?>"></label><label>Heading — emphasis line<input name="subtitle" value="<?=e($main['subtitle']??'A better everyday.')?>"></label></div>
<label>Studio description<textarea name="description" rows="6"><?=e($main['description']??'TOP STUDIO creates thoughtful interior design directions for residential and commercial spaces.')?></textarea></label>
<label>About image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WEBP · Maximum 5 MB.</small></label>
<?php if($currentImage):?><div class="adm-current-image"><img src="<?=e($currentImage)?>" alt="Current About image"><span>Current About image</span></div><?php endif;?>
<label>Image caption<input name="image_caption" value="<?=e($main['link']??'DESIGN INSPIRATION / CONCEPTUAL IMAGE')?>"></label>
<div class="adm-divider"></div><div class="adm-panel-heading"><div><p class="adm-eyebrow">STUDIO PRINCIPLES</p><h2>Three key features</h2></div></div>
<div class="adm-feature-editor-grid"><?php for($i=1;$i<=3;$i++):$fv=$features[$i]??[];?><article class="adm-feature-editor"><span class="adm-feature-number">0<?=$i?></span><label>Feature title<input name="feature_title_<?=$i?>" value="<?=e($fv['title']??$featureDefaults[$i]['title'])?>"></label><label>Feature description<textarea name="feature_description_<?=$i?>" rows="3"><?=e($fv['description']??$featureDefaults[$i]['description'])?></textarea></label></article><?php endfor;?></div>
<label class="adm-check"><input type="checkbox" name="is_published" value="1" <?=(!isset($main['is_published'])||(int)$main['is_published']===1?'checked':'')?>><span>Show the About section on the homepage</span></label>
<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit">Save About section</button><a class="adm-button adm-button-outline" href="../index.php#about" target="_blank" rel="noopener">Preview on website ↗</a></div>
</form></section>
<?php ts_admin_page_end(); ?>
