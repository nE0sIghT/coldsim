#!/usr/bin/php
<?php
/**
*
* @package ColdSim
* @version $Id$
* @copyright (c) 2010-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

define("IN_SIM", true);
define("IN_GAME", true);
define("VERSION", '1.0');

define('MAX_PLAYER_PLANETS',	0); // Suppress undefined warning
define('HOST',			'');
define('WIN_HOST',		stristr(PHP_OS, "win"));

$root_path = get_root_path();

require($root_path . "lib/coldzone/constants.php");
require($root_path . "lib/coldzone/vars.php");
require($root_path . "lib/coldzone/class_battle.php");
require($root_path . "lib/class_config.php");

set_time_limit(0); // Override class_battle.php value
@ini_set("memory_limit", -1);

if($argc < 2)
{
	echo "Cold Zone battle calculator, version " . VERSION . PHP_EOL;
	echo "Usage:  " . basename(__FILE__) . " simulation_data" . PHP_EOL;
	exit;
}

$data_ary = @unserialize(base64_decode($argv[1]));

if(!is_array($data_ary) || sizeof($data_ary) != 2)
{
	echo "Wrong simulation_data" . PHP_EOL;
	exit(1);
}

if($argc == 3)
{
	file_put_contents(temp_dir() . "ttt", "tt");
	file_put_contents(temp_dir() . "csim_" . $argv[2], getmypid());
}

$battle = new Battle($data_ary[0], $data_ary[1]);
$start_time = microtime(true);
$battle->calculate();
$calculate_time = microtime(true) - $start_time;

file_put_contents(temp_dir() . "csim_" . getmypid(), serialize(
	array(
		'fleet'			=> $battle->fleet,
		'debris'		=> $battle->debris,
		'winner'		=> $battle->winner,
		'debris'		=> $battle->debris,
		'moon_chance'		=> $battle->moon_chance,
		'calculate_time'	=> $calculate_time,
	)
));

function temp_dir()
{
	if(substr(sys_get_temp_dir(), strlen(sys_get_temp_dir()) - 1) !== DIRECTORY_SEPARATOR)
		return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
	else
		return sys_get_temp_dir();
}

exit;

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
