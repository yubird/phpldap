<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('mailadd');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
$pass = $u->getOption('password');
$uid = $u->getOption('uid');
$gid = $u->getOption('gid');
if (($mail = $u->getOption('mail')) === false) {
	$u->help();
	return false;
}
return ldap::mailadd($u->target, $mail, $pass, $uid, $gid);
?>
