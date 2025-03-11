<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\player\Player;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3; 
use pocketmine\entity\Location;
use pocketmine\entity\object\Minecart as MinecartEntity;
use pocketmine\entity\object\MinecartChest as MinecartChestEntity;
use pocketmine\entity\object\MinecartHopper as MinecartHopperEntity;
use pocketmine\entity\object\MinecartTNT as MinecartTNTEntity;

class Minecart extends Item{

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($blockClicked->getTypeId() === BlockTypeIds::DETECTOR_RAIL || $blockClicked->getTypeId() === BlockTypeIds::RAIL || $blockClicked->getTypeId() === BlockTypeIds::POWERED_RAIL || $blockClicked->getTypeId() === BlockTypeIds::ACTIVATOR_RAIL){
			$pos = $blockClicked->getPosition()->add(0.5, 1, 0.5);
            $world = $blockClicked->getPosition()->getWorld();
            $yaw = fmod($player->getLocation()->yaw - 90, 360);
            $location = Location::fromObject($pos, $world, $yaw, 0);

            switch ($this->getTypeId()) {
				case VanillaItems::MINECART()->getTypeId():
                    $entity = new MinecartEntity($location);
                    break;
				case VanillaItems::CHEST_MINECART()->getTypeId():
                    $entity = new MinecartChestEntity($location);
                    $entity->saveNBT()->setString("Data", $entity->write());
                    break;
				case VanillaItems::HOPPER_MINECART()->getTypeId():
                    $entity = new MinecartHopperEntity($location);
                    break;
				case VanillaItems::TNT_MINECART()->getTypeId():
                    $entity = new MinecartTNTEntity($location);
                    break;
				default:

				if ($this->hasCustomName()) {
                $entity->setNameTag($item->getCustomName());
            }

            if ($player->isSurvival()) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            $entity->spawnToAll();
		    $this->pop();
	     	return ItemUseResult::SUCCESS;
			}
		}
		return ItemUseResult::NONE;
	}
} 
