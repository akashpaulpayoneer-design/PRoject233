<?php
require_once __DIR__ . '/../config.php';
if (!empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
  if($username===''||$password==='')throw new RuntimeException('Enter your username and password.');
  $q=db()->prepare('SELECT * FROM admins WHERE username=? LIMIT 1');$q->execute([$username]);$admin=$q->fetch();
  if(!$admin||!password_verify($password,(string)$admin['password_hash']))throw new RuntimeException('Incorrect username or password.');
  session_regenerate_id(true);$_SESSION['admin_id']=(int)$admin['id'];$_SESSION['admin_name']=(string)$admin['username'];$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: index.php');exit;
 }catch(Throwable $e){error_log('TOP STUDIO admin login error: '.$e->getMessage());$error=$e instanceof RuntimeException?$e->getMessage():'Unable to sign in. Please check the database connection.';}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin Sign In | TOP STUDIO</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/admin.css?v=20261031-system1"></head>
<body class="admin-body adm-login-body"><main class="adm-login-wrap"><div class="adm-login-art"><div class="adm-login-mark">TS</div><p class="adm-eyebrow">A CONSIDERED APPROACH TO INTERIORS</p><h1>Design with<br><em>intention.</em></h1><p class="adm-login-manifesto">The private workspace for the people shaping the TOP STUDIO experience.</p><div class="adm-login-lines"><span></span><span></span><span></span></div><small>TOP STUDIO · INTERIOR &amp; DECOR · BANGLADESH</small></div><section class="adm-login-card"><a class="adm-login-brand" href="../index.php"><strong>TOP STUDIO</strong><small>ADMINISTRATION</small></a><p class="adm-eyebrow">WELCOME BACK</p><h2>Sign in to your<br><em>workspace.</em></h2><?php if($error!==''):?><div class="adm-alert is-error"><?=e($error)?></div><?php endif;?><form method="post" class="adm-form"><?php echo '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';?><label>Username<input type="text" name="username" required autocomplete="username" value="<?=e($_POST['username']??'')?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="adm-button adm-button-gold adm-login-submit" type="submit">Sign in securely <span>↗</span></button></form><a class="adm-login-back" href="../index.php">← Return to public website</a></section></main></body></html>
