<?php
/**
*
* @package Cold Zone
* @copyright (c) 2009-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

if (!defined('IN_GAME'))
{
	exit;
}                                                       

ini_set('max_execution_time', '120');

define('BATTLE_VERSION', 0.3);

define('BATTLE_DRAW', -1);
define('BATTLE_FLEET_ATTACKER', 0);
define('BATTLE_FLEET_DEFENDER', 1);
define('BATTLE_PRECISION',	100000);
define('BATTLE_MAX_ACCURACY',	200);

define('BATTLE_LOG_CONSOLE',	0);
define('BATTLE_LOG_FILE',	1);

class Battle extends Report
{
	public $original_fleet		= array(); // [fleet_type][fleet_id][element] = count
	public $fleet			= array(); // [fleet_type][fleet_id][element] = count
	public $tech			= array(); // [fleet_type][fleet_id][tech] = level
	public $fleet_count		= array(); // [fleet_type][fleet_id] = count
						   // [fleet_type]['total']  = count
	public $fleet_count_real	= array(); // dynamic

	public $shield			= array(); // [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
	public $damage_percent		= array(); // [fleet_type][fleet_id][element] = percent

	public $round			= 0;
	public static $max_rounds	= 6;

	public $round_info		= array();
	public $users			= array(); // [fleet_type][fleet_id]['username'] = username;
						   // [fleet_type][fleet_id]['coords'] = coords;
	public $winner			= -1;
	public $first_round_loose	= array(); // index = fleet_id
	public $debris			= array(
						'total' => array(
							BATTLE_FLEET_ATTACKER => array(
								'metal' => 0,
								'crystal' => 0),
							BATTLE_FLEET_DEFENDER => array(
								'metal' => 0,
								'crystal' => 0)
						),
						'nondefence' => array(
							BATTLE_FLEET_DEFENDER => array(
								'metal' => 0,
								'crystal' => 0)
						),
						'metal' => 0,
						'crystal' => 0
	);
	public $moon_chance		= 0;
	public $errors			= array();
	private static $debug		= false;
	public $logs			= array();
	private static $log_type	= BATTLE_LOG_FILE;
	private static $log_file	= './class.battle.log';

	function __construct($attacker, $defender)
	{
		if(self::$debug)
		{
			global $lang;

			$this->log('Класс боя создан');
			$this->log('Cold Zone версия ' . VERSION);
			$this->log('Модуль боя версия ' . BATTLE_VERSION);
			$this->log('Номера кораблей:');
			foreach(vars::get_resources('fleet') as $fleet_id)
			{
				$this->log($fleet_id . ': ' . $lang['TECH'][$fleet_id]);
			}
			$this->log('Точность брони и щитов: ' . BATTLE_PRECISION);
		}

		if(!empty($attacker))
			$this->fill_fleet($attacker, BATTLE_FLEET_ATTACKER);

		if(!empty($defender))
			$this->fill_fleet($defender, BATTLE_FLEET_DEFENDER);
	}

	function __destruct()
	{
		if(self::$debug)
		{
			$this->log('Очистка данных...', false);
		}

		unset($this->fleet, $this->shield);

		if(self::$debug)
		{
			$this->log('Ok');
		}
	}

	function calculate()
	{

		if(self::$debug)
		{
			$this->log('Подготовка расчета...');
		}

		if(!$this->ready())
		{
			return false;
		}

		if($this->round !== 0)
			$this->clear();

		if(self::$debug)
		{
			$this->log('Копирование флота...', false);
		}

		$this->fleet = $this->original_fleet;

		if(self::$debug)
		{
			$this->log('Ok');
		}

		if(self::$debug)
		{
			$this->log('Начинаю расчет...');
		}

		for($this->round = 0; $this->round < self::$max_rounds; $this->round++)
		{
			if(self::$debug)
			{
				$this->log('Раунд ' . $this->round);
			}

			$this->get_fleet_count();

			if($this->battle_ended())
				break;

			$this->calculate_round();

			if($this->round == 0)
			{
				$this->check_first_round_loosers();
			}

		}

		$this->get_fleet_count();
		if($this->round == self::$max_rounds)
		{
			$this->battle_ended();
		}

		$this->report_add_fleet();
		$this->restore_defence();

		if(self::$debug)
		{
			$this->log('Расчет поля обломков...');
		}

		$this->debris['metal'] = round(0.3 * ($this->debris['total'][BATTLE_FLEET_ATTACKER]['metal'] + $this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['metal']));
		$this->debris['crystal'] = round(0.3 * ($this->debris['total'][BATTLE_FLEET_ATTACKER]['crystal'] + $this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['crystal']));

		if(self::$debug)
		{
			$this->log($this->debris);
		}

		if(self::$debug)
		{
			$this->log('Расчет шанса на луну...', false);
		}

		$this->moon_chance = ($this->debris['metal'] + $this->debris['crystal']) / 100000;
		if($this->moon_chance > 20)
			$this->moon_chance = 20;

		if(self::$debug)
		{
			$this->log($this->moon_chance);
		}
	}

	private function battle_ended()
	{
		if(self::$debug)
		{
			$this->log('Проверка на конец битвы...', false);
		}

		if($this->fleet_count[BATTLE_FLEET_ATTACKER]['total'] === 0 || $this->fleet_count[BATTLE_FLEET_DEFENDER]['total'] === 0)
		{
			if(self::$debug)
			{
				$this->log('Да');
			}

			if($this->fleet_count[BATTLE_FLEET_ATTACKER]['total'])
			{
				$this->winner = BATTLE_FLEET_ATTACKER;
			}
			else if($this->fleet_count[BATTLE_FLEET_DEFENDER]['total'])
			{
				$this->winner = BATTLE_FLEET_DEFENDER;
			}
			return true;
		}
		else
		{
			if(self::$debug)
			{
				$this->log('Нет');
			}
			return false;
		}
	}

	private function calculate_round()
	{
		if(self::$debug)
		{
			$this->log('Расчет раунда...');
		}

		$this->report_add_fleet();

		$this->regenerate_shield();

		$this->get_damage_percent();

		$this->fleet_attack(BATTLE_FLEET_ATTACKER);
		$this->fleet_attack(BATTLE_FLEET_DEFENDER);

		$this->remove_broken_ships();
		$this->fleet_recalculate();

		if(self::$debug)
		{
			$this->log('Раунд расчитан');
		}
	}

	private function restore_defence()
	{
		if(self::$debug)
		{
			$this->log('Восстановление обороны...');
		}

		// [fleet_type][fleet_id][element] = count
		foreach($this->original_fleet[BATTLE_FLEET_DEFENDER] as $fleet_id => $fleet)
		{
			foreach($fleet as $element => $count)
			{
				if(in_array($element, vars::get_resources('defense')))
				{
					if(!in_array($element, array(MISSILE_INTERCEPTOR, MISSILE_INTERPLANETARY)))
					{
						$broken = $count - (isset($this->fleet[BATTLE_FLEET_DEFENDER][$fleet_id][$element]) ? $this->fleet[BATTLE_FLEET_DEFENDER][$fleet_id][$element] : 0);

						if($broken)
						{
							$chance = mt_rand(60, 80)/100;
							$rand = mt_rand();

							if((pow(1 - $chance, $broken) * mt_getrandmax()) < $rand)
							{
								$restore = round($chance * $broken);

								if($restore)
								{
									if(!isset($this->fleet[BATTLE_FLEET_DEFENDER][$fleet_id][$element]))
										$this->fleet[BATTLE_FLEET_DEFENDER][$fleet_id][$element] = 0;
									$this->fleet[BATTLE_FLEET_DEFENDER][$fleet_id][$element] += $restore;
								}
							}
						}
					}
				}
			}
		}
	}

	private function fleet_attack($source_type)
	{
		if(self::$debug)
		{
			$this->log('Атакуют флоты ' . ($source_type == BATTLE_FLEET_ATTACKER ? 'нападающих' : 'обороняющихся'));
		}

		$target_type = $this->get_inverted_type($source_type);

		$source = array();
		foreach($this->fleet[$source_type] as $source['fleet_id'] => $source['ships']) // [fleet_type][fleet_id][element] = count
		{
			if(self::$debug)
			{
				$this->log('Атакует флот ' . $source['fleet_id']);
			}

			foreach($source['ships'] as $source['element'] => $source['element_count'])
			{
				// Variables names are ugly
				$rapidfire = $this->fleet_group_attack($source, $target_type);

				if(self::$debug)
				{
					$this->log('Скорострел...');
				}
				while($rapidfire)
				{
					$data = array();
					foreach($rapidfire as $rapidfire_source)
					{
						if($tmp = $this->fleet_group_attack($rapidfire_source, $target_type))
						{
							$data[] = $tmp;
						}
					}

					$rapidfire = array();
					foreach($data as $tmp)
					{
						foreach($tmp as $tmp2)
						{
							$rapidfire[] = $tmp2;
						}
					}
				}

				if(self::$debug)
				{
					$this->log('Скорострел закончен');
				}
			}
		}
	}

	private function fleet_group_attack($source, $target_type)
	{
		if(self::$debug)
		{
			$this->log('Атакуют корабли: ' . $source['element']);
		}

		$source_type = $this->get_inverted_type($target_type);
		$targets = $this->get_target_elements($target_type, $source['element_count']);

		//file_put_contents('log', 'Memory usage: ' . memory_get_peak_usage(true) . "\n", FILE_APPEND);
		$target = $rapidfire = array();

		foreach($targets as $target['fleet_id'] => $target['ships']) // [fleet_id][element] = source_count
		{
			if(!$this->fleet_count_real[$target_type][$target['fleet_id']])
				continue;

			if(self::$debug)
			{
				$this->log('Нападение на флот ' . $target['fleet_id']);
			}

			foreach($target['ships'] as $target['element'] => $source['total_count'])
			{
				if(!$source['total_count'] || !$this->fleet_count_real[$target_type][$target['fleet_id']])
					continue;

				if(self::$debug)
				{
					$this->log('Нападение на корабли ' . $target['element']);
				}

				if(self::$debug)
				{
					$this->log('Расчет параметров...');
				}

				// [fleet_type][fleet_id][tech] = level
				$source['bonus_attack']		= (1 + (0.1 * ($this->tech[$source_type][$source['fleet_id']]['attack']) + (0.05 * $this->tech[$source_type][$source['fleet_id']]['rpg_admiral'])));
				$source['single_attack']	= vars::$battle_caps[$source['element']]['attack'] * $source['bonus_attack'];

				$target['bonus_hull']		= (1 + (0.1 * ($this->tech[$target_type][$target['fleet_id']]['defence']) + (0.05 * $this->tech[$target_type][$target['fleet_id']]['rpg_admiral'])));
				$target['bonus_shield']		= (1 + (0.1 * ($this->tech[$target_type][$target['fleet_id']]['shield']) + (0.05 * $this->tech[$target_type][$target['fleet_id']]['rpg_admiral'])));

				$target['single_hull']		= ((vars::$params[$target['element']]['metal'] + vars::$params[$target['element']]['crystal']) / 10) * $target['bonus_hull'];
				$target['single_shield']	= vars::$battle_caps[$target['element']]['shield'] * $target['bonus_shield'];

				if(self::$debug)
				{
					$this->log('Атака нападающего: ' . $source['single_attack']);
					$this->log('Защита обороняющегося: ' . $target['single_hull']);
					$this->log('Щиты обороняющегося: ' . $target['single_shield']);
				}

				$targets_hull = $this->get_target_elements_by_hull($target_type, $target, $source['total_count']); // [hull_percent] = source_count
				$perform_rapidfire = false;

				foreach($targets_hull as $hull_percent => $source['hull_count'])
				{
					if(!$this->fleet_count_real[$target_type][$target['fleet_id']])
						continue;

					if(self::$debug)
					{
						$this->log('Выбраны ' . $source['hull_count'] . ' кораблей с процентом брони ' . $hull_percent);
					}

					// It's posible to hit already broken targets. Waste turn so
					if($hull_percent && $source['hull_count'] && $this->is_alive($target_type, $target, $hull_percent))
					{
						$perform_rapidfire = true;
						$targets_shield = $this->get_target_elements_by_shield($target_type, $target, $source['hull_count'], $hull_percent);

						foreach($targets_shield as $shield_percent => $source['count'])
						{
							$this->report_add_attack($source_type, $source['single_attack'] * $source['count'], $source['count']);
							$target['count'] = $this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent];

							$this->fleet_perform_attack($target_type, $source, $target, $hull_percent, $shield_percent);
						}

						unset($targets_shield);
					}
					else if($source['hull_count'])
					{
						$perform_rapidfire = true;
					}
					else
					{
						if(self::$debug)
						{
							$this->log('Пропускаем атаку.');
						}
					}
				}

				if($perform_rapidfire)
				{
					//unset($target['bonus_hull'], $target['bonus_shield'], $target['single_hull'], $target['single_shield'], $targets_hull, $perform_rapidfire);
					if($tmp = $this->fleet_rapidfire($source_type, $source, $target['element']))
					{
						$rapidfire[] = $tmp;
					}
				}
			}
		}

		return $rapidfire;
	}

	private function is_alive($target_type, $target, $hull_percent, $shield_percent = false)
	{
		$alive_by_hull = isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent]);
		if($shield_percent === false || $alive_by_hull === false)
		{
			return $alive_by_hull;
		}
		else
		{
			return $alive_by_hull && isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]);
		}
	}

	private function fleet_perform_attack($target_type, $source, $target, $hull_percent, $shield_percent)
	{
		if($target['count'] == 0 || $source['count'] == 0)
			return;

		if(self::$debug)
		{
			$this->log('Производим атаку...');
		}

		// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
		//$target['count'] = $this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent];
		$target['real_shield'] 	= $target['single_shield'] * $shield_percent / BATTLE_PRECISION;
		$target['real_hull']	= $target['single_hull'] * $hull_percent / BATTLE_PRECISION;

		if(self::$debug)
		{
			$this->log('Щиты цели: ' . $target['real_shield']);
			$this->log('Броня цели: ' . $target['real_hull']);
		}

		if(self::$debug)
		{
			$this->log('Проверка на правило неуязвимости...', false);
		}

		//Ineffectiveness rule
		if($source['single_attack'] > $target['real_shield'] * 0.01)
		{
			/*if($target_type == BATTLE_FLEET_ATTACKER)
			{
				echo $source['element'] . '<br />';
				print_r($target);
				print_r($source);
				echo '<hr /><br />';
			}*/
			if(self::$debug)
			{
				$this->log('Нет');
			}

			$attacks = $source['count'] / $target['count'];
			$full_attacks 	= floor($attacks);

			if(!$full_attacks)
			{
				$full_attacks = 1;
				$target['count'] = $source['count'];
			}
