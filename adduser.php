<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('adduser');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
if (($group = $u->getOption('group')) === false) {
	$u->help();
	return false;
}
return ldap::adduser($group, $u->target);
?>
