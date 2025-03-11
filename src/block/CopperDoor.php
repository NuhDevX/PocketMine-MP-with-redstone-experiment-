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

namespace pocketmine\block;

use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\CopperMaterial;
use pocketmine\block\utils\CopperTrait;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\item\Item;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\world\sound\DoorSound;

class CopperDoor extends Door implements CopperMaterial, IRedstoneComponent{
	use CopperTrait{
		onInteract as onInteractCopper;
	}
	use RedstoneComponentTrait;

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if ($player !== null && $player->isSneaking() && $this->onInteractCopper($item, $face, $clickVector, $player, $returnedItems)) {
			//copy copper properties to other half
			$other = $this->getSide($this->top ? Facing::DOWN : Facing::UP);
			$world = $this->position->getWorld();
			if ($other instanceof CopperDoor) {
				$other->setOxidation($this->oxidation);
				$other->setWaxed($this->waxed);
				$world->setBlock($other->position, $other);
			}
			return true;
		}

		return parent::onInteract($item, $face, $clickVector, $player, $returnedItems);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool {
        $other = $this->getSide(Facing::UP);
        $powered = PowerHelper::isPowered($this) || PowerHelper::isPowered($other);
        $this->setPowered($powered);
        return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
    }

    public function onRedstoneUpdate(): void {
        $other = $this->getSide($this->isTop() ? Facing::DOWN : Facing::UP);
        $powered = PowerHelper::isPowered($this) || PowerHelper::isPowered($other);
        if ($powered === $this->isPowered()) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $powered, $this->isPowered());
            $event->call();
            $powered = $event->getNewPowered();
            if ($powered === $this->isPowered()) return;
        }

        $this->setPowered($powered);
        $world = $this->getPosition()->getWorld();
        if ($this->isOpen() !== $powered) {
            $this->setOpen($powered);
            $world->addSound($this->getPosition(), new DoorSound());
        }
        $world->setBlock($this->getPosition(), $this);

        if ($other instanceof Door && $this->isSameType($other)) {
            $other->setPowered($this->isPowered());
            $other->setOpen($this->isOpen());
            $world->setBlock($other->getPosition(), $other);
        }
	}
}
