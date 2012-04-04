<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('userdel');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
return ldap::userdel($u->target);
?>
