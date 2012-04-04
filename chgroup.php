<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('chgroup');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
if (($gid = $u->getOption('gid')) === false) {
	$u->help();
	return false;
}
return ldap::chgroup($u->target, $gid);
?>
