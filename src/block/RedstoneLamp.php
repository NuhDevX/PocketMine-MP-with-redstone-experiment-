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

use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;

class RedstoneLamp extends Opaque implements IRedstoneComponent{
	use PoweredByRedstoneTrait;
	use RedstoneComponentTrait;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->powered);
	}

	public function getLightLevel() : int{
		return $this->powered ? 15 : 0;
	}

	public function onPostPlace(): void {
        if (PowerHelper::isPowered($this) === $this->isPowered()) return;

        $this->setPowered(!$this->isPowered());
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
    }

    public function onScheduledUpdate(): void {
        $side = PowerHelper::isPowered($this);
        if ($side) return;

        $this->updatePowered(false);
    }

    public function onRedstoneUpdate(): void {
        if (PowerHelper::isPowered($this) === $this->isPowered()) return;

        if ($this->isPowered()) {
            $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 4);
            return;
        }

        $this->updatePowered(true);
    }

    protected function updatePowered(bool $powered): void {
        if ($powered === $this->isPowered()) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $powered, $this->isPowered());
            $event->call();
            $powered = $event->getNewPowered();
            if ($powered === $this->isPowered()) return;
        }

        $this->setPowered($powered);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
    }
}
