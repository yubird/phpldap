<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('useradd');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
$pass = $u->getOption('password');
$uid = $u->getOption('uid');
$gid = $u->getOption('gid');
if (ldap::useradd($u->target, $pass, $uid) === false) {
	// ユーザ追加
	return false;
}
if ($gid !== false) {
	// GIDセット
	if (ldap::chgroup($u->target, $gid) === false) {
		return false;
	}
}
return true;
?>
