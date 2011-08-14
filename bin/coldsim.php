#!/usr/bin/php
<?php
/**
*
* @package ColdSim
* @copyright (c) 2010-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

define("IN_SIM", true);
define("IN_GAME", true);
require($root_path . "lib/common.php");

if(!extension_loaded('php_gtk2') && function_exists('dl'))
{
        @dl('php_gtk2.so');
}

if (!class_exists('gtk'))
{       
        die("Please load the php-gtk2 module in your php.ini\n");
}

$root_path = get_root_path();

require($root_path . "lib/coldzone/constants.php");
require($root_path . "lib/coldzone/vars.php");
require($root_path . "lib/coldzone/class_battle.php");
require($root_path . "lib/class_coldsim.php");
require($root_path . "lib/class_config.php");
require($root_path . "lib/functions.php");

set_time_limit(0); // Override class_battle.php value

$glade = new GladeXML($root_path . "gui/coldsim.glade");
$coldsim = new coldsim($glade);
$glade->signal_autoconnect_instance($coldsim);

Gtk::main();

?>