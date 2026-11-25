<?php
require_once __DIR__ . '/_common.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  ts_admin_verify_post();$action=(string)($_POST['action']??'');$id=(int)($_POST['id']??0);
  if($action==='status'){$status=(string)($_POST['status']??'New');if(!in_array($status,['New','Contacted'],true))throw new RuntimeException('Invalid inquiry status.');$q=$pdo->prepare('UPDATE inquiries SET status=? WHERE id=?');$q->execute([$status,$id]);ts_admin_flash('success','Inquiry status updated.');}
  elseif($action==='delete'){$q=$pdo->prepare('DELETE FROM inquiries WHERE id=?');$q->execute([$id]);ts_admin_flash('success',$q->rowCount()?'Inquiry deleted.':'Inquiry not found.');}
  else throw new RuntimeException('Unsupported inquiry action.');
  $back='inquiries.php';if(!empty($_POST['return_q']))$back.='?q='.rawurlencode((string)$_POST['return_q']);ts_admin_redirect($back);
 }catch(Throwable $e){error_log('TOP STUDIO inquiry management error: '.$e->getMessage());ts_admin_flash('error',$e instanceof RuntimeException?$e->getMessage():'The inquiry action could not be completed.');ts_admin_redirect('inquiries.php');}
}
$search=trim((string)($_GET['q']??''));$filter=(string)($_GET['status']??'');$where=[];$params=[];
if($search!==''){$where[]='(name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';$like='%'.$search.'%';array_push($params,$like,$like,$like,$like);}
if(in_array($filter,['New','Contacted'],true)){$where[]='status = ?';$params[]=$filter;}
$sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export = $pdo->prepare('SELECT id,name,email,phone,message,status,created_at FROM inquiries'.$sqlWhere.' ORDER BY created_at DESC');
    $export->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=top-studio-inquiries-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID','Name','Email','Phone','Message','Status','Created At']);
    while ($record = $export->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$record['id'],$record['name'],$record['email'],$record['phone'],$record['message'],$record['status'],$record['created_at']]);
    }
    fclose($output);
    exit;
}
$count=$pdo->prepare('SELECT COUNT(*) FROM inquiries'.$sqlWhere);$count->execute($params);$total=(int)$count->fetchColumn();
$page=max(1,(int)($_GET['page']??1));$perPage=20;$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$q=$pdo->prepare('SELECT * FROM inquiries'.$sqlWhere.' ORDER BY created_at DESC LIMIT '.$perPage.' OFFSET '.$offset);$q->execute($params);$rows=$q->fetchAll();
$newCount=(int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='New'")->fetchColumn();
ts_admin_page_start('Client Inquiries','inquiries','Review project requests, follow up with potential clients and keep your response status organised.');
?>
<div class="adm-toolbar-row"><div class="adm-inline-stats"><span><b><?=$total?></b> MATCHING INQUIRIES</span><span><b><?=$newCount?></b> NEW TO REVIEW</span></div><a class="adm-button adm-button-outline" href="?<?=e(http_build_query(['q'=>$search,'status'=>$filter,'export'=>'csv']))?>">Download CSV ↓</a></div>
<section class="adm-panel">
<form class="adm-filter-form" method="get"><label class="adm-search-field"><span>⌕</span><input type="search" name="q" value="<?=e($search)?>" placeholder="Search name, email, phone or message"></label><select name="status"><option value="">All statuses</option><option value="New" <?=$filter==='New'?'selected':''?>>New</option><option value="Contacted" <?=$filter==='Contacted'?'selected':''?>>Contacted</option></select><button class="adm-button adm-button-gold" type="submit">Filter</button><?php if($search!==''||$filter!==''):?><a class="adm-button adm-button-quiet" href="inquiries.php">Clear</a><?php endif;?></form>
<?php if(!$rows):?><div class="adm-empty-state"><span>✉</span><b>No inquiries found</b><p>Website contact form submissions will be listed here. Adjust the filter or wait for the next client message.</p></div>
<?php else:?><div class="adm-inquiry-list">
<?php foreach($rows as $row):?><article class="adm-inquiry-card">
 <div class="adm-inquiry-main"><div class="adm-inquiry-heading"><div><p class="adm-eyebrow">INQUIRY #<?=(int)$row['id']?></p><h3><?=e($row['name'])?></h3></div><?=ts_admin_status_badge(($row['status']??'New')==='New','New','Contacted')?></div>
 <div class="adm-inquiry-contact"><a href="mailto:<?=e($row['email'])?>">✉ <?=e($row['email'])?></a><?php if(!empty($row['phone'])):?><a href="tel:<?=e(preg_replace('/[^0-9+]/','',(string)$row['phone']))?>">☎ <?=e($row['phone'])?></a><?php endif;?><span>◷ <?=e(date('d M Y · h:i A',strtotime((string)$row['created_at'])))?></span></div>
 <p class="adm-inquiry-message"><?=nl2br(e($row['message']))?></p>
 </div>
 <div class="adm-inquiry-actions"><form method="post"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="status" value="<?=($row['status']??'New')==='New'?'Contacted':'New'?>"><button class="adm-button adm-button-outline" type="submit"><?=($row['status']??'New')==='New'?'Mark contacted':'Mark new'?></button></form><form method="post" onsubmit="return confirm('Permanently delete this inquiry record?')"><?php ts_admin_csrf_field();?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="adm-button adm-button-danger" type="submit">Delete</button></form></div>
</article><?php endforeach;?></div>
<?php if($pages>1):?><div class="adm-pagination"><span>Page <?=$page?> of <?=$pages?></span><div><?php if($page>1):?><a href="?<?=http_build_query(['q'=>$search,'status'=>$filter,'page'=>$page-1])?>">← Previous</a><?php endif;?><?php if($page<$pages):?><a href="?<?=http_build_query(['q'=>$search,'status'=>$filter,'page'=>$page+1])?>">Next →</a><?php endif;?></div></div><?php endif;?>
<?php endif;?></section>
<?php ts_admin_page_end(); ?>
