<?php
include(dirname(__FILE__).'/includes/header.php');
$u = new util('chsh');
if ($u->getOption('help') || $u->target === false) {
	$u->help();
	return false;
}
if (($shell = $u->getOption('shell')) === false) {
	$u->help();
	return false;
}
return ldap::chsh($u->target, $shell);
?>
