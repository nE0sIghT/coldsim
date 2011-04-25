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

class config
{
	private $default = array(
		'version'			=> VERSION,
		'position'			=> 0,
		'settings_store_position'	=> 1,
		'settings_store_game_factor'	=> 1,
		'settings_simulations'		=> 20,
	);
	private $data = array();
	private $config_file = null;

	function __construct()
	{
		global $root_path;

		$this->config_file = $root_path . 'etc/coldsim.conf';

		if(file_exists($this->config_file))
		{
			if($fp = fopen($this->config_file, 'rt'))
			{
				$raw = file($this->config_file);
				foreach($raw as $line)
				{
					$line = trim($line);
					list($key, $value) = explode('=', $line);
	
					$key = trim($key);
					$value = trim($value);
	
					$this->data[$key] = $value;
				}
			}
		}

		foreach($this->default as $key => $value)
		{
			if(!isset($this->data[$key]))
				$this->data[$key] = $value;
		}

		// Upgrade config if needed
		if(version_compare($this->data['version'], VERSION, "<"))
		{
			$this->data['version'] = $this->default['version'];
		}
	}

	function __destruct()
	{
		if($fp = fopen($this->config_file, 'wt'))
		{
			foreach($this->data as $key => $value)
			{
				fwrite($fp, "$key = $value\n");
			}
			fclose($fp);
		}
	}

	function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : (isset($this->default[$key]) ? $this->default[$key] : false);
	}

	function get_setting($key)
	{
		return $this->get('settings_' . $key);
	}

	function set($key, $value)
	{
		if(is_bool($value) || is_null($value))
			$value = (int) $value;

		$this->data[$key] = (string) $value;
	}

	function set_setting($key, $value)
	{
		$this->set('settings_' . $key, $value);
	}

	function get_group($prefix, $omit_prefix = false)
	{
		$keys = array();
		foreach($this->data as $key => $value)
		{
			if(strpos($key, $prefix . '_') === 0)
			{
				$keys[] = !$omit_prefix ? $key : substr($key, strlen($prefix) + 1);
			}
		}

		return $keys;
	}
}

?>