//echo $source['element'] . ' ' . $target['element'] . '<br />' . $source['count']  . ' ' . $target['count'] . '<br />' . ($source['single_attack'] * $full_attacks) . '<br />' . $target['real_shield'] . '<br /><br />';
			if($source['single_attack'] * $full_attacks <= $target['real_shield'])
			{
				$shield_new_percent = BATTLE_PRECISION * (($target['real_shield'] - $source['single_attack'] * $full_attacks) / $target['single_shield']);

				$this->lower_shield($target_type, $target, $hull_percent, $shield_percent, $shield_new_percent);

				$shield_percent = $shield_new_percent;

				$this->report_add_shield($target_type, $source['single_attack'] * $full_attacks * $target['count']);
			}
			else
			{
				$shield_new_percent = 0;

				if($source['single_attack'] * $full_attacks < $target['real_shield'] + $target['real_hull'])
					$hull_new_percent = BATTLE_PRECISION * (($target['real_hull'] - ($source['single_attack'] * $full_attacks - $target['real_shield'])) / $target['single_hull']);
				else
					$hull_new_percent = 0;

				$this->lower_shield($target_type, $target, $hull_percent, $shield_percent, $shield_new_percent);
				$this->lower_hull($target_type, $target, $hull_percent, $hull_new_percent, $shield_new_percent);

				$shield_percent = $shield_new_percent;
				$hull_percent = $hull_new_percent;

				$this->report_add_shield($target_type, $target['real_shield'] * $target['count']);
			}

			if(!$hull_percent)
				return;
			else
				$this->fleet_explode($target_type, $target, $shield_percent, $hull_percent, $full_attacks);

			if($attacks > 1)
			{
				$source['count'] -= $target['count']*$full_attacks;
				$target['count'] = isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]) ? $this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent] : 0;
				$this->fleet_perform_attack($target_type, $source, $target, $hull_percent, $shield_percent);
			}
		}
		else
		{
			if(self::$debug)
			{
				$this->log('Да');
			}
		}
	}
								
	private function lower_shield($target_type, $target, $hull_percent, $shield_percent, $shield_new_percent)
	{
		if(!isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_new_percent]))
			$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_new_percent] = 0;

		$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_new_percent]	+= $target['count'];
		$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]		-= $target['count'];

		if($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent] == 0)
			unset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]);
	}

	private function lower_hull($target_type, $target, $hull_percent, $hull_new_percent, $shield_percent)
	{
		if($hull_new_percent == 0)
		{
			$this->fleet_count_real[$target_type][$target['fleet_id']] -= $target['count'];
			$this->fleet_count_real[$target_type]['total'] -= $target['count'];
		}
		
		if(!isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_new_percent][$shield_percent]))
			$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_new_percent][$shield_percent] = 0;

		$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_new_percent][$shield_percent]	+= $target['count'];
		$this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]		-= $target['count'];

		if($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent] == 0)
			unset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent][$shield_percent]);
		if(empty($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent]))
			unset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent]);
	}

	private function fleet_explode($fleet_type, $target, $shield_percent, $hull_percent, $attacks = 1)
	{
		if(self::$debug)
		{
			$this->log('Проверка шанса на взрыв...');
		}

		if($hull_percent < 0.7 * BATTLE_PRECISION)
		{
			$chance = (1 - ($hull_percent / BATTLE_PRECISION));
			$total_count = $target['count'];
			for($i = 0; $i < $attacks; $i++)
			{
				if($total_count == 0)
					break;

				$rand = mt_rand();
				if($target['count'] > BATTLE_MAX_ACCURACY)
				{
					if((pow(1 - $chance, $target['count'])) * mt_getrandmax() < $rand)
					{
						$rand = mt_rand(90, 110) / 100;
						$explode_count = round($target['count'] * $chance * $rand);
						if($explode_count > $total_count)
							$explode_count = $total_count;
	
						$target['count'] = $explode_count;
						$total_count -= $target['count'];
						if($target['count'])
						{
							if(self::$debug)
							{
								$this->log('Взорваны ' . $target['count'] . ' кораблей');
							}
	
							// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
							$this->lower_hull($fleet_type, $target, $hull_percent, 0, $shield_percent);
						}
						$target['count'] = $total_count;
					}
				}
				else
				{
					$explode_count = 0;
					for($j = 0; $j < $target['count']; $j++)
					{
						$rand = mt_rand();
						if($chance * mt_getrandmax() >= $rand)
						{
							$explode_count++;
						}
					}
	
					$target['count'] = $explode_count;
					$total_count -= $target['count'];
					if($target['count'])
					{
						if(self::$debug)
						{
							$this->log('Взорваны ' . $target['count'] . ' кораблей');
						}
						
						// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
						$this->lower_hull($fleet_type, $target, $hull_percent, 0, $shield_percent);
					}
					$target['count'] = $total_count;
				}
			}
		}
		if(self::$debug)
		{
			$this->log('Ок');
		}
	}

	private function fleet_rapidfire($source_type, $source, $target_element)
	{
		if(!isset(vars::$battle_caps[$source['element']]['sd'][$target_element]) || !vars::$battle_caps[$source['element']]['sd'][$target_element])
		{
			$this->error('Не задан скорострел юнита ' . $source['element'] . ' по юниту ' . $target_element . '. Принят 1');
			vars::$battle_caps[$source['element']]['sd'][$target_element] = 1;
		}
		$chance = (double)(((int)vars::$battle_caps[$source['element']]['sd'][$target_element] - 1) / (int)vars::$battle_caps[$source['element']]['sd'][$target_element]);

		if($chance > 0.0)
		{
			//file_put_contents('log', 'Rapidfire ' . $source['element'] . ' on ' . $target_element . ' with chance ' . $chance, FILE_APPEND);
			if($source['total_count'] > BATTLE_MAX_ACCURACY)
			{
				//file_put_contents('log', ' | massive', FILE_APPEND);
				$rand = mt_rand();
				if((pow(1 - $chance, $source['total_count'])) * mt_getrandmax() < $rand)
				{
					$rand = mt_rand(85, 115) / 100;
					if(($source_count = round($source['total_count'] * $chance * $rand)) > $source['total_count'])
						$source_count = $source['total_count'];
					$source['total_count'] = $source_count;
					//file_put_contents('log', " | success\n", FILE_APPEND);
					//unset($chance, $rand, $source_count);
					return array(
						'fleet_id'	=> $source['fleet_id'],
						'element'	=> $source['element'],
						'element_count'	=> $source['total_count'],
					);
					//$this->fleet_group_attack($source, $this->get_inverted_type($source_type));
				}
				//else
					//file_put_contents('log', " | failed\n", FILE_APPEND);
			}
			else
			{
				$real_count = 0;
				for($i = 0; $i < $source['total_count']; $i++)
				{
					$rand = mt_rand();
					if($chance * mt_getrandmax() >= $rand)
					{
						$real_count++;
					}
				}

				//file_put_contents('log', " | single with count $real_count\n", FILE_APPEND);
				if($real_count)
				{
					$source['total_count'] = $real_count;
					//unset($chance, $rand, $real_count);
					return array(
						'fleet_id'	=> $source['fleet_id'],
						'element'	=> $source['element'],
						'element_count'	=> $source['total_count'],
					);
					//$this->fleet_group_attack($source, $this->get_inverted_type($source_type));
				}
			}
		}

		return array();
	}

	private function get_inverted_type($fleet_type)
	{
		return $fleet_type === BATTLE_FLEET_ATTACKER ? BATTLE_FLEET_DEFENDER : BATTLE_FLEET_ATTACKER;
	}

	private function get_damage_percent()
	{
		$this->damage_percent = array();
		foreach($this->fleet as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $count)
				{
					$this->damage_percent[$fleet_type][$fleet_id][$element] = ($count / $this->fleet_count[$fleet_type][$fleet_id]) * ($this->fleet_count[$fleet_type][$fleet_id] / $this->fleet_count[$fleet_type]['total']);
				}
			}
		}
	}

	private function get_fleet_count()
	{
		if(self::$debug)
		{
			$this->log('Считаем кол-во кораблей...');
		}

		$this->fleet_count = array();
		$this->fleet_count[BATTLE_FLEET_ATTACKER]['total'] = $this->fleet_count[BATTLE_FLEET_DEFENDER]['total'] = 0;
		foreach($this->fleet as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $count)
				{
					if(!isset($this->fleet_count[$fleet_type][$fleet_id]))
						$this->fleet_count[$fleet_type][$fleet_id] = 0;

					$this->fleet_count[$fleet_type][$fleet_id] 	+= $count;
					$this->fleet_count[$fleet_type]['total']	+= $count;
				}
			}
		}

		$this->fleet_count_real = $this->fleet_count;
		if(self::$debug)
		{
			$this->log($this->fleet_count);
		}
	}

	private function get_target_elements($target_type, $source_count)
	{
		if(self::$debug)
		{
			$this->log('Считаем шанс на попадание по кол-ву кораблей...');
		}

		$return = array();
		$left = $source_count;

		if($source_count > BATTLE_MAX_ACCURACY)
		{
			foreach($this->damage_percent[$target_type] as $fleet_id => $ships) // [fleet_type][fleet_id][element] = percent
			{
				foreach($ships as $element => $percent)
				{
					$return[$fleet_id][$element]	 = round($percent * $source_count);
	
					if($return[$fleet_id][$element] > $left)
					{
						$return[$fleet_id][$element] 	= $left;
						$left = 0;
					}
					else
						$left			-= $return[$fleet_id][$element];
				}
			}
		}
		else
		{
			for($i = 0; $i < $left; $i++)
			{
				$rand = mt_rand();
				$chance = 0;
				foreach($this->damage_percent[$target_type] as $fleet_id => $ships) // [fleet_type][fleet_id][element] = percent
				{
					foreach($ships as $element => $percent)
					{
						$chance += $percent;

						if($chance * mt_getrandmax() >= $rand)
						{
							if(!isset($return[$fleet_id][$element]))
								$return[$fleet_id][$element] = 1;
							else
								$return[$fleet_id][$element]++;
							$left--;
							break 2;
						}
					}
				}
			}
		}

		if($left)
		{
			$rand = mt_rand();
			$chance = 0;
			foreach($this->damage_percent[$target_type] as $fleet_id => $ships) // [fleet_type][fleet_id][element] = percent
			{
				foreach($ships as $element => $percent)
				{
					$chance += $percent;

					if($chance * mt_getrandmax() >= $rand)
					{
						if(!isset($return[$fleet_id][$element]))
							$return[$fleet_id][$element] = 0;

						$return[$fleet_id][$element] += $left;
						break 2;
					}
				}
			}
		}

		if(self::$debug)
		{
			$this->log($return);
			$this->log('Ок');
		}

		return $return;
	}

	private function get_target_elements_by_hull($target_type, $target, $source_count)
	{
		$return = array();

		if(self::$debug)
		{
			$this->log('Считаем шанс на попадание по броне...');
		}

		if($source_count)
		{
			$left = $source_count;

			$percents = array();
			$target_total_count = $this->get_hull_elements_count($target_type, $target);

			if($source_count < $target_total_count && $source_count <= BATTLE_MAX_ACCURACY)
			{
				$target_percent = array();
				foreach($this->shield[$target_type][$target['fleet_id']][$target['element']] as $hull_percent => $shield) // [fleet_type][fleet_id][element][hull_percent] = count
				{
					$target_percent[$hull_percent] = 0;
					foreach($shield as $shield_percent => $count)
						$target_percent[$hull_percent] += $count;

					$target_percent[$hull_percent] = $target_percent[$hull_percent] / $target_total_count;
				}

				for($i = 0; $i < $source_count; $i++)
				{
					$chance = 0;
					$rand = mt_rand();

					foreach($target_percent as $hull_percent => $percent) // [fleet_type][fleet_id][element][hull_percent] = count
					{
						$chance += $percent;
						if($chance * mt_getrandmax() >= $rand)
						{
							if(!isset($return[$hull_percent]))
								$return[$hull_percent] = 0;
	
							$return[$hull_percent] += 1;
							$left -= 1;
							break;
						}
					}
				}
			}
			else
			{
				foreach($this->shield[$target_type][$target['fleet_id']][$target['element']] as $hull_percent => $shield) // [fleet_type][fleet_id][element][hull_percent] = count
				{
					$hull_count = 0;
					foreach($shield as $shield_percent => $count)
						$hull_count += $count;
	
					if($hull_count)
					{
						$return[$hull_percent]	 	= round(($hull_count/$target_total_count) * $source_count);
	
						if($return[$hull_percent] > $left)
						{
							$return[$hull_percent] 	= $left;
							$left = 0;
						}
						else
							$left			-= $return[$hull_percent];
	
						$percents[$hull_percent] = $hull_count / $target_total_count;
					}
				}
			}

			if($left)
			{
				$chance = 0;
				$rand = mt_rand();
				foreach($percents as $hull_percent => $percent) // [fleet_type][fleet_id][element][hull_percent] = count
				{
					$chance += $percent;

					if($chance * mt_getrandmax() >= $rand)
					{
						if(!isset($return[$hull_percent]))
							$return[$hull_percent] = 0;

						$return[$hull_percent] += $left;
						break;
					}
				}
			}
		}

		if(self::$debug)
		{
			$this->log($return);
			$this->log('Ок');
		}

		return $return;
	}

	private function get_target_elements_by_shield($target_type, $target, $source_count, $hull_percent)
	{
		$return = array();

		if(self::$debug)
		{
			$this->log('Считаем шанс на попадание по щитам...');
		}

		if($source_count)
		{
			$percents = array();
			$left = $source_count;
			$target_total_count = $this->get_shield_elements_count($target_type, $target, $hull_percent);

			if(!isset($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent]))
			{
				print_r($this->shield[$target_type][$target['fleet_id']]);
			}
			foreach($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent] as $shield_percent => $count) // [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
			{
				if($hull_percent)
				{
					$return[$shield_percent] 	= round(($count/$target_total_count) * $source_count);

					if($return[$shield_percent] > $left)
					{
						$return[$shield_percent] 	= $left;
						$left = 0;
					}
					else
						$left			-= $return[$shield_percent];

					$percents[$shield_percent] = $count / $target_total_count;
				}
			}

			if($left)
			{
				$chance = 0;
				$rand = mt_rand();
				foreach($percents as $shield_percent => $percent) // [fleet_type][fleet_id][element][hull_percent] = count
				{
					$chance += $percent;
					if($chance * mt_getrandmax() >= $rand)
					{
						if(!isset($return[$shield_percent]))
							$return[$shield_percent] = 0;

						$return[$shield_percent] += $left;
						break;
					}
				}
			}
		}

		if(self::$debug)
		{
			$this->log($return);
			$this->log('Ок');
		}

		return $return;
	}

	private function get_hull_elements_count($target_type, $target)
	{
		$return = 0;

		foreach($this->shield[$target_type][$target['fleet_id']][$target['element']] as $hull_percent => $shield) // [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
		{
			foreach($shield as $shield_percent => $count)
			{
				$return += $count;
			}
		}

		return $return;
	}

	private function get_shield_elements_count($target_type, $target, $hull_percent)
	{
		$return = 0;

		foreach($this->shield[$target_type][$target['fleet_id']][$target['element']][$hull_percent] as $shield_percent => $count) // [fleet_type][fleet_id][element][hull_percent] = count
		{
			if($hull_percent)
				$return += $count;
		}

		return $return;
	}

	private function regenerate_shield()
	{
		if(self::$debug)
		{
			$this->log('Восстанавливаем щиты...', false);
		}

		// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
		foreach($this->shield as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $hull)
				{
					foreach($hull as $hull_percent => $shield)
					{
						foreach($shield as $shield_percent => $count)
						{
							if($shield_percent != BATTLE_PRECISION)
							{
								if(!isset($this->shield[$fleet_type][$fleet_id][$element][$hull_percent][BATTLE_PRECISION]))
									$this->shield[$fleet_type][$fleet_id][$element][$hull_percent][BATTLE_PRECISION] = 0;

								$this->shield[$fleet_type][$fleet_id][$element][$hull_percent][BATTLE_PRECISION] += $count;
								unset($this->shield[$fleet_type][$fleet_id][$element][$hull_percent][$shield_percent]);
							}
						}
					}
				}
			}
		}
		if(self::$debug)
		{
			$this->log('Ok');
		}
	}

	private function remove_broken_ships()
	{
		if(self::$debug)
		{
			$this->log('Убираем поломаные корабли...');
		}

		// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
		foreach($this->shield as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $hull)
				{
					foreach($hull as $hull_percent => $shield)
					{
						if(!$hull_percent)
						{
							$total_count = 0;
							foreach($shield as $shield_percent => $count)
								$total_count += $count;
							$this->debris['total'][$fleet_type]['metal']	+= vars::$params[$element]['metal'] * $total_count;
							$this->debris['total'][$fleet_type]['crystal']	+= vars::$params[$element]['crystal'] * $total_count;

							if(!in_array($element, vars::get_resources('defense')) && $fleet_type == BATTLE_FLEET_DEFENDER)
							{
								$this->debris['nondefence'][$fleet_type]['metal']	+= vars::$params[$element]['metal'] * $total_count;
								$this->debris['nondefence'][$fleet_type]['crystal']	+= vars::$params[$element]['crystal'] * $total_count;
							}
							unset($this->shield[$fleet_type][$fleet_id][$element][$hull_percent]);
						}
					}
				}
			}
		}

		if(self::$debug)
		{
			$this->log('Ок');
		}
	}

	private function fleet_recalculate()
	{
		if(self::$debug)
		{
			$this->log('Пересчитываем все флоты...');
		}

		// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
		$this->fleet = array(
			BATTLE_FLEET_ATTACKER	=> array(),
			BATTLE_FLEET_DEFENDER	=> array(),
		);
		foreach($this->shield as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $hull)
				{
					foreach($hull as $hull_percent => $shied)
					{
						foreach($shied as $shied_percent => $count)
						{
							if(!isset($this->fleet[$fleet_type][$fleet_id][$element]))
								$this->fleet[$fleet_type][$fleet_id][$element] = 0;

							$this->fleet[$fleet_type][$fleet_id][$element] += $count;
						}
					}
				}
			}
		}

		if(self::$debug)
		{
			$this->log($this->fleet);
			$this->log('Ok');
		}
	}

	private function clear()
	{
		$this->round = 0;
		$this->fleet = $this->shield = array();

		foreach($this->original_fleet as $fleet_type => $fleet)
		{
			foreach($fleet as $fleet_id => $ships)
			{
				foreach($ships as $element => $count)
				{
					$this->shield[$fleet_type][$fleet_id][$element][BATTLE_PRECISION][BATTLE_PRECISION]	= $count;
				}
			}
		}
		$this->winner = BATTLE_DRAW;
		$this->damage_percent		= array();
		$this->fleet_count		= array();
		$this->first_round_loose	= array();
		$this->debris	= array(
					'total' => array(
						BATTLE_FLEET_ATTACKER => array(
							'metal' => 0,
							'crystal' => 0),
						BATTLE_FLEET_DEFENDER => array(
							'metal' => 0,
							'crystal' => 0)
					),
					'nondefence' => array(
						BATTLE_FLEET_DEFENDER => array(
							'metal' => 0,
							'crystal' => 0)
					),
					'metal' => 0,
					'crystal' => 0
		);
		$this->moon_chance		= 0;
		$this->logs		= array();
	}

	private function ready()
	{
		if(self::$debug)
		{
			$this->log('Проверяем готовность...', false);
		}

		if(!empty($this->original_fleet[BATTLE_FLEET_ATTACKER]) &&
			!empty($this->original_fleet[BATTLE_FLEET_DEFENDER]) &&
			!empty($this->tech[BATTLE_FLEET_ATTACKER]) &&
			!empty($this->tech[BATTLE_FLEET_DEFENDER])
		)
		{
			if(self::$debug)
			{
				$this->log('Ok');
			}
			return true;
		}
		else
		{
			if(self::$debug)
			{
				$this->log('Ошибка');
			}
			return false;
		}
	}

	private function fill_fleet($fleets, $fleet_type)
	{
		if(self::$debug)
		{
			$this->log('Заполняем флоты ' . ($fleet_type == BATTLE_FLEET_ATTACKER ? 'нападающих' : 'обороняющихся') . '...', false);
		}

		foreach ($fleets as $fleet_id => $ships)
		{
			if(!isset($this->original_fleet[$fleet_type][$fleet_id]))
				$this->original_fleet[$fleet_type][$fleet_id] = array();
			foreach ($ships['fleet'] as $element => $count)
			{
				if($count)
				{
					$this->original_fleet[$fleet_type][$fleet_id][$element] 	= $count;
					// [fleet_type][fleet_id][element][hull_percent][shield_percent] = count
					$this->shield[$fleet_type][$fleet_id][$element][BATTLE_PRECISION][BATTLE_PRECISION]	= $count;
				}
			}
			
			$this->tech[$fleet_type][$fleet_id]				= array(
				'attack'	=> $ships['data'][vars::$db_fields[TECH_MILITARY]],
				'defence'	=> $ships['data'][vars::$db_fields[TECH_DEFENCE]],
				'shield'	=> $ships['data'][vars::$db_fields[TECH_SHIELD]],
				'rpg_admiral'	=> $ships['data'][vars::$db_fields[RPG_ADMIRAL]],
			);
			$this->users[$fleet_type][$fleet_id]['username'] = $ships['data']['username'];
			if($fleet_type == BATTLE_FLEET_ATTACKER)
			{
				$this->users[$fleet_type][$fleet_id]['coords'] = $ships['data']['fleet_start_galaxy'] . ':' . $ships['data']['fleet_start_system'] . ':' . $ships['data']['fleet_start_planet'];
				$this->users[BATTLE_FLEET_DEFENDER][0]['coords'] = $ships['data']['fleet_end_galaxy'] . ':' . $ships['data']['fleet_end_system'] . ':' . $ships['data']['fleet_end_planet'];
			}
		}

		if(self::$debug)
		{
			$this->log('Сделано');
		}
	}

	private function check_first_round_loosers()
	{
		if(self::$debug)
		{
			$this->log('Проверяем проигравших в первом раунде...');
		}

		// [fleet_type][fleet_id][element] = count
		foreach($this->original_fleet[BATTLE_FLEET_ATTACKER] as $fleet_id => $fleet)
		{
			if(!isset($this->fleet[BATTLE_FLEET_ATTACKER][$fleet_id]))
			{
				$this->first_round_loose[] = $fleet_id;
			}
		}

		if(self::$debug)
		{
			$this->log($this->first_round_loose);
			$this->log('Ok');
		}
	}

	private function error($str)
	{
		$hash = md5($str);

		if(!isset($this->errors[$hash]))
			$this->errors[$hash] = $str;
	}

	private function log($str, $break_line = true)
	{
		if(in_array(gettype($str), array('object', 'array')))
		{
			$str = var_export($str, true);
		}

		if($break_line)
		{
			$str .= "\n";
		}

		if(self::$log_type == BATTLE_LOG_FILE)
		{
			file_put_contents(self::$log_file, $str, FILE_APPEND);
		}
		else
		{
			print_r($str);
		}
	}
}

