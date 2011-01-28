<?php
/**
*
* @package ColdSim
* @version $Id$
* @copyright (c) 2010-2011 nE0sIghT
* @license GNU General Public License, version 2 http://www.gnu.org/licenses/gpl-2.0.html
*
*/

if (!defined('IN_SIM'))
{
	exit;
}

class config
{
	private $default = array(
		'version'	=> VERSION,
		'position'	=> 0,
	);
	private $data = array();
	private $config_file = null;

	function __construct()
	{
		global $root_path;

		$this->config_file = $root_path . 'etc/update_check';

		if(file_exists($this->config_file))
		{
			if($fp = fopen($this->config_file, 'wt'))
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
			switch($this->data['version'])
			{
				default:
					break;
			}
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

	function set($key, $value)
	{
		$this->data[$key] = $value;
	}
}

?>
