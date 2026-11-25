<?php
require_once __DIR__ . '/_common.php';
$pdo=db();
$sections=[
 'hero'=>['Homepage Hero',true,'hero'],
 'expertise'=>['Expertise',false,'expertise'],
 'concepts'=>['Concept Gallery',false,'concepts'],
 'process'=>['Our Process',false,'process'],
 'testimonials'=>['Testimonials',false,'testimonials'],
 'contact'=>['Contact Section',true,'contact-content'],
];
$section=(string)($_GET['section']??'hero');
if(!isset($sections[$section])){http_response_code(404);exit('Section not found.');}
[$sectionTitle,$single,$active]=$sections[$section];

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  ts_admin_verify_post();
  $action=(string)($_POST['action']??'save');
  if($action==='delete' && !$single){
   $id=(int)($_POST['id']??0);$q=$pdo->prepare('DELETE FROM content_items WHERE id=? AND section_key=?');$q->execute([$id,$section]);ts_admin_flash('success',$q->rowCount()?'Content item deleted. Its image file was kept.':'Content item not found.');ts_admin_redirect('content-manager.php?section='.rawurlencode($section));
  }
  if($action==='toggle' && !$single){
   $id=(int)($_POST['id']??0);$q=$pdo->prepare('UPDATE content_items SET is_published=CASE WHEN is_published=1 THEN 0 ELSE 1 END WHERE id=? AND section_key=?');$q->execute([$id,$section]);ts_admin_flash('success','Content visibility updated.');ts_admin_redirect('content-manager.php?section='.rawurlencode($section));
  }
  if($action!=='save')throw new RuntimeException('Unsupported action.');
  $id=(int)($_POST['id']??0);
  $key=$single?'main':trim((string)($_POST['item_key']??''));
  if(!$single && $key==='')$key='item_'.bin2hex(random_bytes(5));
  if(!$single && !preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$key))throw new RuntimeException('Item key may contain letters, numbers, dash and underscore only.');
  $title=trim((string)($_POST['title']??''));$subtitle=trim((string)($_POST['subtitle']??''));$description=trim((string)($_POST['description']??''));$button=trim((string)($_POST['button_text']??''));$link=trim((string)($_POST['link']??''));$order=(int)($_POST['sort_order']??0);$published=isset($_POST['is_published'])?1:0;
  $image=ts_admin_upload_image('image_file',$section);
  if($id>0){$q=$pdo->prepare('SELECT image FROM content_items WHERE id=? AND section_key=? LIMIT 1');$q->execute([$id,$section]);$old=$q->fetch();if(!$old)throw new RuntimeException('Content item not found.');if($image===null)$image=$old['image'];
   $q=$pdo->prepare('UPDATE content_items SET item_key=?,title=?,subtitle=?,description=?,image=?,link=?,button_text=?,sort_order=?,is_published=? WHERE id=? AND section_key=?');$q->execute([$key,$title,$subtitle,$description,$image,$link,$button,$order,$published,$id,$section]);
  }elseif($single){
   $q=$pdo->prepare('SELECT id,image FROM content_items WHERE section_key=? AND item_key=? ORDER BY id ASC LIMIT 1');$q->execute([$section,'main']);$old=$q->fetch();if($old){if($image===null)$image=$old['image'];$q=$pdo->prepare('UPDATE content_items SET title=?,subtitle=?,description=?,image=?,link=?,button_text=?,sort_order=?,is_published=? WHERE id=?');$q->execute([$title,$subtitle,$description,$image,$link,$button,$order,$published,(int)$old['id']]);}else{$q=$pdo->prepare('INSERT INTO content_items(section_key,item_key,title,subtitle,description,image,link,button_text,sort_order,is_published) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$section,'main',$title,$subtitle,$description,$image,$link,$button,$order,$published]);}
  }else{$q=$pdo->prepare('INSERT INTO content_items(section_key,item_key,title,subtitle,description,image,link,button_text,sort_order,is_published) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$section,$key,$title,$subtitle,$description,$image,$link,$button,$order,$published]);}
  ts_admin_flash('success',$single?'Homepage content saved successfully.':'Content item saved successfully.');ts_admin_redirect('content-manager.php?section='.rawurlencode($section));
 }catch(Throwable $e){error_log('TOP STUDIO content manager error: '.$e->getMessage());ts_admin_flash('error',$e instanceof RuntimeException?$e->getMessage():'Content could not be saved. Please try again.');ts_admin_redirect('content-manager.php?section='.rawurlencode($section).(!empty($_POST['id'])?'&id='.(int)$_POST['id']:''));}
}
$editId=(int)($_GET['id']??0);
$current=['id'=>0,'item_key'=>$single?'main':'','title'=>'','subtitle'=>'','description'=>'','image'=>'','link'=>'','button_text'=>'','sort_order'=>0,'is_published'=>1];
if($single){$q=$pdo->prepare('SELECT * FROM content_items WHERE section_key=? AND item_key=? ORDER BY id ASC LIMIT 1');$q->execute([$section,'main']);$current=$q->fetch()?:$current;}
elseif($editId){$q=$pdo->prepare('SELECT * FROM content_items WHERE id=? AND section_key=? LIMIT 1');$q->execute([$editId,$section]);$current=$q->fetch()?:$current;}
$q=$pdo->prepare('SELECT * FROM content_items WHERE section_key=? ORDER BY sort_order ASC,id ASC');$q->execute([$section]);$items=$q->fetchAll();
$activeSection=$active;
ts_admin_page_start($sectionTitle,$activeSection,'Edit the words, imagery and supporting content used across the public homepage.');
?>
<div class="adm-subnav"><?php foreach($sections as $key=>$meta):?><a class="<?=($key===$section?'is-active':'')?>" href="content-manager.php?section=<?=e($key)?>"><?=e($meta[0])?></a><?php endforeach;?></div>
<div class="adm-content-grid adm-content-manager-grid">
<section class="adm-panel adm-form-panel"><div class="adm-panel-heading"><div><p class="adm-eyebrow"><?=$single?'SINGLE SECTION':'CONTENT EDITOR'?></p><h2><?=$editId?'Edit item':($single?'Homepage section':'Add content item')?></h2></div><?php if($editId):?><a class="adm-text-link" href="content-manager.php?section=<?=e($section)?>">＋ New item</a><?php endif;?></div>
<form class="adm-form" method="post" enctype="multipart/form-data"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$current['id']?>">
<?php if(!$single):?><label>Item key<input name="item_key" value="<?=e($current['item_key'])?>" placeholder="Leave blank to generate automatically"><small>Optional internal ID. Use letters, numbers, dash or underscore.</small></label><?php endif;?>
<label>Title<input name="title" value="<?=e($current['title'])?>"></label>
<label>Subtitle / eyebrow<input name="subtitle" value="<?=e($current['subtitle'])?>"></label>
<label>Description<textarea name="description" rows="6"><?=e($current['description'])?></textarea></label>
<div class="adm-form-row"><label>Button text / tag<input name="button_text" value="<?=e($current['button_text'])?>"></label><label>Link / caption<input name="link" value="<?=e($current['link'])?>"></label></div>
<label>Display order<input type="number" name="sort_order" value="<?=(int)$current['sort_order']?>"></label>
<label>Image<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WEBP · Maximum 5 MB.</small></label>
<?php $src=ts_admin_image_url($current['image']);if($src):?><div class="adm-current-image"><img src="<?=e($src)?>" alt="Current content image"><span>Current image · Leave upload empty to keep</span></div><?php endif;?>
<label class="adm-check"><input type="checkbox" name="is_published" value="1" <?=((int)$current['is_published']===1?'checked':'')?>><span>Published / visible on homepage</span></label>
<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit">Save content</button><?php if($editId):?><a class="adm-button adm-button-quiet" href="content-manager.php?section=<?=e($section)?>">Cancel</a><?php endif;?></div>
</form></section>
<?php if(!$single):?><section class="adm-panel adm-table-panel"><div class="adm-panel-heading"><div><p class="adm-eyebrow">CONTENT LIBRARY</p><h2>Existing items <span class="adm-count-pill"><?=count($items)?></span></h2></div></div>
<?php if(!$items):?><div class="adm-empty-state"><span>✳</span><b>No custom items yet</b><p>The homepage will continue using its built-in fallback content until you add a published item here.</p></div><?php else:?><div class="adm-project-table-wrap"><table class="adm-data-table"><thead><tr><th>ITEM</th><th>ORDER</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody><?php foreach($items as $it):?><tr><td><div class="adm-table-project"><?php $img=ts_admin_image_url($it['image']);if($img):?><img src="<?=e($img)?>" alt=""><?php else:?><span class="adm-thumb-placeholder">TS</span><?php endif;?><span><b><?=e($it['title']?:'(Untitled item)')?></b><small><?=e($it['subtitle']?:$it['item_key'])?></small></span></div></td><td><?=(int)$it['sort_order']?></td><td><?=ts_admin_status_badge((int)$it['is_published']===1)?></td><td><div class="adm-row-actions"><a class="adm-action-link" href="content-manager.php?section=<?=e($section)?>&id=<?=(int)$it['id']?>">Edit</a><form method="post" onsubmit="return confirm('Change this item visibility?')"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$it['id']?>"><button type="submit" class="adm-action-link"><?=((int)$it['is_published']===1?'Unpublish':'Publish')?></button></form><form method="post" onsubmit="return confirm('Delete this content item? Its uploaded image file will be kept.')"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$it['id']?>"><button type="submit" class="adm-action-link is-danger">Delete</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><?php endif;?>
</div>
<?php ts_admin_page_end(); ?>
