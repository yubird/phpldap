<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('deluser');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
if (($group = $u->getOption('group')) === false) {
	$u->help();
	return false;
}
return ldap::deluser($group, $u->target);
?>
