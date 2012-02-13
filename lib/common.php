<?php
/**
*
* @package ColdSim
* @version $Id$
* @copyright (c) 2010-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

if (!defined('IN_SIM'))
{
	exit;
}

define('VERSION', '1.1');

define('MAX_PLAYER_PLANETS',	0); // Suppress undefined warning
define('HOST',			'');
define('WIN_HOST',		stristr(PHP_OS, "win"));

@ini_set("memory_limit", -1);

if(!WIN_HOST)
{
	if(getenv('LANG') && strpos(getenv('LANG'), '.') && function_exists('ini_set'))
	{
		list(, $encoding) = explode('.', getenv('LANG'));
		ini_set('php-gtk.codepage', $encoding);
	}
}

function temp_dir()
{
	if(substr(sys_get_temp_dir(), strlen(sys_get_temp_dir()) - 1) !== DIRECTORY_SEPARATOR)
		return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
	else
		return sys_get_temp_dir();
}
?>