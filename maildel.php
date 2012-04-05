<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('maildel');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
return ldap::maildel($u->target);
?>
