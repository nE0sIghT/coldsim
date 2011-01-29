#!/usr/bin/php
<?php
/**
*
* @package ColdSim
* @copyright (c) 2010 nE0sIghT
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

define("IN_SIM", true);
define("IN_GAME", true);
define("VERSION", '0.3.2 RC1');

define('MAX_PLAYER_PLANETS',	0); // Suppress undefined warning
define('HOST',			'');
define('WIN_HOST',		stristr(PHP_OS, "win"));

if(!extension_loaded('php_gtk2') && function_exists('dl'))
{
        @dl('php_gtk2.so');
}

if (!class_exists('gtk'))
{       
        die("Please load the php-gtk2 module in your php.ini\n");
}

if(!WIN_HOST)
{
	if(getenv('LANG') && strpos(getenv('LANG'), '.') && function_exists('ini_set'))
	{
		list(, $encoding) = explode('.', getenv('LANG'));
		ini_set('php-gtk.codepage', $encoding);
	}
}

$root_path = get_root_path();

require($root_path . "lib/coldzone/vars.php");
require($root_path . "lib/coldzone/class_battle.php");
require($root_path . "lib/class_coldsim.php");
require($root_path . "lib/class_config.php");
require($root_path . "lib/functions.php");

set_time_limit(0); // Override class_battle.php value
@ini_set("memory_limit", -1);

$glade = new GladeXML($root_path . "gui/coldsim.glade");
$coldsim = new coldsim($glade);
$glade->signal_autoconnect_instance($coldsim);

Gtk::main();

function get_root_path()
{
	$current_path = getcwd();

	if(strpos($current_path, 'bin') == strlen($current_path) - 3 || strpos($current_path, 'php') == strlen($current_path) - 3)
	{
		return '../';
	}

	return './';
}

?>