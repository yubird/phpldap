<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('userlist');
if ($u->getOption('help')) {
	$u->help();
	return false;
}
return ldap::userlist();
?>
