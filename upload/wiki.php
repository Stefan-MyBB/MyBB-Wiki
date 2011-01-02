<?php
 /**
 * This file is part of MyBB-Wiki.
 * Copyright (C) 2008-2011 StefanT (http://www.mybbcoder.info)
 * https://github.com/Stefan-ST/MyBB-Wiki
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
