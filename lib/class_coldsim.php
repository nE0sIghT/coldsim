<?php
/**
*
* @package ColdSim
* @copyright (c) 2010 nE0sIghT
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
	private $glade		= null;
	private $acs_slot	= 0;
	private $fleet_factor	= 1;
	private $fleets		= array(
		BATTLE_FLEET_ATTACKER		=> array(),
		BATTLE_FLEET_DEFENDER		=> array(),
	);
	private $simulations	= 20;
	private $results;
	private $prime_target	= 0;
	private $spy_buffer;

	function __construct($object)
	{
		$this->glade = &$object;
		$this->glade->get_widget('acs_combobox')->set_active(0);
		$this->glade->get_widget('fleet_factor')->set_active(0);
		$this->spy_buffer = new GtkTextBuffer();
		$this->glade->get_widget('spy_report')->set_buffer($this->spy_buffer);

		$this->clear_results();
	}

	function acs_changed($acs_combo)
	{
		$this->store_current_acs();
		$this->acs_slot = (int) $acs_combo->get_active();
		$this->show_ships_results();
	}

	function fleet_factor_changed($fleet_factor_combo)
	{
		$factors = array(
			0	=> 1,
			1	=> 1.5,
		);
		$this->fleet_factor = $factors[$fleet_factor_combo->get_active()];
	}

	function store_current_acs()
	{
		global $reslist, $resource;

		$acs_combo = $this->glade->get_widget("acs_combobox");
		foreach($reslist['fleet'] as $element)
		{
			if($this->glade->get_widget("ship_a_$element"))
			{
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

		foreach($reslist['defense'] as $element)
		{
			if($this->glade->get_widget("defense_$element"))
			{
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
				$this->fleets[BATTLE_FLEET_ATTACKER][$this->acs_slot]['data'][$resource[$element]] = (int) $this->glade->get_widget("addition_a_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("addition_a_$element")->set_text($this->fleets[BATTLE_FLEET_ATTACKER][(int) $acs_combo->get_active()]['data'][$resource[$element]]);
				}
				else
				{
					$this->glade->get_widget("addition_a_$element")->set_text(0);
				}
			}

			if($this->glade->get_widget("addition_d_$element"))
			{
				$this->fleets[BATTLE_FLEET_DEFENDER][$this->acs_slot]['data'][$resource[$element]] = (int) $this->glade->get_widget("addition_d_$element")->get_text();

				if(isset($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]))
				{
					$this->glade->get_widget("addition_d_$element")->set_text($this->fleets[BATTLE_FLEET_DEFENDER][(int) $acs_combo->get_active()]['data'][$resource[$element]]);
				}
				else
				{
					$this->glade->get_widget("addition_d_$element")->set_text(0);
				}
			}
		}

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

	function swap_fleet()
	{
		global $reslist;

		$tmp = array();
		foreach($reslist['fleet'] as $element)
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

	function simulate()
	{
		global $pricelist, $resource;

		$this->store_current_acs();
		$this->simulations = max(1, (int) $this->glade->get_widget("entry_simulations")->get_text());

		$this->clear_results();

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
			TECH_COMBUSTION		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][$resource[TECH_COMBUSTION]],
			TECH_IMPULSE_DRIVE	=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][$resource[TECH_IMPULSE_DRIVE]],
			TECH_HYPERSPACE_DRIVE	=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][$resource[TECH_HYPERSPACE_DRIVE]],
			RPG_GENERAL		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][$resource[RPG_GENERAL]],
			RPG_AIDEDECAMP		=> (int) $this->fleets[BATTLE_FLEET_ATTACKER][0]['data'][$resource[RPG_AIDEDECAMP]],
		);
		$fleet_speed = get_fleet_speed($this->fleets[BATTLE_FLEET_ATTACKER][0]['fleet'], $source);
		$distance = get_distance(
			(int) $this->glade->get_widget("source_galaxy")->get_text(),
			(int) $this->glade->get_widget("target_galaxy")->get_text(),
			(int) $this->glade->get_widget("source_system")->get_text(),
			(int) $this->glade->get_widget("target_system")->get_text(),
			(int) $this->glade->get_widget("source_planet")->get_text(),
			(int) $this->glade->get_widget("target_planet")->get_text()
		);
		$duration = get_duration($fleet_speed, $distance);
		$consumption = get_fleet_consumption ($this->fleets[BATTLE_FLEET_ATTACKER][0]['fleet'], $source, $duration, $distance, $fleet_speed);

		$duration = max(1, round($duration / $this->fleet_factor));
		list($fleet_hours, $fleet_minutes, $fleet_seconds) = secs2time($duration);

		$this->glade->get_widget("fleet_deuterium")->set_text($consumption);
		$this->glade->get_widget("fleet_hours")->set_text($fleet_hours);
		$this->glade->get_widget("fleet_minutes")->set_text($fleet_minutes);
		$this->glade->get_widget("fleet_seconds")->set_text($fleet_seconds);
		
		$battle = new Battle($this->fleets[BATTLE_FLEET_ATTACKER], $this->fleets[BATTLE_FLEET_DEFENDER]);
		for($i = 0; $i < $this->simulations; $i++)
		{
			$battle->calculate();

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
                                             
			$debris['metal'][] = $battle->debris['metal'];
			$debris['crystal'][] = $battle->debris['crystal'];
			$debris['total'][BATTLE_FLEET_ATTACKER]['metal'][] = $battle->debris['total'][BATTLE_FLEET_ATTACKER]['metal'];
			$debris['total'][BATTLE_FLEET_ATTACKER]['crystal'][] = $battle->debris['total'][BATTLE_FLEET_ATTACKER]['crystal'];
			$debris['total'][BATTLE_FLEET_DEFENDER]['metal'][] = $battle->debris['total'][BATTLE_FLEET_DEFENDER]['metal'];
			$debris['total'][BATTLE_FLEET_DEFENDER]['crystal'][] = $battle->debris['total'][BATTLE_FLEET_DEFENDER]['crystal'];
		}

		$this->results['plunder']['metal'] = (int) $this->glade->get_widget("target_metal")->get_text();
		$this->results['plunder']['crystal'] = (int) $this->glade->get_widget("target_crystal")->get_text();
		$this->results['plunder']['deuterium'] = (int) $this->glade->get_widget("target_deuterium")->get_text();

		if($this->results['battle'][BATTLE_FLEET_ATTACKER])
		{
			$this->glade->get_widget("label_r_a_prefix")->set_visible(true);
			$this->glade->get_widget("label_r_a_postfix")->set_visible(true);
			$this->glade->get_widget("label_r_a_percent")->set_visible(true);
			$this->glade->get_widget("label_r_a_percent")->set_text(round($this->results['battle'][BATTLE_FLEET_ATTACKER]/$this->simulations) * 100);

			$this->glade->get_widget("plunder_t_metal")->set_text(floor($this->results['plunder']['metal'] / 2));
			$this->glade->get_widget("plunder_t_crystal")->set_text(floor($this->results['plunder']['crystal'] / 2));
			$this->glade->get_widget("plunder_t_deuterium")->set_text(floor($this->results['plunder']['deuterium'] / 2));
			$this->glade->get_widget("plunder_t_cargo")->set_text(ceil((floor($this->results['plunder']['metal'] / 2) + floor($this->results['plunder']['crystal'] / 2) + floor($this->results['plunder']['deuterium'] / 2)) / $pricelist[SHIP_TRANSPORT_BIG]['capacity']));

			foreach ($this->results['ships'][BATTLE_FLEET_ATTACKER] as $fleet_id => $ships)
			{
				$this->results['max_resources'][$fleet_id] = 0;
				$this->results['steal'][$fleet_id] = array('metal' => 0, 'crystal' => 0, 'deuterium' => 0);

				foreach($ships['ships'] as $element => $counts)
				{
					$count = sizeof($counts) ? (int) round(array_sum($counts)/sizeof($counts), 1) : 0;
					$this->results['max_resources'][$fleet_id] += (int) ($pricelist[$element]['capacity'] * $count);
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
			$this->glade->get_widget("label_r_a_prefix")->set_visible(false);
			$this->glade->get_widget("label_r_a_postfix")->set_visible(false);
			$this->glade->get_widget("label_r_a_percent")->set_visible(false);

			$this->glade->get_widget("plunder_t_metal")->set_text(0);
			$this->glade->get_widget("plunder_t_crystal")->set_text(0);
			$this->glade->get_widget("plunder_t_deuterium")->set_text(0);
			$this->glade->get_widget("plunder_t_cargo")->set_text(0);

			$this->glade->get_widget("plunder_r_metal")->set_text(0);
			$this->glade->get_widget("plunder_r_crystal")->set_text(0);
			$this->glade->get_widget("plunder_r_deuterium")->set_text(0);
			$this->glade->get_widget("plunder_r_boot")->set_text(0);
		}

		if($this->results['battle'][BATTLE_FLEET_DEFENDER])
		{
			$this->glade->get_widget("label_r_d_prefix")->set_visible(true);
			$this->glade->get_widget("label_r_d_postfix")->set_visible(true);
			$this->glade->get_widget("label_r_d_percent")->set_visible(true);
			$this->glade->get_widget("label_r_d_percent")->set_text(round($this->results['battle'][BATTLE_FLEET_DEFENDER]/$this->simulations) * 100);
		}
		else
		{
			$this->glade->get_widget("label_r_d_prefix")->set_visible(false);
			$this->glade->get_widget("label_r_d_postfix")->set_visible(false);
			$this->glade->get_widget("label_r_d_percent")->set_visible(false);
		}

		if($this->results['battle'][BATTLE_DRAW])
		{
			$this->glade->get_widget("label_r_w_prefix")->set_visible(true);
			$this->glade->get_widget("label_r_w_postfix")->set_visible(true);
			$this->glade->get_widget("label_r_w_percent")->set_visible(true);
			$this->glade->get_widget("label_r_w_percent")->set_text(round($this->results['battle'][BATTLE_DRAW]/$this->simulations) * 100);
		}
		else
		{
			$this->glade->get_widget("label_r_w_prefix")->set_visible(false);
			$this->glade->get_widget("label_r_w_postfix")->set_visible(false);
			$this->glade->get_widget("label_r_w_percent")->set_visible(false);
		}

		$this->show_ships_results();

		$this->glade->get_widget("label_r_r_prefix")->set_visible(true);
		$this->glade->get_widget("label_r_r_postfix")->set_visible(true);
		$this->glade->get_widget("label_r_r_rounds")->set_visible(true);
		$this->glade->get_widget("label_r_r_rounds")->set_text(round(array_sum($this->results['rounds'])/sizeof($this->results['rounds'])));

		$this->glade->get_widget("moon_chance")->set_text(round($battle->moon_chance));

		$debris_metal = round(array_sum($debris['metal'])/sizeof($debris['metal']));
		$debris_crystal = round(array_sum($debris['crystal'])/sizeof($debris['crystal']));

		$this->glade->get_widget("debris_metal")->set_text($debris_metal);
		$this->glade->get_widget("debris_crystal")->set_text($debris_crystal);
		$this->glade->get_widget("debris_recyclers")->set_text(ceil(($debris_metal + $debris_crystal) / $pricelist[SHIP_RECYCLER]['capacity']));

		$this->glade->get_widget("lose_a_metal")->set_text(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['metal'])));
		$this->glade->get_widget("lose_a_crystal")->set_text(round(array_sum($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_ATTACKER]['crystal'])));

		$this->glade->get_widget("lose_d_metal")->set_text(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['metal'])));
		$this->glade->get_widget("lose_d_crystal")->set_text(round(array_sum($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])/sizeof($debris['total'][BATTLE_FLEET_DEFENDER]['crystal'])));
	}

	function simulate_missile_attack()
	{
		global $reslist;

		$source = $target = array();

		foreach($reslist['defense'] as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			$target[$element] = (int) $this->glade->get_widget("defense_$element")->get_text();
		}
		$source[MISSILE_INTERPLANETARY] = (int) $this->glade->get_widget("missile_" . MISSILE_INTERPLANETARY)->get_text();
		$source[TECH_MILITARY] = (int) $this->glade->get_widget("addition_a_" . TECH_MILITARY)->get_text();
		$target[TECH_DEFENCE] = (int) $this->glade->get_widget("addition_d_" . TECH_DEFENCE)->get_text();

		list($source, $target) = missiles_attack($source, $target, $this->prime_target);

		foreach($reslist['defense'] as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			$this->glade->get_widget("label_d_$element")->set_text((int) $target[$element]);
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
		global $root_path, $reslist;

		$this->import_spy_report_hide();

		$lang = array();
		require($root_path . "lib/coldzone/lang/tech.php");
		$matches = array();
		if(preg_match("/доклад с (.+) \[(\d):(\d{1,3}):(\d{1,2})\]/Uui", $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter()), $matches))
		{
			$this->glade->get_widget('target_name')->set_text($matches[1]);
			$this->glade->get_widget('target_galaxy')->set_text((int) $matches[2]);
			$this->glade->get_widget('target_system')->set_text((int) $matches[3]);
			$this->glade->get_widget('target_planet')->set_text((int) $matches[4]);
		}

		if(preg_match("/Металл\s+([\d\.]+)\s+Кристалл\s+([\d\.]+)[\s\n+]Дейтерий\s+([\d\.]+)/ui", $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter()), $matches))
		{
			$this->glade->get_widget('target_metal')->set_text((int) str_replace('.', '', $matches[1]));
			$this->glade->get_widget('target_crystal')->set_text((int) str_replace('.', '', $matches[2]));
			$this->glade->get_widget('target_deuterium')->set_text((int) str_replace('.', '', $matches[3]));
		}

		foreach(array(TECH_MILITARY, TECH_SHIELD, TECH_DEFENCE, RPG_ADMIRAL) as $element)
		{
			if(preg_match("/" . $lang['tech'][$element] . "\s+([\d\.]+)/ui", $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter()), $matches))
			{
				$this->glade->get_widget('addition_d_' . $element)->set_text((int) $matches[1]);
			}
		}

		foreach($reslist['fleet'] as $element)
		{
			if(preg_match("/" . $lang['tech'][$element] . "\s+([\d\.]+)/ui", $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter()), $matches))
			{
				$this->glade->get_widget("ship_d_$element")->set_text((int) $matches[1]);
			}
			else
			{
				$this->glade->get_widget("ship_d_$element")->set_text(0);
			}
		}

		foreach($reslist['defense'] as $element)
		{
			if($element == MISSILE_INTERPLANETARY)
				continue;

			if(preg_match("/" . $lang['tech'][$element] . "\s+([\d\.]+)/ui", $this->spy_buffer->get_text($this->spy_buffer->get_start_iter(), $this->spy_buffer->get_end_iter()), $matches))
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
		global $reslist;

		foreach($reslist['fleet'] as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), 1) : 0;

			if($this->glade->get_widget("label_a_$element"))
			{
				$this->glade->get_widget("label_a_$element")->set_text($count);
			}

			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), 1) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($count);
			}
		}

		foreach($reslist['defense'] as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? round(array_sum($counts)/sizeof($counts), 1) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($count);
			}
		}

		if($this->results['battle'][BATTLE_FLEET_ATTACKER])
		{
			$this->glade->get_widget("plunder_r_metal")->set_text($this->results['steal'][$this->acs_slot]['metal']);
			$this->glade->get_widget("plunder_r_crystal")->set_text($this->results['steal'][$this->acs_slot]['crystal']);
			$this->glade->get_widget("plunder_r_deuterium")->set_text($this->results['steal'][$this->acs_slot]['deuterium']);
			$this->glade->get_widget("plunder_r_boot")->set_text(($this->results['plunder']['metal'] + $this->results['plunder']['crystal'] + $this->results['plunder']['deuterium']) ? round((($this->results['steal'][$this->acs_slot]['metal'] + $this->results['steal'][$this->acs_slot]['crystal'] + $this->results['steal'][$this->acs_slot]['deuterium']) / ($this->results['plunder']['metal'] + $this->results['plunder']['crystal'] + $this->results['plunder']['deuterium']) * 2) * 100, 1) : 0);
		}
	}

	function clear_results()
	{
		global $reslist;

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
				foreach($reslist['fleet'] as $element)
				{
					$this->results['ships'][$fleet_type][$acs_slot]['ships'][$element] = array();
				}
			}
		}
	}

	function select_region($object)
	{
		$object->select_region(0, -1);
	}

	function update_check()
	{
		$this->glade->get_widget('window_update')->show();
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

	function main_quit()
	{
		Gtk::main_quit();
	}
}
?>