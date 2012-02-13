<?php
/**
*
* @package ColdSim
* @copyright (c) 2010-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

if (!defined('IN_SIM'))
{
	exit;
}

if(!class_exists('Battle'))
{
	die("class_battle.php must be loaded before class_coldsim.php\n");
}

class coldsim
{
	private $glade				= null;
	private $config				= null;
	private $acs_slot			= 0;
	private $game_factor			= 1;
	private $fleets				= array(
		BATTLE_FLEET_ATTACKER			=> array(),
		BATTLE_FLEET_DEFENDER			=> array(),
	);
	private $simulations			= 20;
	private $results;
	private $prime_target			= 0;
	private $spy_buffer;
	private $file_filter			= null;
	private $files_action_signal		= null;
	private $files_choose_signal		= null;
	private $precission			= 1;

	private $completed_simulations		= 0;
	private $simulation_threads		= array();
	private $simulation_threads_return	= array();

	private $lang			= array(
		'RESULT_BATTLE_ATTACKER'	=> '| Победа атакующего (%u%%) |',
		'RESULT_BATTLE_DEFENDER'	=> '| Победа обороняющегося (%u%%) |',
		'RESULT_BATTLE_DRAW'		=> '| Ничья (%u%%) |',
		'RESULT_BATTLE_ROUNDS'		=> ' После ~%u раундов',

		'RESULT_DEBRIS'			=> '%s металла, %s кристалла ~ %s переработчиков',
		'RESULT_LOSE_RESOURCES'		=> '%s металла, %s кристалла',
		'RESULT_PLUNDER'		=> '%s металла, %s кристалла и %s дейтерия ~ %s БТ',
		'RESULT_PLUNDER_REAL'		=> '%s металла, %s кристалла и %s дейтерия (%u%% загрузки)',
		'RESULT_DEUTERIUM'		=> '%s дейтерия',
		'RESULT_TIME'			=> '%02u:%02u:%02u часов',
		'CALCULATE_TIME'		=> 'Ср.: %.2f; Макс: %.2f; Мин: %.2f',
	);

	function __construct($object)
	{
		$this->config = new config();

		$this->glade = &$object;
		$this->glade->get_widget('acs_combobox')->set_active(0);
		$this->glade->get_widget('acs_combobox_advanced')->set_active(0);
		$this->glade->get_widget('files_combobox')->set_active(0);
		$this->glade->get_widget('speed_factor')->set_active(0);

		if($this->config->get_setting('store_game_factor'))
			$this->glade->get_widget('game_factor')->set_active((int) $this->config->get_setting('game_factor'));
		else
			$this->glade->get_widget('game_factor')->set_active(0);
		$this->game_factor_changed($this->glade->get_widget('game_factor'));

		$this->spy_buffer = new GtkTextBuffer();
		$this->glade->get_widget('spy_report')->set_buffer($this->spy_buffer);

		$this->glade->get_widget('about_dialog')->set_version(VERSION);
		$this->clear_results();

		$this->reorder_tab_chains();

		$this->glade->get_widget('latest_version')->modify_fg(Gtk::STATE_NORMAL, GdkColor::parse('#008800'));
		$this->glade->get_widget('release_info')->modify_fg(Gtk::STATE_NORMAL, GdkColor::parse('#0000aa'));

		if($this->config->get_setting('store_position') && $this->config->get('position'))
		{
			list($x, $y) = explode(',', $this->config->get('position'));
			$this->glade->get_widget('window_main')->move($x, $y);
		}

		$this->glade->get_widget('window_main')->show();
		$this->check_update(true);
	}

	function reorder_tab_chains()
	{
		$tab_order = array();
		$index = 0;
		$index_offset = sizeof(vars::get_resources('fleet')) * 2;
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_a_$element"))
			{
				$tab_order[$index++] = $this->glade->get_widget("ship_a_$element");
			}

			if($this->glade->get_widget("ship_d_$element"))
			{
				$tab_order[$index + $index_offset] = $this->glade->get_widget("ship_d_$element");
			}
			$index++;
		}
		ksort($tab_order);
		$this->glade->get_widget("fleet_table")->set_focus_chain($tab_order);
	}

	function acs_changed($acs_combo)
	{
		$this->store_current_acs();
		$this->acs_slot = (int) $acs_combo->get_active();
		$this->glade->get_widget('acs_combobox_advanced')->set_active($this->acs_slot);
		$this->show_ships_results();
	}

	function acs_changed_advanced($acs_combo)
	{
		$this->store_current_acs("acs_combobox_advanced");
		$this->acs_slot = (int) $acs_combo->get_active();
		$this->glade->get_widget('acs_combobox')->set_active($this->acs_slot);
		$this->show_ships_results();
	}

	function game_factor_changed($game_factor_combo)
	{
		$factors = array(
			0	=> 1,
			1	=> 1.5,
		);
		$this->game_factor = $factors[$game_factor_combo->get_active()];

		if($this->config->get_setting('store_game_factor'))
			$this->config->set_setting('game_factor', $this->game_factor);
	}

	function store_current_acs($combo_name = "acs_combobox", $force_save = false)
	{
		$acs_combo = $this->glade->get_widget($combo_name);
		$change_only = $force_save ? false : ((int) $acs_combo->get_active() == $this->acs_slot);
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_a_$element"))
			{
				if(!$change_only)
					$this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['fleet'][$element] = (int) $this->glade->get_widget("ship_a_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("ship_a_$element")->set_text($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]['fleet'][$element]);
				}
				else
				{
					$this->glade->get_widget("ship_a_$element")->set_text(0);
				}
			}

			if($this->glade->get_widget("ship_d_$element"))
			{
				if(!$change_only)
					$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['fleet'][$element] = (int) $this->glade->get_widget("ship_d_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("ship_d_$element")->set_text($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]['fleet'][$element]);
				}
				else
				{
					$this->glade->get_widget("ship_d_$element")->set_text(0);
				}
			}
		}

		foreach(vars::get_resources('defense') as $element)
		{
			if($this->glade->get_widget("defense_$element"))
			{
				if(!$change_only)
					$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['fleet'][$element] = (int) $this->glade->get_widget("defense_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("defense_$element")->set_text($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]['fleet'][$element]);
				}
				else
				{
					$this->glade->get_widget("defense_$element")->set_text(0);
				}
			}
		}

		$additions = array(TECH_MILITARY, TECH_SHIELD, TECH_DEFENCE, TECH_COMBUSTION, TECH_IMPULSE_DRIVE, TECH_HYPERSPACE_DRIVE, RPG_ADMIRAL, RPG_GENERAL, RPG_AIDEDECAMP);
		foreach($additions as $element)
		{
			if($this->glade->get_widget("addition_a_$element"))
			{
				if(!$change_only)
					$this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['data'][vars::$db_fields[$element]] = (int) $this->glade->get_widget("addition_a_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("addition_a_$element")->set_text($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]['data'][vars::$db_fields[$element]]);
				}
				else
				{
					$this->glade->get_widget("addition_a_$element")->set_text(0);
				}
			}

			if($this->glade->get_widget("addition_d_$element"))
			{
				if(!$change_only)
					$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['data'][vars::$db_fields[$element]] = (int) $this->glade->get_widget("addition_d_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("addition_d_$element")->set_text($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]['data'][vars::$db_fields[$element]]);
				}
				else
				{
					$this->glade->get_widget("addition_d_$element")->set_text(0);
				}
			}
		}

		if(!$change_only)
		{
			$this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['data'] = array_merge($this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['data'], array(
				'username'		=> '',
				'id'			=> 0,
				'fleet_start_galaxy'	=> 0,
				'fleet_start_system'	=> 0,
				'fleet_start_planet'	=> 0,
				'fleet_end_galaxy'	=> 0,
				'fleet_end_system'	=> 0,
				'fleet_end_planet'	=> 0,
			));
			$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['data'] = array_merge($this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['data'], array(
				'username'		=> '',
				'id'			=> 0,
			));
		}
	}

	function swap_fleet()
	{
		$tmp = array();
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_a_$element"))
			{
				$tmp[$element] = (int) $this->glade->get_widget("ship_a_$element")->get_text();

				if($this->glade->get_widget("ship_d_$element"))
				{
					$this->glade->get_widget("ship_a_$element")->set_text((int) $this->glade->get_widget("ship_d_$element")->get_text());
				}
				else
				{
					$this->glade->get_widget("ship_a_$element")->set_text(0);
				}
			}

			if($this->glade->get_widget("ship_d_$element"))
			{
				if(isset($tmp[$element]))
				{
					$this->glade->get_widget("ship_d_$element")->set_text($tmp[$element]);
				}
				else
				{
					$this->glade->get_widget("ship_d_$element")->set_text(0);
				}
			}
		}
	}

	function clear_attacker()
	{
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_a_$element"))
			{
				$this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['fleet'][$element] = 0;
				$this->glade->get_widget("ship_a_$element")->set_text(0);
			}
		}
	}

	function clear_defender()
	{
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_d_$element"))
			{
				$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['fleet'][$element] = 0;
				$this->glade->get_widget("ship_d_$element")->set_text(0);
			}
		}

		foreach(vars::get_resources('defense') as $element)
		{
			if($this->glade->get_widget("defense_$element"))
			{
				$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['fleet'][$element] = 0;
				$this->glade->get_widget("defense_$element")->set_text(0);
			}
		}
	}

	function run_simulation_thread($attackers, $defenders)
	{
		global $root_path;

		if(WIN_HOST)
		{
			$pid_hash = md5(mt_rand());

			pclose(popen('start "' . $pid_hash . '" "' . str_replace("/", "\\", $root_path) . 'php\php-win.exe" "' . str_replace("/", "\\", $root_path) . 'bin\simulation.php" ' . base64_encode(serialize(array($attackers, $defenders))) . ' ' . $pid_hash, r));

			$pid = 0;
			while(!$pid)
			{
				if(file_exists(temp_dir() . "csim_" . $pid_hash))
				{
					$pid = (int) file_get_contents(temp_dir() . "csim_" . $pid_hash);
					@unlink(temp_dir() . "csim_" . $pid_hash);
				}
				else
					usleep(100);
			}
		}
		else
		{
			exec($root_path . 'bin/simulation.php "' . base64_encode(serialize(array($attackers, $defenders))) . '" > /dev/null 2>&1 & echo $!', $out);
			list($pid) = explode(" ", implode(PHP_EOL, $out));
		}

		$this->simulation_threads[$pid] = true;
	}

	function check_simulation_threads()
	{
		$usleep = true;
		foreach($this->simulation_threads as $pid => $true)
		{
			if(file_exists(temp_dir() . "csim_$pid"))
			{
				$this->simulation_threads_return[] = @unserialize(file_get_contents(temp_dir() . "csim_$pid"));
				$this->completed_simulations++;
				@unlink(temp_dir() . "csim_$pid");

				unset($this->simulation_threads[$pid]);
				$usleep = false;
			}
		}

		if($usleep)
			usleep(300);
	}

	function simulation_threads_count()
	{
		if($this->config->get_setting('threads') < 1)
		{
			$cpus = 0;
			if(WIN_HOST)
				$cpus = (int) getenv("NUMBER_OF_PROCESSORS");
			else
				$cpus = (int) exec("cat /proc/cpuinfo | grep -c processor");

			return max(1, $cpus);
		}
		else
			return $this->config->get_setting('threads');
	}

	function simulate()
	{
		$this->glade->get_widget('menubar')->deactivate();

		$this->store_current_acs('acs_combobox', true);
		$this->simulations = max(1, (int) $this->config->get_setting('simulations'));

		$this->clear_results();

		$calculate_time = array();

		$debris = array(
			'metal'		=> array(),
			'crystal'	=> array(),
			'total'		=> array(
				BATTLE_FLEET_ATTACKER	=> array(
					'metal'		=> array(),
					'crystal'	=> array(),
				),
				BATTLE_FLEET_DEFENDER	=> array(
					'metal'		=> array(),
					'crystal'	=> array(),
				),
			),
		);

		$source = array(
			TECH_COMBUSTION		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][vars::$db_fields[TECH_COMBUSTION]],
			TECH_IMPULSE_DRIVE	=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][vars::$db_fields[TECH_IMPULSE_DRIVE]],
			TECH_HYPERSPACE_DRIVE	=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][vars::$db_fields[TECH_HYPERSPACE_DRIVE]],
			RPG_GENERAL		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][vars::$db_fields[RPG_GENERAL]],
			RPG_AIDEDECAMP		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][vars::$db_fields[RPG_AIDEDECAMP]],
		);

		$empty = true;
		foreach($this->fleets[BATTLE_FLEET_ATTACKER] as $acs_slot => $slot_data)
		{
			foreach($slot_data['fleet'] as $ship_id => $ships_count)
			{
				if($ships_count)
				{
					$empty = false;
					break 2;
				}
			}
		}

		if($empty)
			return;

		$empty = true;
		foreach($this->fleets[BATTLE_FLEET_DEFENDER] as $acs_slot => $slot_data)
		{
			foreach($slot_data['fleet'] as $ship_id => $ships_count)
			{
				if($ships_count)
				{
					$empty = false;
					break 2;
				}
			}
		}

		if($empty)
			return;

		$speed_factor = array();
		for($i = 10; $i >= 0; $i -= 1)
			$speed_factor[] = $i;
		$speed_factor = $speed_factor[(int) $this->glade->get_widget("speed_factor")->get_active()];
		
		$fleet_speed = get_fleet_speed($this->fleets[BATTLE_FLEET_ATTACKER][0]['fleet'], $source);
		$distance = get_distance(
			(int) $this->glade->get_widget("source_galaxy")->get_text(),
			(int) $this->glade->get_widget("target_galaxy")->get_text(),
			(int) $this->glade->get_widget("source_system")->get_text(),
			(int) $this->glade->get_widget("target_system")->get_text(),
			(int) $this->glade->get_widget("source_planet")->get_text(),
			(int) $this->glade->get_widget("target_planet")->get_text()
		);
		$duration = get_duration($fleet_speed, $distance, $speed_factor);
		$consumption = get_fleet_consumption ($this->fleets[BATTLE_FLEET_ATTACKER][0]['fleet'], $source, $duration, $distance, $fleet_speed);

		$duration = max(1, round($duration / $this->game_factor));
		list($fleet_hours, $fleet_minutes, $fleet_seconds) = secs2time($duration);

		$this->glade->get_widget('fleet_deuterium')->set_text($this->encode(sprintf($this->lang['RESULT_DEUTERIUM'], $this->number_format($consumption))));
		$this->glade->get_widget('fleet_time')->set_text($this->encode(sprintf($this->lang['RESULT_TIME'],
			$fleet_hours,
			$fleet_minutes,
			$fleet_seconds
		)));

		if(!$this->config->get_setting('threaded') || $this->simulation_threads_count() <= 1)
		{
			$battle = new Battle($this->fleets[BATTLE_FLEET_ATTACKER], $this->fleets[BATTLE_FLEET_DEFENDER]);
			for($i = 0; $i < $this->simulations; $i++)
			{
				$start_time = microtime(true);
				$battle->calculate();
				$calculate_time[] = microtime(true) - $start_time;
	
				for($fleet_type = BATTLE_FLEET_ATTACKER; $fleet_type != BATTLE_DRAW; $fleet_type = ($fleet_type == BATTLE_FLEET_ATTACKER ? BATTLE_FLEET_DEFENDER : BATTLE_DRAW))
				{
					if($battle->fleet[$fleet_type])
					{
						foreach($battle->fleet[$fleet_type] as $acs_slot => $ships)
						{
							foreach($ships as $element => $count)
							{
								$this->results['ships'][$fleet_type][$acs_slot]['ships'][$element][] = $count;
							}
						}
					}
				}
	
				$this->results['battle'][$battle->winner]++;
				$this->results['rounds'][] = $battle->round + 1;
				$this->results['moon_chance'][] = $battle->moon_chance;
	                                             
				$debris['metal'][] = $battle->debris['metal'];
				$debris['crystal'][] = $battle->debris['crystal'];
				$debris['total'][BATTLE_FLEET_ATTACKER]['metal'][] = $battle->debris['total'][BATTLE_FLEET_ATTACKER]['metal'];
				$debris['total'][BATTLE_FLEET_ATTACKER]['crystal'][] = $battle->debris['total'][BATTLE_FLEET_ATTACKER]['crystal'];
				$debris['total'][BATTLE_FLEET_DEFENDER]['metal'][] = $battle->debris['total'][BATTLE_FLEET_DEFENDER]['metal'];
				$debris['total'][BATTLE_FLEET_DEFENDER]['crystal'][] = $battle->debris['total'][BATTLE_FLEET_DEFENDER]['crystal'];
			}
		}
		else
		{
			$this->completed_simulations = 0;
			$this->simulation_threads_return = array();

			while($this->completed_simulations + sizeof($this->simulation_threads) < $this->simulations)
			{
				if(sizeof($this->simulation_threads) < $this->simulation_threads_count())
				{
					$this->run_simulation_thread($this->fleets[BATTLE_FLEET_ATTACKER], $this->fleets[BATTLE_FLEET_DEFENDER]);
				}
				else
				{
					$this->check_simulation_threads();
				}
			}

			while($this->completed_simulations < $this->simulations)
			{
				$this->check_simulation_threads();
			}

			foreach($this->simulation_threads_return as $battle)
			{
				for($fleet_type = BATTLE_FLEET_ATTACKER; $fleet_type != BATTLE_DRAW; $fleet_type = ($fleet_type == BATTLE_FLEET_ATTACKER ? BATTLE_FLEET_DEFENDER : BATTLE_DRAW))
				{
					if($battle['fleet'][$fleet_type])
					{
						foreach($battle['fleet'][$fleet_type] as $acs_slot => $ships)
						{
							foreach($ships as $element => $count)
							{
								$this->results['ships'][$fleet_type][$acs_slot]['ships'][$element][] = $count;
							}
						}
					}
				}
	
				$this->results['battle'][$battle['winner']]++;
				$this->results['rounds'][] = $battle['round'] + 1;
				$this->results['moon_chance'][] = $battle['moon_chance'];
				$calculate_time[] = $battle['calculate_time'];

				$debris['metal'][] = $battle['debris']['metal'];
				$debris['crystal'][] = $battle['debris']['crystal'];
				$debris['total'][BATTLE_FLEET_ATTACKER]['metal'][] = $battle['debris']['total'][BATTLE_FLEET_ATTACKER]['metal'];
				$debris['total'][BATTLE_FLEET_ATTACKER]['crystal'][] = $battle['debris']['total'][BATTLE_FLEET_ATTACKER]['crystal'];
				$debris['total'][BATTLE_FLEET_DEFENDER]['metal'][] = $battle['debris']['total'][BATTLE_FLEET_DEFENDER]['metal'];
				$debris['total'][BATTLE_FLEET_DEFENDER]['crystal'][] = $battle['debris']['total'][BATTLE_FLEET_DEFENDER]['crystal'];
			}
		}

		$this->results['plunder']['metal'] = (int) $this->glade->get_widget("target_metal")->get_text();
		$this->results['plunder']['crystal'] = (int) $this->glade->get_widget("target_crystal")->get_text();
		$this->results['plunder']['deuterium'] = (int) $this->glade->get_widget("target_deuterium")->get_text();

		$result_battle = '';
		if($this->results['battle'][BATTLE_FLEET_ATTACKER])
		{
			$result_battle .= sprintf($this->lang['RESULT_BATTLE_ATTACKER'], round($this->results['battle'][BATTLE_FLEET_ATTACKER] * 100/$this->simulations));

			$this->glade->get_widget('result_plunder')->set_text($this->encode(sprintf($this->lang['RESULT_PLUNDER'],
				$this->number_format(floor($this->results['plunder']['metal'] / 2)),
				$this->number_format(floor($this->results['plunder']['crystal'] / 2)),
				$this->number_format(floor($this->results['plunder']['deuterium'] / 2)),
				$this->number_format(ceil((floor($this->results['plunder']['metal'] / 2) + floor($this->results['plunder']['crystal'] / 2) + floor($this->results['plunder']['deuterium'] / 2)) / vars::$params[SHIP_TRANSPORT_BIG]['capacity']))
			)));

			foreach ($this->results['ships'][BATTLE_FLEET_ATTACKER] as $fleet_id => $ships)
			{
				$this->results['max_resources'][$fleet_id] = 0;
				$this->results['steal'][$fleet_id] = array('metal' => 0, 'crystal' => 0, 'deuterium' => 0);

				foreach($ships['ships'] as $element => $counts)
				{
					$count = sizeof($counts) ? (int) round(array_sum($counts)/sizeof($counts), 1) : 0;
					$this->results['max_resources'][$fleet_id] += (int) (vars::$params[$element]['capacity'] * $count);
				}
				$this->results['max_resources']['total'] += $this->results['max_resources'][$fleet_id];
			}

			// Calculate new fleet maximum resources for base attacker
			if($this->results['max_resources']['total'])
			{
				foreach ($this->results['max_resources'] as $fleet_id => $capacity)
				{
					if($fleet_id === 'total' || !$capacity)
					{
						continue;
					}

					$resource_percent = $capacity / $this->results['max_resources']['total'];
					
					$metal   = floor(($resource_percent * $this->results['plunder']['metal']) / 2);
					$crystal = floor(($resource_percent * $this->results['plunder']['crystal']) / 2);
					$deuter  = floor(($resource_percent * $this->results['plunder']['deuterium']) / 2);

					if ($metal > round($capacity / 3))
					{
						$this->results['steal'][$fleet_id]['metal'] = round($capacity / 3);
						$capacity  = $capacity - $this->results['steal'][$fleet_id]['metal'];
					}
					else
					{
						$this->results['steal'][$fleet_id]['metal'] 	= $metal;
						$capacity	-= $this->results['steal'][$fleet_id]['metal'];
					}
					$metal -= $this->results['steal'][$fleet_id]['metal'];
					
					if ($crystal > round($capacity / 2))
					{
						$this->results['steal'][$fleet_id]['crystal']	= round($capacity / 2);
						$capacity	-= $this->results['steal'][$fleet_id]['crystal'];
					}
					else
					{
						$this->results['steal'][$fleet_id]['crystal'] 	= $crystal;
						$capacity   	-= $this->results['steal'][$fleet_id]['crystal'];
					}
					$crystal -= $this->results['steal'][$fleet_id]['crystal'];
					
					if ($deuter > $capacity)
					{
						$this->results['steal'][$fleet_id]['deuterium']	= $capacity;
						$capacity	-= $this->results['steal'][$fleet_id]['deuterium'];
					}
					else
					{
						$this->results['steal'][$fleet_id]['deuterium']	= $deuter;
						$capacity	-= $this->results['steal'][$fleet_id]['deuterium'];
					}
					$deuter -= $this->results['steal'][$fleet_id]['deuterium'];
					
					if($capacity > 0)
					{
						if ($metal > round($capacity/2))
						{
							$this->results['steal'][$fleet_id]['metal'] += round($capacity/2);
							$metal -= round($capacity/2);
							$capacity  = round($capacity/2);
						}
						else
						{
							$this->results['steal'][$fleet_id]['metal'] 	+= $metal;
							$capacity	-= $metal;
							$metal = 0;
							
							if ($crystal > $capacity)
							{
								$this->results['steal'][$fleet_id]['crystal']	+= $capacity;
								$crystal -= $capacity;
								$capacity	= 0;
							}
							else
							{
								$this->results['steal'][$fleet_id]['crystal'] 	+= $crystal;
								$capacity   	-= $crystal;
							}
						}
					}
					
					$this->results['steal'][$fleet_id] = array_map('round', $this->results['steal'][$fleet_id]);
					
					$this->results['steal']['total']['metal'] += $this->results['steal'][$fleet_id]['metal'];
					$this->results['steal']['total']['crystal'] += $this->results['steal'][$fleet_id]['crystal'];
					$this->results['steal']['total']['deuterium'] += $this->results['steal'][$fleet_id]['deuterium'];
					
					$this->results['steal'][$fleet_id]['metal'] = (string)sprintf('%.0f', floor($this->results['steal'][$fleet_id]['metal']));
					$this->results['steal'][$fleet_id]['crystal'] = (string)sprintf('%.0f', floor($this->results['steal'][$fleet_id]['crystal']));
					$this->results['steal'][$fleet_id]['deuterium'] = (string)sprintf('%.0f', floor($this->results['steal'][$fleet_id]['deuterium']));
				}
				$this->results['steal']['total']['metal'] = (string)sprintf('%.0f', floor($this->results['steal']['total']['metal']));
				$this->results['steal']['total']['crystal'] = (string)sprintf('%.0f', floor($this->results['steal']['total']['crystal']));
				$this->results['steal']['total']['deuterium'] = (string)sprintf('%.0f', floor($this->results['steal']['total']['deuterium']));
			}
		}
		else
		{
			$this->glade->get_widget('result_plunder')->set_text($this->encode(sprintf($this->lang['RESULT_PLUNDER'], 0, 0, 0, 0)));
			$this->glade->get_widget('result_plunder_real')->set_text($this->encode(sprintf($this->lang['RESULT_PLUNDER_REAL'], 0, 0, 0, 0)));
		}

		if($this->results['battle'][BATTLE_FLEET_DEFENDER])
		{
			$result_battle .= sprintf($this->lang['RESULT_BATTLE_DEFENDER'], round($this->results['battle'][BATTLE_FLEET_DEFENDER] * 100/$this->simulations));
		}

		if($this->results['battle'][BATTLE_DRAW])
		{
			if($this->results['battle'][BATTLE_FLEET_ATTACKER] || $this->results['battle'][BATTLE_FLEET_DEFENDER])
				$result_battle .= "\n";

			$result_battle .= sprintf($this->lang['RESULT_BATTLE_DRAW'], round($this->results['battle'][BATTLE_DRAW] * 100/$this->simulations));
		}

		$result_battle .= sprintf($this->lang['RESULT_BATTLE_ROUNDS'], round(round(array_sum($this->results['rounds'])/sizeof($this->results['rounds']))));
		$this->glade->get_widget("label_result_battle")->set_text($this->encode($result_battle));

		$this->show_ships_results();

		$this->glade->get_widget("moon_chance")->set_text(round(array_sum($this->results['moon_chance']) / sizeof($this->results['moon_chance'])) . '%');

		$debris_metal = round(array_sum($debris['metal'])/sizeof($debris['metal']));
		$debris_crystal = round(array_sum($debris['crystal'])/sizeof($debris['crystal']));

		$this->glade->get_widget('label_result_debris')->set_text($this->encode(sprintf($this->lang['RESULT_DEBRIS'],
			$this->number_format($debris_metal),
			$this->number_format($debris_crystal),
			$this->number_format(ceil(($debris_metal + $debris_crystal) / vars::$params[SHIP_RECYCLER]['capacity']))
		)));

		$this->glade->get_widget("result_lose_attacker")->set_text($this->encode(sprintf($this->lang['RESULT_LOSE_RESOURCES'],
			$this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['metal']))),
			$this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])))
		)));

		$this->glade->get_widget("result_lose_defender")->set_text($this->encode(sprintf($this->lang['RESULT_LOSE_RESOURCES'],
			$this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['metal']))),
			$this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])))
		)));

		$this->glade->get_widget("debris_adv_a_m_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])));
		$this->glade->get_widget("debris_adv_a_c_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])));
		$this->glade->get_widget("debris_adv_a_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_ATTACKER]['metal']) + min($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])));

		$this->glade->get_widget("debris_adv_a_m_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['metal']))));
		$this->glade->get_widget("debris_adv_a_c_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['crystal']))));
		$this->glade->get_widget("debris_adv_a_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])) + round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['crystal']))));

		$this->glade->get_widget("debris_adv_a_m_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])));
		$this->glade->get_widget("debris_adv_a_c_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])));
		$this->glade->get_widget("debris_adv_a_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_ATTACKER]['metal']) + max($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])));

		$this->glade->get_widget("debris_adv_d_m_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])));
		$this->glade->get_widget("debris_adv_d_c_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])));
		$this->glade->get_widget("debris_adv_d_min")->set_text($this->number_format(min($debris['total'][BATTLE_FLEET_DEFENDER]['metal']) + min($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])));

		$this->glade->get_widget("debris_adv_d_m_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['metal']))));
		$this->glade->get_widget("debris_adv_d_c_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['crystal']))));
		$this->glade->get_widget("debris_adv_d_avg")->set_text($this->number_format(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])) + round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['crystal']))));

		$this->glade->get_widget("debris_adv_d_m_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])));
		$this->glade->get_widget("debris_adv_d_c_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])));
		$this->glade->get_widget("debris_adv_d_max")->set_text($this->number_format(max($debris['total'][BATTLE_FLEET_DEFENDER]['metal']) + max($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])));

		$this->glade->get_widget("calculate_time")->set_text($this->encode(sprintf($this->lang['CALCULATE_TIME'],
			round(array_sum($calculate_time) / count($calculate_time), 2),
			round(max($calculate_time), 2),
			round(min($calculate_time), 2)
		)));
	}

	function simulate_missile_attack()
	{
		$source = $target = array();

		foreach(vars::get_resources('defense') as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			$target[$element] = (int) $this->glade->get_widget("defense_$element")->get_text();
		}
		$source[MISSILE_INTERPLANETARY] = (int) $this->glade->get_widget("missile_" . MISSILE_INTERPLANETARY)->get_text();
		$source[TECH_MILITARY] = (int) $this->glade->get_widget("addition_a_" . TECH_MILITARY)->get_text();
		$target[TECH_DEFENCE] = (int) $this->glade->get_widget("addition_d_" . TECH_DEFENCE)->get_text();

		list($source, $target) = missiles_attack($source, $target, $this->prime_target);

		foreach(vars::get_resources('defense') as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			$this->glade->get_widget("label_d_$element")->set_text((int) $target[$element]);
		}
	}

	function results_to_slot()
	{
		foreach(vars::get_resources('fleet') as $element)
		{
			if($this->glade->get_widget("ship_d_$element"))
			{
				$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
				$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts)) : 0;

				$this->glade->get_widget("ship_d_$element")->set_text($count);
			}
		}

		foreach(vars::get_resources('defense') as $element)
		{
			if($this->glade->get_widget("defense_$element"))
			{
				$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
				$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts)) : 0;

				$this->glade->get_widget("defense_$element")->set_text($count);
			}
		}
	}

	function change_prime_target($object)
	{
		list(,, $this->prime_target) = explode('_', $object->name);
		$this->prime_target = (int) $this->prime_target;
	}

	function import_spy_report_show()
	{
		$this->glade->get_widget('window_spy_report')->show();
	}

	function import_spy_report_hide()
	{
		$this->glade->get_widget('window_spy_report')->hide();
	}

	function import_spy_report()
	{
		global $root_path;

		$this->import_spy_report_hide();

		$spy_buffer = $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter());
		if(WIN_HOST)
		{
			$spy_buffer = iconv('cp1251', 'UTF-8', $spy_buffer);
		}
		
		$lang = array();
		require($root_path . "lib/coldzone/lang/tech.php");
		$matches = array();
		if(preg_match("/доклад с (?:планеты|луны) (.+) \[(\d):(\d{1,3}):(\d{1,2})\]/Uui", $spy_buffer, $matches))
		{
			if(WIN_HOST)
			{
				$matches[1] = iconv('UTF-8', 'cp1251', $matches[1]);
			}

			$this->glade->get_widget('target_name')->set_text($matches[1]);
			$this->glade->get_widget('target_galaxy')->set_text((int) $matches[2]);
			$this->glade->get_widget('target_system')->set_text((int) $matches[3]);
			$this->glade->get_widget('target_planet')->set_text((int) $matches[4]);
		}

		if(preg_match("/Металл\s+([\d\.]+)\s+Кристалл\s+([\d\.]+)[\s\n+]Дейтерий\s+([\d\.]+)/ui", $spy_buffer, $matches))
		{
			$this->glade->get_widget('target_metal')->set_text((int) str_replace('.', '', $matches[1]));
			$this->glade->get_widget('target_crystal')->set_text((int) str_replace('.', '', $matches[2]));
			$this->glade->get_widget('target_deuterium')->set_text((int) str_replace('.', '', $matches[3]));
		}

		foreach(array(TECH_MILITARY, TECH_SHIELD, TECH_DEFENCE, RPG_ADMIRAL) as $element)
		{
			if(preg_match("/" . $lang['TECH'][$element] . "\s+([\d\.]+)/ui", $spy_buffer, $matches))
			{
				$this->glade->get_widget('addition_d_' . $element)->set_text((int) $matches[1]);
			}
		}

		foreach(vars::get_resources('fleet') as $element)
		{
			if(preg_match("/" . $lang['TECH'][$element] . "\s+([\d\.]+)/u", $spy_buffer, $matches))
			{
				$this->glade->get_widget("ship_d_$element")->set_text((int) $matches[1]);
			}
			else
			{
				$this->glade->get_widget("ship_d_$element")->set_text(0);
			}
		}

		foreach(vars::get_resources('defense') as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			if(preg_match("/" . $lang['TECH'][$element] . "\s+([\d\.]+)/u", $spy_buffer, $matches))
			{
				$this->glade->get_widget("defense_$element")->set_text((int) $matches[1]);
			}
			else
			{
				$this->glade->get_widget("defense_$element")->set_text(0);
			}
		}
	}

	function show_ships_results()
	{
		foreach(vars::get_resources('fleet') as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), $this->precission) : 0;
			$min = sizeof($counts) ? min($counts) : 0;
			$max = sizeof($counts) ? max($counts) : 0;

			if($this->glade->get_widget("label_a_$element"))
			{
				$this->glade->get_widget("label_a_$element")->set_text($this->number_format($count, $this->precission));
			}

			if($this->glade->get_widget("adv_a_a_$element"))
			{
				$this->glade->get_widget("adv_a_a_$element")->set_text($this->number_format($min, $this->precission));
			}

			if($this->glade->get_widget("adv_a_b_$element"))
			{
				$this->glade->get_widget("adv_a_b_$element")->set_text($this->number_format($max, $this->precission));
			}

			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), $this->precission) : 0;
			$min = sizeof($counts) ? min($counts) : 0;
			$max = sizeof($counts) ? max($counts) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($this->number_format($count, $this->precission));
			}

			if($this->glade->get_widget("adv_d_a_$element"))
			{
				$this->glade->get_widget("adv_d_a_$element")->set_text($this->number_format($min, $this->precission));
			}

			if($this->glade->get_widget("adv_d_b_$element"))
			{
				$this->glade->get_widget("adv_d_b_$element")->set_text($this->number_format($max, $this->precission));
			}
		}

		foreach(vars::get_resources('defense') as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), $this->precission) : 0;
			$min = sizeof($counts) ? min($counts) : 0;
			$max = sizeof($counts) ? max($counts) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($this->number_format($count, $this->precission));
			}

			if($this->glade->get_widget("adv_d_a_$element"))
			{
				$this->glade->get_widget("adv_d_a_$element")->set_text($this->number_format($min, $this->precission));
			}

			if($this->glade->get_widget("adv_d_b_$element"))
			{
				$this->glade->get_widget("adv_d_b_$element")->set_text($this->number_format($max, $this->precission));
			}
		}

		if($this->results['battle'][BATTLE_FLEET_ATTACKER])
		{
			$this->glade->get_widget('result_plunder_real')->set_text($this->encode(sprintf($this->lang['RESULT_PLUNDER_REAL'],
				$this->number_format($this->results['steal'][$this->acs_slot]['metal']),
				$this->number_format($this->results['steal'][$this->acs_slot]['crystal']),
				$this->number_format($this->results['steal'][$this->acs_slot]['deuterium']),
				($this->results['plunder']['metal'] + $this->results['plunder']['crystal'] + $this->results['plunder']['deuterium']) ? round((($this->results['steal'][$this->acs_slot]['metal'] + $this->results['steal'][$this->acs_slot]['crystal'] + $this->results['steal'][$this->acs_slot]['deuterium']) / ($this->results['plunder']['metal'] + $this->results['plunder']['crystal'] + $this->results['plunder']['deuterium']) * 2) * 100, 1) : 0
			)));
		}
	}

	function clear_results()
	{
		$this->results = array(
			'battle' => array(
				BATTLE_FLEET_ATTACKER	=> 0,
				BATTLE_FLEET_DEFENDER	=> 0,
				BATTLE_DRAW		=> 0,
			),
			'rounds'	=> array(),
			'steal'		=> array('total' => array('metal' => 0, 'crystal' => 0, 'deuterium' => 0)),
			'max_resources'	=> array('total' => 0),
			'plunder'	=> array('metal' => 0, 'crystal' => 0, 'deuterium' => 0),
		);

		for($fleet_type = BATTLE_FLEET_ATTACKER; $fleet_type != BATTLE_DRAW; $fleet_type = ($fleet_type == BATTLE_FLEET_ATTACKER ? BATTLE_FLEET_DEFENDER : BATTLE_DRAW))
		{
			for($acs_slot = 0; $acs_slot < 16; $acs_slot++)
			{
				foreach(vars::get_resources('fleet') as $element)
				{
					$this->results['ships'][$fleet_type][$acs_slot]['ships'][$element] = array();
				}
			}
		}
	}

	function results_to_clipboard()
	{
		$elements_ary = array(
			array('label61', 'label_result_battle'),
			array('label55', 'moon_chance'),
			array('label54', 'label_result_debris'),
			array('label56', 'result_lose_attacker'),
			array('label57', 'result_lose_defender'),
			array('label58', 'result_plunder'),
			array('label59', 'result_plunder_real'),
			array('label83', 'fleet_deuterium'),
			array('label84', 'fleet_time'),
			array('label143', 'calculate_time'),
		);
			
		$clipboard = new GtkClipboard($this->glade->get_widget('window_main')->get_display(),
						Gdk::atom_intern('CLIPBOARD')); 

		$results = '';
		foreach($elements_ary as $elements)
		{
			$results .= str_replace("\n", ' ', $this->glade->get_widget($elements[0])->get_text()) . "\t" . $this->glade->get_widget($elements[1])->get_text() . "\n";
		}
		$clipboard->set_text($results);
		$clipboard->store();
	}

	function files_open_dialog()
	{
		$window = $this->glade->get_widget('window_files');

		$this->glade->get_widget('files_combobox')->set_active(0);
		$this->files_filter_changed($this->glade->get_widget('files_combobox'));

		if(!empty($this->files_action_signal))
			$this->glade->get_widget('files_action_button')->disconnect($this->files_action_signal);

		if(!empty($this->files_choose_signal))
			$window->disconnect($this->files_choose_signal);

		$this->glade->get_widget('files_action_button')->set_label($this->encode('Открыть'));
		$this->files_action_signal = $this->glade->get_widget('files_action_button')->connect('clicked', array($this, 'open_data'));

		$window->reparent($this->glade->get_widget('window_main'));
		$window->set_action(Gtk::FILE_CHOOSER_ACTION_OPEN);
		$this->files_choose_signal = $window->connect('file-activated', array($this, 'open_data'));
		$window->show();
	}

	function files_save_dialog()
	{
		$window = $this->glade->get_widget('window_files');

		$this->glade->get_widget('files_combobox')->set_active(0);
		$this->files_filter_changed($this->glade->get_widget('files_combobox'));

		if(!empty($this->files_action_signal))
			$this->glade->get_widget('files_action_button')->disconnect($this->files_action_signal);

		if(!empty($this->files_choose_signal))
			$window->disconnect($this->files_choose_signal);

		$this->glade->get_widget('files_action_button')->set_label($this->encode('Сохранить'));
		$this->files_action_signal = $this->glade->get_widget('files_action_button')->connect('clicked', array($this, 'save_data'));
		//$this->files_action_signal = $this->glade->get_widget('files_action_button')->connect('activate', array($this, 'save_data'));

		$window->reparent($this->glade->get_widget('window_save'));
		$window->set_action(Gtk::FILE_CHOOSER_ACTION_SAVE);
		$this->files_choose_signal = $window->connect('file-activated', array($this, 'save_data'));
		$window->show();
	}

	function hide_files_dialog()
	{
		$this->window_hide($this->glade->get_widget('window_files'));
	}

	function show_save_options()
	{
		$this->glade->get_widget('window_save')->show();
	}

	function hide_save_options()
	{
		$this->window_hide($this->glade->get_widget('window_save'));
	}

	function files_filter_changed($filter_combo)
	{
		$index = $filter_combo->get_active();

		$this->file_filter = new GtkFileFilter();
		switch($index)
		{
			case 1:
				$this->file_filter->add_pattern("*");
				break;
			case 0:
			default:
				$this->file_filter->add_pattern("*.csim");
				break;
		}

		$this->glade->get_widget('window_files')->set_filter($this->file_filter);
	}

	function open_data()
	{
		$filename = $this->glade->get_widget('window_files')->get_filename();

		if(file_exists($filename))
		{
			if($data = @file_get_contents($filename))
			{
				if($data = @base64_decode($data))
				{
					if($data = @unserialize($data))
					{
						if(is_array($data) && isset($data['version']) && $data['version'] >= 0.3)
						{
							$this->results = $data['results'];
							$this->fleets = $data['fleets'];

							if($data['version'] >= 1.1)
							{
								$this->glade->get_widget("target_galaxy")->set_text((int) $data['target']['galaxy']);
								$this->glade->get_widget("target_system")->set_text((int) $data['target']['system']);
								$this->glade->get_widget("target_planet")->set_text((int) $data['target']['planet']);

								$this->glade->get_widget("source_galaxy")->set_text((int) $data['source']['galaxy']);
								$this->glade->get_widget("source_system")->set_text((int) $data['source']['system']);
								$this->glade->get_widget("source_planet")->set_text((int) $data['source']['planet']);
							}

							$this->store_current_acs();
							$this->show_ships_results();
						}
					}
				}
			}
		}

		$this->window_hide($this->glade->get_widget('window_files'));
	}

	function save_data()
	{
		$this->store_current_acs("acs_combobox", true);

		$filename = $this->glade->get_widget('window_files')->get_filename();

		if($this->glade->get_widget('files_combobox')->get_active() == 0 && substr(strrchr($filename, '.'), 1) != 'csim')
		{
			$filename = $filename . '.csim';
		}

		if(file_exists($filename))
		{
			if(!$this->confirm_box("Подтверждение", "Перезаписать файл " . basename($filename) . "?", $this->glade->get_widget('window_files')))
			{
				return;
			}
		}

		$data = array(
			'version'	=> VERSION,
			'results'	=> $this->results,
			'fleets'	=> $this->fleets,
			'target'	=> array(
					'galaxy'	=> (int) $this->glade->get_widget("target_galaxy")->get_text(),
					'system'	=> (int) $this->glade->get_widget("target_system")->get_text(),
					'planet'	=> (int) $this->glade->get_widget("target_planet")->get_text(),
			),
			'source'	=> array(
					'galaxy'	=> (int) $this->glade->get_widget("source_galaxy")->get_text(),
					'system'	=> (int) $this->glade->get_widget("source_system")->get_text(),
					'planet'	=> (int) $this->glade->get_widget("source_planet")->get_text(),
			),
		);

		if(!$this->glade->get_widget('save_results')->get_active())
		{
			$data['results'] = array();
		}

		if(!$this->glade->get_widget('save_fleet_a')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_ATTACKER] as $acs_slot => $slot_data)
			{
				$data['fleets'][BATTLE_FLEET_ATTACKER][$acs_slot]['fleet'] = array();
			}
		}

		if(!$this->glade->get_widget('save_fleet_d')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_DEFENDER] as $acs_slot => $slot_data)
			{
				$data['fleets'][BATTLE_FLEET_DEFENDER][$acs_slot]['fleet'] = array();
			}
		}

		if(!$this->glade->get_widget('save_tech_a')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_ATTACKER] as $acs_slot => $slot_data)
			{
				foreach(vars::get_resources('tech') as $element)
				{
					if(isset($data['fleets'][BATTLE_FLEET_ATTACKER][$acs_slot]['data'][vars::$db_fields[$element]]))
						unset($data['fleets'][BATTLE_FLEET_ATTACKER][$acs_slot]['data'][vars::$db_fields[$element]]);
				}
			}
		}

		if(!$this->glade->get_widget('save_tech_d')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_DEFENDER] as $acs_slot => $slot_data)
			{
				foreach(vars::get_resources('tech') as $element)
				{
					if(isset($data['fleets'][BATTLE_FLEET_DEFENDER][$acs_slot]['data'][vars::$db_fields[$element]]))
						unset($data['fleets'][BATTLE_FLEET_DEFENDER][$acs_slot]['data'][vars::$db_fields[$element]]);
				}
			}
		}

		if(!$this->glade->get_widget('save_officiers_a')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_ATTACKER] as $acs_slot => $slot_data)
			{
				foreach(vars::get_resources('officier') as $element)
				{
					if(isset($data['fleets'][BATTLE_FLEET_ATTACKER][$acs_slot]['data'][vars::$db_fields[$element]]))
						unset($data['fleets'][BATTLE_FLEET_ATTACKER][$acs_slot]['data'][vars::$db_fields[$element]]);
				}
			}
		}

		if(!$this->glade->get_widget('save_officiers_d')->get_active())
		{
			foreach($data['fleets'][BATTLE_FLEET_DEFENDER] as $acs_slot => $slot_data)
			{
				foreach(vars::get_resources('officier') as $element)
				{
					if(isset($data['fleets'][BATTLE_FLEET_DEFENDER][$acs_slot]['data'][vars::$db_fields[$element]]))
						unset($data['fleets'][BATTLE_FLEET_DEFENDER][$acs_slot]['data'][vars::$db_fields[$element]]);
				}
			}
		}

		@file_put_contents($filename, base64_encode(serialize($data)));

		$this->window_hide($this->glade->get_widget('window_files'));
		$this->hide_save_options();
	}

	function advanced_show()
	{
		$this->glade->get_widget('window_advanced')->show();
	}

	function advanced_hide()
	{
		$this->glade->get_widget('window_advanced')->hide();
	}

	function select_region($object)
	{
		$object->select_region(0, -1);
	}

	function update_hide()
	{
		$this->glade->get_widget('window_update')->hide();
	}

	function show_about()
	{
		$this->glade->get_widget("about_dialog")->run();
		$this->glade->get_widget("about_dialog")->hide();
	}

	function window_hide($object)
	{
		$object->hide();
		return true;
	}

	function settings_show()
	{
		$settings = $this->config->get_group('settings', true);
		foreach($settings as $setting)
		{
			if($this->glade->get_widget($setting))
			{
				switch(get_class($this->glade->get_widget($setting)))
				{
					case 'GtkCheckButton':
						$this->glade->get_widget($setting)->set_active($this->config->get_setting($setting)); 
						break;
					case 'GtkEntry':
						$this->glade->get_widget($setting)->set_text($this->config->get_setting($setting));
						break;
				}
			}
		}

		$this->glade->get_widget('window_settings')->show();
	}

	function settings_hide()
	{
		$settings = $this->config->get_group('settings', true);
		foreach($settings as $setting)
		{
			if($this->glade->get_widget($setting))
			{
				switch(get_class($this->glade->get_widget($setting)))
				{
					case 'GtkCheckButton':
						$this->config->set_setting($setting, $this->glade->get_widget($setting)->get_active()); 
						break;
					case 'GtkEntry':
						$this->config->set_setting($setting, $this->glade->get_widget($setting)->get_text());
						break;
				}
			}
		}

		return $this->window_hide($this->glade->get_widget('window_settings'));
	}

	function confirm_box($title, $text, $parent = null)
	{
		$dialog = new GtkDialog($this->encode($title), $parent);

		$label = new GtkLabel($this->encode($text));
		$dialog->vbox->pack_start($label);

		$dialog->add_buttons(array(
			Gtk::STOCK_YES, Gtk::RESPONSE_YES,
			Gtk::STOCK_NO, Gtk::RESPONSE_NO
		));
		
		$dialog->show_all();
		$response_id = $dialog->run();
		$dialog->destroy();

		switch($response_id)
		{
			case Gtk::RESPONSE_YES:
				return true;
				break;
			case Gtk::RESPONSE_NO:
				return false;
				break;
		}
	}

	
	function show_update()
	{
		$this->glade->get_widget('window_update')->show();
	}

	function check_update_manual()
	{
		$this->check_update();
	}

	function check_update($startup = false)
	{
		global $root_path;

		$this->glade->get_widget('current_version')->set_text(VERSION);
		if($startup && $this->config->get('update_check') > time() - 24*60*60)
		{
			return;
		}

		if($update_data = file_get_contents("http://coldsim.coldzone.ru/version.txt"))
		{
			list($latest_version, $release_info) = explode("\n", $update_data);

			$this->glade->get_widget('release_info')->set_markup('<u>' . $this->encode($release_info) . '</u>');
			$this->glade->get_widget('latest_version')->set_text($latest_version);
			$this->glade->get_widget('current_version')->set_text(VERSION);

			if(version_compare(VERSION, $latest_version, "<"))
			{
				$this->glade->get_widget('current_version')->modify_fg(Gtk::STATE_NORMAL, GdkColor::parse('#ff0000'));

				if($startup)
				{
					$this->show_update();
				}
			}
			else
			{
				$this->glade->get_widget('current_version')->modify_fg(Gtk::STATE_NORMAL, GdkColor::parse('#008800'));
			}

			$this->config->set('update_check', time());
		}
	}

	function release_url()
	{
		exec((WIN_HOST ? 'explorer.exe' : 'xdg-open') . ' ' . $this->glade->get_widget('release_info')->get_text());
	}

	function encode($string)
	{
		return WIN_HOST ? iconv('utf-8', 'cp1251', $string) : $string;
	}

	function number_format($number, $precission = 0)
	{
		if(is_int($number))
			$precission = 0;

		return $number ? number_format($number, $precission, ',', '.') : 0;
	}

	function store_position()
	{
		if($this->config->get_setting('store_position'))
		{
			$this->config->set('position', array_map(
				create_function('$num', 'return max(0, $num);'),
				implode(',', $this->glade->get_widget('window_main')->get_position())
				)
			);
		}
	}

	function quit_button_clicked()
	{
		$this->store_position();
		$this->main_quit();
	}

	function main_quit()
	{
		unset($this->config);
		Gtk::main_quit();
	}
}
?>