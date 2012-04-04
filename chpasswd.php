<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('chpasswd');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
if (($pass = $u->getOption('password')) === false) {
	$u->help();
	return false;
}
return ldap::chpasswd($u->target, $pass);
?>
