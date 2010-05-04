<?php
/*
	Wiki-System
	(c) 2008-2010 by StefanT
	http://www.mybbcoder.info
*/
define('NO_ONLINE', 1);
define('IN_MYBB', 1);
require_once './global.php';
if(!isset($wiki))
{
	$error_handler->trigger('<b><i>Wiki not activated.</i></b>', MYBB_GENERAL);
}
if(version_compare(PHP_VERSION, '5.0.0', '<'))
{
	$error_handler->trigger('<b><i>Wiki can not start, because it requires PHP 5.</i></b>', MYBB_GENERAL);
}
$wiki->system();
?>
