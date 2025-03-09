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

use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\math\Facing;
use pocketmine\math\AxisAlignedBB;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;

class WoodenButton extends Button implements IRedstoneComponent, ILinkRedstoneWire {
	use WoodTypeTrait;
	use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;

	protected function getActivationTime() : int{
		return 30;
	}

	public function hasEntityCollision() : bool{
		return true; 
	}

	public function getFuelTime() : int{
		return $this->woodType->isFlammable() ? 100 : 0;
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
            if ($entities[$i] instanceof Arrow) { //TODO Trident and Wind Charge activate wooden buttons
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

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        if ($this->isPressed()) UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
        return true;
	}

    public function onEntityInside(Entity $entity): bool {
        if (!$entity instanceof Arrow) {//TODO trident and wind charge activate wooden buttons
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

    protected function getHitCollision(): AxisAlignedBB {
        $bb = match ($this->getFacing()) {
            Facing::DOWN => new AxisAlignedBB(5, 14, 6, 11, 16, 10),
            Facing::UP => new AxisAlignedBB(5, 0, 6, 11, 2, 10),
            Facing::NORTH => new AxisAlignedBB(5, 6, 14, 11, 10, 16),
            Facing::SOUTH => new AxisAlignedBB(5, 6, 0, 11, 10, 2),
            Facing::WEST => new AxisAlignedBB(14, 6, 5, 16, 10, 11),
            Facing::EAST => new AxisAlignedBB(0, 6, 5, 2, 10, 11)
        };
        $bb->minX /= 16;
        $bb->maxX /= 16;
        $bb->minY /= 16;
        $bb->maxY /= 16;
        $bb->minZ /= 16;
        $bb->maxZ /= 16;
        $pos = $this->getPosition();
        $bb->offset($pos->getX(), $pos->getY(), $pos->getZ());
        return $bb;
    }

    protected function recalculateCollisionBoxes(): array {
        $bb = match ($this->getFacing()) {
            Facing::DOWN => new AxisAlignedBB(5, 15, 6, 11, 16, 10),
            Facing::UP => new AxisAlignedBB(5, 0, 6, 11, 1, 10),
            Facing::NORTH => new AxisAlignedBB(5, 6, 15, 11, 10, 16),
            Facing::SOUTH => new AxisAlignedBB(5, 6, 0, 11, 10, 1),
            Facing::WEST => new AxisAlignedBB(15, 6, 5, 16, 10, 11),
            Facing::EAST => new AxisAlignedBB(0, 6, 5, 1, 10, 11)
        };
        $bb->minX /= 16;
        $bb->maxX /= 16;
        $bb->minY /= 16;
        $bb->maxY /= 16;
        $bb->minZ /= 16;
        $bb->maxZ /= 16;
        return [ $bb ];
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
