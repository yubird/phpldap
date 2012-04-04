<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('groupdel');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
return ldap::groupdel($u->target);
?>
