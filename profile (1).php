<?php
require_once __DIR__ . '/_common.php';
$pdo=db();$adminId=(int)($_SESSION['admin_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  ts_admin_verify_post();$current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
  if(strlen($new)<12)throw new RuntimeException('Use a new password with at least 12 characters.');
  if($new!==$confirm)throw new RuntimeException('The new password confirmation does not match.');
  $q=$pdo->prepare('SELECT password_hash FROM admins WHERE id=? LIMIT 1');$q->execute([$adminId]);$row=$q->fetch();
  if(!$row||!password_verify($current,(string)$row['password_hash']))throw new RuntimeException('Current password is incorrect.');
  $hash=password_hash($new,PASSWORD_DEFAULT);$s=$pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?');$s->execute([$hash,$adminId]);session_regenerate_id(true);ts_admin_flash('success','Administrator password changed successfully.');ts_admin_redirect('profile.php');
 }catch(Throwable $e){error_log('TOP STUDIO admin password update error: '.$e->getMessage());ts_admin_flash('error',$e instanceof RuntimeException?$e->getMessage():'Password could not be changed.');ts_admin_redirect('profile.php');}
}
ts_admin_page_start('Admin Security','profile','Protect access to your website management system.');
?>
<div class="adm-security-grid"><section class="adm-panel adm-form-panel"><div class="adm-panel-heading"><div><p class="adm-eyebrow">ACCOUNT SECURITY</p><h2>Change password</h2><p class="adm-help">Use a unique password that is not reused on other websites.</p></div><span class="adm-security-icon">♙</span></div>
<form class="adm-form" method="post"><?php ts_admin_csrf_field();?>
<label>Administrator username<input value="<?=e($_SESSION['admin_name']??'Admin')?>" disabled></label>
<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>New password<input type="password" name="new_password" required minlength="12" autocomplete="new-password"><small>At least 12 characters. A passphrase is recommended.</small></label>
<label>Confirm new password<input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label>
<div class="adm-form-actions"><button class="adm-button adm-button-gold" type="submit">Update password</button></div>
</form></section>
<aside class="adm-panel adm-security-note"><p class="adm-eyebrow">SECURITY CHECKLIST</p><h2>Keep your studio protected.</h2><ul><li>Use a unique, long password.</li><li>Do not share admin credentials in chat or screenshots.</li><li>Sign out when using a shared computer.</li><li>Keep PHP, hosting and database credentials private.</li><li>Remove any installer or temporary archive from the public web root after confirming it is no longer needed.</li></ul></aside></div>
<?php ts_admin_page_end(); ?>