class Report
{
	public $fleet		= array(); // [fleet_type][fleet_id][element] = count
	public $round		= 0;
	public $tech		= array();
	public $report		= array();
	public $users		= array();
	public $html		= '';

	protected function report_add_fleet()
	{
		$this->report[$this->round]['fleet'] = $this->fleet;
	}

	protected function report_add_attack($source_type, $source_attack, $attack_count)
	{
		if(!isset($this->report[$this->round]['attack'][$source_type]))
			$this->report[$this->round]['attack'][$source_type] = 0;

		if(!isset($this->report[$this->round]['attack_count'][$source_type]))
			$this->report[$this->round]['attack_count'][$source_type] = 0;

		$this->report[$this->round]['attack'][$source_type] += $source_attack;
		$this->report[$this->round]['attack_count'][$source_type] += $attack_count;
	}

	protected function report_add_shield($target_type, $target_shield)
	{
		if(!isset($this->report[$this->round]['shield'][$target_type]))
			$this->report[$this->round]['shield'][$target_type] = 0;

		$this->report[$this->round]['shield'][$target_type] += $target_shield;
	}

	public function generate_report($metal = 0, $crystal = 0, $deuterium = 0, $battle_time = 0, $is_moon = false, $moon_destroyed = false)
	{
		global $lang;

		$this->html = '<center>' . date('d M Y, в H:i:s', $battle_time ? $battle_time : time()) . ' во Вселенной ' . UNIVERSE . ' произошла битва между флотами.<br /><br />';
		foreach($this->report as $round => $data)
		{
			$this->html .= "Раунд " . ($round + 1) . "<br /><br />";
			foreach($data['fleet'] as $fleet_type => $fleet)
			{
				$this->html .= "<table>";
				foreach($fleet as $fleet_id => $ships)
				{
					$this->html .= "<tr><th>" . ($fleet_type == BATTLE_FLEET_ATTACKER ? "Атакуюший" : "Обороняющийся") . ": " . $this->users[$fleet_type][$fleet_id]['username'] . " [" . $this->users[$fleet_type][$fleet_id]['coords'] . "]<br />";
					$this->html .= "Воооружение: " . ($this->tech[$fleet_type][$fleet_id]['attack'] * 10) . "%; ";
					$this->html .= "Щиты: " . ($this->tech[$fleet_type][$fleet_id]['shield'] * 10) . "%; ";
					$this->html .= "Защита: " . ($this->tech[$fleet_type][$fleet_id]['defence'] * 10) . "%; ";
					$this->html .= "Адмирал: " . ($this->tech[$fleet_type][$fleet_id]['rpg_admiral']) . " уровень.";

					if(empty($ships))
						$this->html .= "<br />Уничтожен!</th></tr>";
					else
					{
						$row_ships = $row_count = $row_attack = $row_shield = $row_hull = '<tr>';
						$row_ships = "<table border='1' align='center'>" . $row_ships . "<th>Тип</th>";
						$row_count = $row_count . "<th>Количество</th>";
						$row_attack = $row_attack . "<th>Атака</th>";
						$row_shield = $row_shield . "<th>Щит</th>";
						$row_hull = $row_hull . "<th>Броня</th>";

						foreach($ships as $element => $count)
						{
							$bonus_attack		= (1 + (0.1 * ($this->tech[$fleet_type][$fleet_id]['attack']) + (0.05 * $this->tech[$fleet_type][$fleet_id]['rpg_admiral'])));
							$bonus_hull		= (1 + (0.1 * ($this->tech[$fleet_type][$fleet_id]['defence']) + (0.05 * $this->tech[$fleet_type][$fleet_id]['rpg_admiral'])));
							$bonus_shield		= (1 + (0.1 * ($this->tech[$fleet_type][$fleet_id]['shield']) + (0.05 * $this->tech[$fleet_type][$fleet_id]['rpg_admiral'])));

							$row_attack .= '<th>' . (function_exists('pretty_number') ? pretty_number(vars::$battle_caps[$element]['attack'] * $bonus_attack, false, 1) : vars::$battle_caps[$element]['attack'] * $bonus_attack) . '</th>';
							$row_shield .= '<th>' . (function_exists('pretty_number') ? pretty_number(vars::$battle_caps[$element]['shield'] * $bonus_shield, false) : vars::$battle_caps[$element]['shield'] * $bonus_shield) . '</th>';
							$row_hull .= '<th>' . (function_exists('pretty_number') ? pretty_number(((vars::$params[$element]['metal'] + vars::$params[$element]['crystal']) / 10) * $bonus_hull, false) : ((vars::$params[$element]['metal'] + vars::$params[$element]['crystal']) / 10) * $bonus_hull) . '</th>';

							$row_ships .= '<th>' . $lang['TECH'][$element] . '</th>';
							$row_count .= '<th>' . (function_exists('pretty_number') ?
											pretty_number($count)
										:
											$count
										) .
										(
											($round > 0 && (!isset($this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element]) || $count != $this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element])) ?
											(' <span style="font-size: 85%">(<span style="color: red">' . 
													((
														isset($this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element]) &&
														$count != $this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element]
													) ?
														(
															function_exists('pretty_number') ?
																pretty_number($count - $this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element]) :
																$count - $this->report[$round-1]['fleet'][$fleet_type][$fleet_id][$element]
														) :
														(
															-1 * $count
														))
												. '</span>)</span>') : ''
										) . '</th>';
						}
						$row_ships .= '</tr>';
						$row_count .= '</tr>';
						$this->html .= $row_ships . $row_count . $row_attack . $row_shield . $row_hull . "</table></th></tr>";
					}
				}
				$this->html .= '</table><br /><br />';
			}
			if($round != $this->round)
			{
				$this->html .= 'Нападающий делает ' . $this->report[$round]['attack_count'][BATTLE_FLEET_ATTACKER] . ' выстрел(ов) общей мощностью ' . (function_exists('pretty_number') ? pretty_number(round($this->report[$round]['attack'][BATTLE_FLEET_ATTACKER])) : round($this->report[$round]['attack'][BATTLE_FLEET_ATTACKER])) . '. Щиты обороняющегося ' . (isset($this->report[$round]['shield'][BATTLE_FLEET_DEFENDER]) ? 'поглощают ' . (function_exists('pretty_number') ? pretty_number(round($this->report[$round]['shield'][BATTLE_FLEET_DEFENDER])) : round($this->report[$round]['shield'][BATTLE_FLEET_DEFENDER])) . ' урона' : 'отражают весь урон') . '.<br />';
				$this->html .= 'Обороняющийся делает ' . $this->report[$round]['attack_count'][BATTLE_FLEET_DEFENDER] . ' выстрел(ов) общей мощностью ' . (function_exists('pretty_number') ? pretty_number(round($this->report[$round]['attack'][BATTLE_FLEET_DEFENDER])) : round($this->report[$round]['attack'][BATTLE_FLEET_DEFENDER])) . '. Щиты атакующего ' . (isset($this->report[$round]['shield'][BATTLE_FLEET_ATTACKER]) ? 'поглощают ' . (function_exists('pretty_number') ? pretty_number(round($this->report[$round]['shield'][BATTLE_FLEET_ATTACKER])) : round($this->report[$round]['shield'][BATTLE_FLEET_ATTACKER])) . ' урона' : 'отражают весь урон') . ' .<br /><br />';
			}
		}

		if($this->winner != BATTLE_DRAW)
		{
			$this->html .= (($this->winner == BATTLE_FLEET_ATTACKER) ? 'Нападающий' : 'Обороняющийся') . ' выиграл битву!<br />';
			$this->html .= ($this->winner == BATTLE_FLEET_ATTACKER) ? 'Он получает ' . $metal . ' металла, ' . $crystal . ' кристалла, ' . $deuterium . ' дейтерия.<br />' : '';
		}
		else
			$this->html .= 'Бой прошел в ничью!<br />';
		$this->html .= 'Нападающий потерял ' . (function_exists('pretty_number') ? pretty_number($this->debris['total'][BATTLE_FLEET_ATTACKER]['metal'] + $this->debris['total'][BATTLE_FLEET_ATTACKER]['crystal']) : $this->debris['total'][BATTLE_FLEET_ATTACKER]['metal'] + $this->debris['total'][BATTLE_FLEET_ATTACKER]['crystal']) . ' единиц.<br />';
		$this->html .= 'Обороняющийся потерял ' . (function_exists('pretty_number') ? pretty_number($this->debris['total'][BATTLE_FLEET_DEFENDER]['metal'] + $this->debris['total'][BATTLE_FLEET_DEFENDER]['crystal']) : $this->debris['total'][BATTLE_FLEET_DEFENDER]['metal'] + $this->debris['total'][BATTLE_FLEET_DEFENDER]['crystal']) . ' единиц (Без учета обороны: ' . (function_exists('pretty_number') ? pretty_number($this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['metal'] + $this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['crystal']) : $this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['metal'] + $this->debris['nondefence'][BATTLE_FLEET_DEFENDER]['crystal']) . ' единиц).' . '<br />';
		if(!$moon_destroyed)
			$this->html .= 'На орбите находится ' . (function_exists('pretty_number') ? pretty_number($this->debris['metal']) : $this->debris['metal']) . ' металла, ' . (function_exists('pretty_number') ? pretty_number($this->debris['crystal']) : $this->debris['crystal']) . ' кристалла.<br />';

		if(!$is_moon)
			$this->html .= 'Шанс появления луны составляет ' . $this->moon_chance . '%';
		else
			$this->html .= 'Луна ' . ($moon_destroyed ? '' : 'не') . ' была уничтожена';

		if(count($this->errors))
		{
			$this->html .= '<br /><br /><h2>Во время вычисления боя произошли следующие ошибки (пожалуйста, уведомите об этом Администратора):';
			$i = 0;
			foreach($this->errors as $error)
			{
				$this->html .= ++$i . ') ' . $error . '<br />';
			}
		}
		$this->html .= '<!--' . mt_rand(1000000, mt_getrandmax()) . '--></center>';

		return $this->html;
	}
}
?>
