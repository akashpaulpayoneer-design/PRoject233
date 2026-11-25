<?php
require_once __DIR__ . '/../config.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){
 if(empty($_SESSION['admin_id'])){header('Location: login.php');exit;}
 ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign out | TOP STUDIO</title><link rel="stylesheet" href="../assets/admin.css?v=20261031-system1"></head><body class="admin-body"><main class="adm-login-card adm-logout-card"><h1>Sign out?</h1><p>You are about to leave the TOP STUDIO admin workspace.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="adm-button adm-button-gold" type="submit">Confirm sign out</button><a class="adm-button adm-button-quiet" href="index.php">Cancel</a></form></main></body></html><?php exit;
}
verify_csrf();
$_SESSION=[];
if(ini_get('session.use_cookies')){$params=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);}
session_destroy();
header('Location: login.php');exit;
