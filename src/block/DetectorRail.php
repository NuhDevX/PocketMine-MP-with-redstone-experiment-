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
use pocketmine\math\Facing;
use pocketmine\block\utile\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\object\Minecart;
use pocketmine\entity\object\MinecartChest;
use pocketmine\entity\object\MinecraftHopper;
use pocketmine\entity\object\MinecraftTNT;

class DetectorRail extends StraightOnlyRail implements IRedstoneComponent, ILinkRedstoneWire{
	use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;
	protected bool $activated = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->activated);
	}

	public function isActivated() : bool{ return $this->activated; }

	/** @return $this */
	public function setActivated(bool $activated) : self{
		$this->activated = $activated;
		return $this;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
        parent::onBreak($item, $player);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
		$this->updateNearbyPoweredRailAndActivatorRail(true);
        return true;
	}

	public function getWeakPower(int $face): int {
		return $this->isActive() ? 15 : 0;
	}

	public function isPowerSource(): bool {
        return true;
	}

	public function onScheduledUpdate(): void {
        if (!$this->isActivated()) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, !$this->isActivated(), $this->isActivated());
            $event->call();
        }
        parent::onScheduledUpdate();
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
		$this->setActivated(false);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $block);
		$this->updateNearbyPoweredRailAndActivatorRail(false);
	}

	public function onEntityInside(Entity $entity): bool {
        if (!($entity instanceof Minecart || $entity instanceof MinecartChest || $entity instanceof MinecartHopper || $entity instanceof MinecartTNT)) return false;

        if (!$this->isActivated()) {
			$activate = true;
            if (RedstoneEvent::isCallEvent()) {
                $event = new RedstonePowerUpdateEvent($this, true, $this->isPressed());
                $event->call();
                $activate = $event->getNewPowered();
            }
            $this->setActivated($activate);
            $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
			$this->updateNearbyPoweredRailAndActivatorRail(true);
            UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
        }
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 20);
        return true;
	}

    private function updateNearbyPoweredRail(bool $powered): void {
        $world = $this->getPosition()->getWorld();
        $x = $this->getPosition()->getX();
        $y = $this->getPosition()->getY();
        $z = $this->getPosition()->getZ();

        $directions = [
            [1, 0, 0],  [-1, 0, 0],  
            [0, 0, 1],  [0, 0, -1], 
            [0, -1, 0], [0, 1, 0]   
        ];

        foreach ($directions as [$dx, $dy, $dz]) {
            $block = $world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
            if ($block instanceof PoweredRail || $block instanceof ActivatorRail) {
                $block->setPowered($powered);
                $world->setBlock($this->getPosition(), $block);
            }
        }
	}
}
