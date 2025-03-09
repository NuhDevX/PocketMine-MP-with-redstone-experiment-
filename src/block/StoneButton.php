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
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\item\Item;
use pocketmine\player\Player:
use pocketmine\math\Vector3;
use pocketmine\math\Facing;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\Entity;

class StoneButton extends Button implements IRedstoneComponent, ILinkRedstoneWire{
    use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;
	
	protected function getActivationTime() : int{
		return 20;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []): bool {
        if ($this->isPressed()) return true;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, !$this->isPressed(), $this->isPressed());
            $event->call();
        }
        parent::onInteract($item, $face, $clickVector, $player, $returnedItems);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
        return true;
    }

    public function onScheduledUpdate(): void {
        if (!$this->isPressed()) return;

		$entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
        for ($i = 0; $i < count($entities); $i++) {
            if ($entities[$i] instanceof Arrow) { //TODO Trident and Wind Charge activate stone buttons
				return;
		    }
        }
		
        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, !$this->isPressed(), $this->isPressed());
            $event->call();
        }
        parent::onScheduledUpdate();
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
    }

	public function onEntityInside(Entity $entity): bool {
        if (!$entity instanceof Arrow) {//TODO trident and wind charge activate stone buttons
			return true;
		}       
		
		if (!$this->getHitCollision()->intersectsWith($entity->getBoundingBox())) return true;

        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 1);
        if ($this->isPressed()) return true;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, !$this->isPressed(), $this->isPressed());
            $event->call();
        }
        $this->setPressed(true);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
        return false;
	}

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        if ($this->isPressed()) UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
        return true;
    }

    public function getStrongPower(int $face): int {
        if (!$this->isPressed()) return 0;
        return $face === $this->getFacing() ? 15 : 0;
    }

    public function getWeakPower(int $face): int {
        return $this->isPressed() ? 15 : 0;
    }

    public function isPowerSource(): bool {
        return $this->isPressed();
    }
}
