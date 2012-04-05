<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('grouplist');
if ($u->getOption('help')) {
	$u->help();
	return false;
}
return ldap::grouplist();
?>
