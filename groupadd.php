<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('groupadd');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
return ldap::groupadd($u->target, $u->getOption('gid'));
?>
