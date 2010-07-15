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
	private $fleets		= array(
		BATTLE_FLEET_ATTACKER		=> array(),
		BATTLE_FLEET_DEFENDER		=> array(),
	);
	private $simulations	= 20;
	private $results;

	function __construct($object)
	{
		$this->glade = &$object;
		$this->glade->get_widget('acs_combobox')->set_active(0);

		$this->clear_results();
	}

	function acs_changed($acs_combo, $params = array())
	{
		$this->store_current_acs();
		$this->acs_slot = (int) $acs_combo->get_active();
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

		$additions = array(TECH_MILITARY, TECH_SHIELD, TECH_DEFENCE, RPG_ADMIRAL);
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

		$this->show_ships_results();
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
		global $pricelist;

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
		$this->show_ships_results();

		if($this->results['battle'][BATTLE_FLEET_ATTACKER])
		{
			$this->glade->get_widget("label_r_a_prefix")->set_visible(true);
			$this->glade->get_widget("label_r_a_postfix")->set_visible(true);
			$this->glade->get_widget("label_r_a_percent")->set_visible(true);
			$this->glade->get_widget("label_r_a_percent")->set_text(round($this->results['battle'][BATTLE_FLEET_ATTACKER]/$this->simulations) * 100);
		}
		else
		{
			$this->glade->get_widget("label_r_a_prefix")->set_visible(false);
			$this->glade->get_widget("label_r_a_postfix")->set_visible(false);
			$this->glade->get_widget("label_r_a_percent")->set_visible(false);
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

	function show_ships_results()
	{
		global $reslist;

		foreach($reslist['fleet'] as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_ATTACKER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? floor(array_sum($counts)/sizeof($counts)) : 0;

			if($this->glade->get_widget("label_a_$element"))
			{
				$this->glade->get_widget("label_a_$element")->set_text($count);
			}

			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? floor(array_sum($counts)/sizeof($counts)) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($count);
			}
		}

		foreach($reslist['defense'] as $element)
		{
			$counts = isset($this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element]) ? $this->results['ships'][BATTLE_FLEET_DEFENDER][$this->acs_slot]['ships'][$element] : array();
			$count = sizeof($counts) ? floor(array_sum($counts)/sizeof($counts)) : 0;

			if($this->glade->get_widget("label_d_$element"))
			{
				$this->glade->get_widget("label_d_$element")->set_text($count);
			}
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