<?php
/**
*
* @package ColdSim
* @version $Id$
* @copyright (c) 2010 nE0sIghT
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

?>