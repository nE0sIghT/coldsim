<?php
/**
*
* @package Cold Zone
* @version $Id$
* @copyright (c) 2009-2011 Yuri nE0sIghT Konotopov, http://coldzone.ru
* @license GNU Affero General Public License, version 3 http://www.gnu.org/licenses/agpl-3.0.html
*
*/

if (!defined('IN_GAME'))
{
	exit;
}

// Game constants

define('MESSAGE_SPY', 		0);
define('MESSAGE_JOUER', 	1);
define('MESSAGE_ALLIANCE', 	2);
define('MESSAGE_ATTACK', 	3);
define('MESSAGE_EXPLOIT',	4);
define('MESSAGE_TRANSPORT', 	5);
define('MESSAGE_EXPEDITION', 	15);
define('MESSAGE_ADMIN', 	97);
define('MESSAGE_BUILDLIST', 	99);
define('MESSAGE_NEW',	 	100);

define('MISSION_ATTACK',	1);
define('MISSION_ATTACK_ALLY',	2);
define('MISSION_TRANSPORT',	3);
define('MISSION_LEAVE',		4);
define('MISSION_STAY',		5);
define('MISSION_SPY',		6);
define('MISSION_COLONY',	7);
define('MISSION_RECYCLE',	8);
define('MISSION_DESTROY',	9);
define('MISSION_MISSILES',	10);
define('MISSION_EXPEDITION',	15);


define('BUILDING_METALL_MINE', 		1);
define('BUILDING_CRYSTAL_MINE',		2);
define('BUILDING_DEUTERIUM_SINTHEZER', 	3);
define('BUILDING_SOLAR_PLANT', 		4);
define('BUILDING_FUSION_PLANT',		12);
define('BUILDING_ROBOT_FACTORY',	14);
define('BUILDING_NANO_FACTORY',		15);
define('BUILDING_HANGAR', 		21);
define('BUILDING_METAL_STORE', 		22);
define('BUILDING_CRYSTAL_STORE', 	23);
define('BUILDING_DEUTERIUM_STORE',	24);
define('BUILDING_LABORATORY', 		31);
define('BUILDING_TERRAFORMER', 		33);
define('BUILDING_ALLY_DEPOSIT',		34);
define('BUILDING_MONDBASIS', 		41);
define('BUILDING_PHALANX', 		42);
define('BUILDING_SPRUNGTOR', 		43);
define('BUILDING_SILO', 		44);

define('TECH_SPY', 		106);
define('TECH_COMPUTER', 	108);
define('TECH_MILITARY', 	109);
define('TECH_SHIELD', 		110);
define('TECH_DEFENCE', 		111);
define('TECH_ENERGY', 		113);
define('TECH_HYPERSPACE',	114);
define('TECH_COMBUSTION',	115);
define('TECH_IMPULSE_DRIVE',	117);
define('TECH_HYPERSPACE_DRIVE',	118);
define('TECH_LASER', 		120);
define('TECH_IONIC', 		121);
define('TECH_BUSTER', 		122);
define('TECH_INTERGALACTIC',	123);
define('TECH_EXPEDITION', 	124);
define('TECH_COLONIZATION', 	150);
define('TECH_GRAVITON',		199);

define('SHIP_TRANSPORT_SMALL',		202);
define('SHIP_TRANSPORT_BIG',		203);
define('SHIP_HUNTER_LIGHT',		204);
define('SHIP_HUNTER_HEAVY',		205);
define('SHIP_CRUSHER',			206);
define('SHIP_LINKOR',			207);
define('SHIP_COLONIZER',		208);
define('SHIP_RECYCLER',			209);
define('SHIP_SPY',			210);
define('SHIP_BOMBER',			211);
define('SHIP_SOLAR_SATELITE',		212);
define('SHIP_DESTRUCTOR',		213);
define('SHIP_DEATH_STAR',		214);
define('SHIP_LINECRUSHER',		215);
define('SHIP_SUPERNOVA',		216);

define('DEFENCE_MISSILE_LAUNCHER',	401);
define('DEFENCE_LASER_SMALL',		402);
define('DEFENCE_LASER_BIG',		403);
define('DEFENCE_GAUSS_CANNON',		404);
define('DEFENCE_IONIC_CANNON',		405);
define('DEFENCE_BUSTER_CANNON',		406);
define('DEFENCE_SHIELD_SMALL',		407);
define('DEFENCE_SHIELD_BIG',		408);
define('DEFENCE_PROTECTOR',		409);

define('MISSILE_INTERCEPTOR',		502);
define('MISSILE_INTERPLANETARY',	503);

define('RPG_GEOLOGUE',		601);
define('RPG_ADMIRAL',		602);
define('RPG_ENGINEER',		603);
define('RPG_TECHNOCRATE',	604);
define('RPG_CONSTRUCTOR',	605);
define('RPG_SCIENTIST',		606);
define('RPG_STOCKER',		607);
define('RPG_DEFENSER',		608);
define('RPG_BUNKER',		609);
define('RPG_ESPION',		610);
define('RPG_COMMANDANT',	611);
define('RPG_DESTRUCTOR',	612);
define('RPG_GENERAL',		613);
define('RPG_RIDER',		614);
define('RPG_EMPEROR',		615);
define('RPG_GEODESIST',		620);
define('RPG_AIDEDECAMP',	621);

// Game constants />
?>