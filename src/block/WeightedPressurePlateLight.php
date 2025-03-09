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

use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\event\block\RedstoneSignalUpdateEvent;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\math\Facing;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
/**
 * @deprecated
 */
class WeightedPressurePlateLight extends WeightedPressurePlate implements IRedstoneComponent, ILinkRedstoneWire{
	use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;
	
	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
        return true;
    }

    public function onNearbyBlockChange(): void {
        if ($this->canBeSupportedBy($this->getSide(Facing::DOWN))) return;
        $this->getPosition()->getWorld()->useBreakOn($this->getPosition());
    }

    public function onScheduledUpdate(): void {
        if ($this->getOutputSignalStrength() === 0) return;

        $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());		
		for ($i = 0; $i < count($entities); $i++) {
            if ($entities[$i] instanceof Arrow) {// TODO trident activate this
				return;
			}
		}
		
        $count = count($entities);
        if ($count !== 0) {
            $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 20);
        }

        $oldPower = $this->getOutputSignalStrength();
        $power = min($count, 15);
        if ($oldPower === $power) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstoneSignalUpdateEvent($this, $power, $oldPower);
            $event->call();
            $power = $event->getNewSignal();
            if ($oldPower === $power) return;
        }

        if ($power === 0) {
            $this->getPosition()->getWorld()->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
        }
        $this->setOutputSignalStrength($power);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
    }

    public function onEntityInside(Entity $entity): bool {
        if ($entity instanceof Player && $entity->isSpectator() || !$entity instanceof Arrow) {//TODO Trident activate this
			return true;
	    }

        $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
        $count = count($entities);
        if ($count <= 0) return true;

        $oldPower = $this->getOutputSignalStrength();
        $power = min($count, 15);
        if ($oldPower !== $power && RedstoneEvent::isCallEvent()) {
            $event = new RedstoneSignalUpdateEvent($this, $power, $oldPower);
            $event->call();
            $power = $event->getNewSignal();
            if ($oldPower === $power) return true;
        }

        if ($oldPower === 0) {
            $this->getPosition()->getWorld()->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
        }
        $this->setOutputSignalStrength($power);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 10);
        return true;
    }

    public function hasEntityCollision(): bool {
        return true;
    }

    protected function getHitCollision(): AxisAlignedBB {
        return new AxisAlignedBB(
            $this->getPosition()->getX() + 0.0625,
            $this->getPosition()->getY(),
            $this->getPosition()->getZ() + 0.0625,
            $this->getPosition()->getX() + 0.9375,
            $this->getPosition()->getY() + 0.0625,
            $this->getPosition()->getZ() + 0.9375
        );
    }

    public function getStrongPower(int $face): int {
        return $face == Facing::UP ? $this->getOutputSignalStrength() : 0;
    }

    public function getWeakPower(int $face): int {
        return $this->getOutputSignalStrength();
    }

    public function isPowerSource(): bool {
        return $this->getOutputSignalStrength() > 0;
    }

    private function canBeSupportedBy(Block $block): bool {
        return !$block->getSupportType(Facing::UP)->equals(SupportType::NONE());
    }

}
