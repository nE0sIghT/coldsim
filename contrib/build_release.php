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

$root_path = get_root_path();

require($root_path . "lib/common.php");

if(file_exists($root_path . "build"))
{
	if(!rrmdir($root_path . "build", true))
		die("Failed to clean build directory");
}
else if(!mkdir($root_path . "build"))
	die("Failed to create build directory");

exec("svn export $root_path {$root_path}build/coldsim");

if(!rrmdir($root_path . "build/coldsim/contrib"))
{
	die("Failed to remove contrib directory");
}

chdir("$root_path/build");

exec("7z a -tzip coldsim-" . VERSION . "-win32.zip coldsim");

if(!rrmdir("coldsim/php") || !unlink("coldsim/coldsim.cmd"))
{
	die("Failed to prepare linux build directory");
}

exec("tar -cJf coldsim-" . VERSION . "-linux.tar.xz coldsim");

echo "Complete" . PHP_EOL;

function rrmdir($directory, $empty = false)
{
	if(substr($directory, -1) != "/")
	{
		$directory .= "/";
	}

	if(!file_exists($directory) || !is_dir($directory))
	{
		return false;
	}
	else if(!is_readable($directory))
	{
		return false;
	}
	else
	{
		$directoryHandle = opendir($directory);
		while ($contents = readdir($directoryHandle))
		{
			if(!in_array($contents, array(".", "..")))
			{
				$path = $directory . $contents;

				if(is_dir($path))
				{
					if(!rrmdir($path))
						return false;
				}
				else
				{
					if(!unlink($path))
						return false;
				}
			}
		}

		closedir($directoryHandle);

		if($empty == false)
		{
			if(!rmdir($directory))
			{
				return false;
			}
		}
	
		return true;
	}
}

function get_root_path()
{
	$current_path = getcwd();

	if(in_array(basename($current_path), array("bin", "php", "contrib")))
	{
		return '../';
	}

	return './';
}
?>