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

function missiles_attack($source, $target, $prime_target = false)
{
	global $CombatCaps, $reslist, $pricelist;

	$prime_target = (int) array_search($prime_target, $reslist['defense']);
	if ($target[MISSILE_INTERCEPTOR] >= $source[MISSILE_INTERPLANETARY])
	{
		$target[MISSILE_INTERCEPTOR] = $target[MISSILE_INTERCEPTOR] - $source[MISSILE_INTERPLANETARY];
	}
	else
	{
		if ($target[MISSILE_INTERCEPTOR] > 0)
		{
			$source[MISSILE_INTERPLANETARY] = $source[MISSILE_INTERPLANETARY] - $target[MISSILE_INTERCEPTOR];
			$target[MISSILE_INTERCEPTOR] = 0;
		}
		
		$source_single_damage = $CombatCaps[MISSILE_INTERPLANETARY]['attack'] * (1 + ($source[TECH_MILITARY] / 10));
		$source_damage = $source[MISSILE_INTERPLANETARY] * $source_single_damage;
		for($next_target = 0, $current_target = $prime_target; $next_target < count($reslist['defense']); $next_target++, $current_target = $next_target)
		{
			if($prime_target == $current_target && $next_target != 0)
				continue;
			
			// Old cold-zone/xnova had broken MIP code, that can turn defence under zero
			// Ready to remove? nS
			if($target[$reslist['defense'][$current_target]] < 0)
				$target[$reslist['defense'][$current_target]] = 0;
			
			$target_single_hull	= (($pricelist[$reslist['defense'][$current_target]]['metal'] + $pricelist[$reslist['defense'][$current_target]]['crystal'])/10) * (1 + ($target[TECH_DEFENCE] / 10));
			$target_hull		= $target_single_hull * $target[$reslist['defense'][$current_target]];
			
			if($source_damage >= $target_hull)
			{
				$target[$reslist['defense'][$current_target]] = 0;
				$source_damage -= $target_hull;
			}
			elseif($source_damage >= $target_single_hull)
			{
				$target_count = floor($source_damage / $target_single_hull);
				$target[$reslist['defense'][$current_target]] -= $target_count;
				$source_damage -= $target_count * $target_single_hull;
			}
		}
	}

	return array($source, $target);
}

function get_ship_speed($element, $source)
{
	global $reslist, $pricelist;

	$speed = 0;
	if(!in_array($element, $reslist['fleet']))
	{
		return $speed;
	}

	switch($element)
	{
		case SHIP_TRANSPORT_SMALL:
			if ($source[TECH_IMPULSE_DRIVE] >= 5)
			{
				$speed  = $pricelist[$element]['speed2'] + (($pricelist[$element]['speed2'] * $source[TECH_IMPULSE_DRIVE]) * 0.2);
			}
			else
			{
				$speed  = $pricelist[$element]['speed']  + (($pricelist[$element]['speed'] * $source[TECH_COMBUSTION]) * 0.1);
			}
			break;
		case SHIP_TRANSPORT_BIG:
		case SHIP_HUNTER_LIGHT:
		case SHIP_RECYCLER:
		case SHIP_SPY:
			$speed = $pricelist[$element]['speed'] + (($pricelist[$element]['speed'] * $source[TECH_COMBUSTION]) * 0.1);
			break;
		case SHIP_HUNTER_HEAVY:
		case SHIP_CRUSHER:
		case SHIP_COLONIZER:
			$speed = $pricelist[$element]['speed'] + (($pricelist[$element]['speed'] * $source[TECH_IMPULSE_DRIVE]) * 0.2);
			break;
		case SHIP_BOMBER:
			if ($source[TECH_HYPERSPACE_DRIVE] >= 8)
			{
				$speed = $pricelist[$element]['speed2'] + (($pricelist[$element]['speed2'] * $source[TECH_HYPERSPACE_DRIVE]) * 0.3);
			}
			else
			{
				$speed = $pricelist[$element]['speed']  + (($pricelist[$element]['speed'] * $source[TECH_IMPULSE_DRIVE]) * 0.2);
			}
			break;
		case SHIP_LINKOR:
		case SHIP_DESTRUCTOR:
		case SHIP_DEATH_STAR:
		case SHIP_LINECRUSHER:
			$speed = $pricelist[$element]['speed'] + (($pricelist[$element]['speed'] * $source[TECH_HYPERSPACE_DRIVE]) * 0.3);
			break;
	}

	return floor($speed * (1 + $source[RPG_GENERAL] * 0.05 + $source[RPG_AIDEDECAMP] * 0.005));
}

function get_fleet_speed($fleet, $source)
{
	$speed = array();
	foreach ($fleet as $element => $count)
	{
		if($count > 0)
		{
			$speed[$element] = get_ship_speed($element, $source);
		}
	}

	return min($speed);
}

function get_ship_consumption($element, $source)
{
	global $pricelist;

	return ($source[TECH_IMPULSE_DRIVE] >= 5 && isset($pricelist[$element]['consumption2'])) ? $pricelist[$element]['consumption2'] : $pricelist[$element]['consumption'];
}

function get_fleet_consumption($fleet, $source, $duration, $distance, $fleet_speed, $mission = 0, $holding_time = 0, $stay_consumption = 0)
{
	$consumption = 0;

	foreach ($fleet as $element => $count)
	{
		if ($count > 0)
		{
			$ship_speed		= get_ship_speed($element, $source);
			$ship_basic_consumption	= get_ship_consumption($element, $source);
			$spd			= 35000 / ($duration - 10) * sqrt($distance * 10 / $ship_speed);
			$ships_consumption	= $ship_basic_consumption * $count;
			$consumption		+= $ships_consumption * $distance / 35000 * pow((($spd / 10) + 1), 2);

			if($mission == MISSION_STAY)
			{
				$consumption      += $count * $stay_consumption[$element] * $holding_time;
			}
		}
	}

	$consumption = round($consumption) + 1;

	return $consumption;
}

function get_distance ($galaxy_source, $galaxy_target, $system_source, $system_target, $planet_source, $planet_target)
{
	if (($galaxy_source - $galaxy_target) != 0)
	{
		$distance = abs($galaxy_source - $galaxy_target) * 20000;
	}
	else if (($system_source - $system_target) != 0)
	{
		$distance = abs($system_source - $system_target) * 5 * 19 + 2700;
	}
	else if (($planet_source - $planet_target) != 0)
	{
		$distance = abs($planet_source - $planet_target) * 5 + 1000;
	}
	else
	{
		$distance = 5;
	}

	return $distance;
}

function get_duration ($fleet_speed, $distance, $speed_factor = 10)
{
	return round(35000 / $speed_factor * sqrt($distance * 10 / $fleet_speed) + 10);
}

function secs2time ($data)
{
	$hours = floor(($data / (60 * 60)) % 24);
	$minutes = floor(($data / 60) % 60);
	$seconds = floor($data % 60);

	return array(sprintf("%02u", $hours), sprintf("%02u", $minutes), sprintf("%02u", $seconds));
}

?>