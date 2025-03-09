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

use pocketmine\math\Facing;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstoneSignalUpdateEvent;

class TrappedChest extends Chest implements IRedstoneComponent, ILinkRedstoneWire{
   use AnalogRedstoneSignalEmitterTrait;
    use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;
	
	public function readStateFromWorld(): Block {
        parent::readStateFromWorld();
        $tile = $this->getPosition()->getWorld()->getTile($this->getPosition());
        if ($tile instanceof TileChest) {
            $this->setOutputSignalStrength(min($tile->getInventory()->getViewerCount(), 15));
        }
    }

    public function onScheduledUpdate(): void {
        $tile = $this->getPosition()->getWorld()->getTile($this->getPosition());
        if (!$tile instanceof TileChest) return;

        $signal = min($tile->getInventory()->getViewerCount(), 15);
        if ($this->getOutputSignalStrength() === $signal) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstoneSignalUpdateEvent($this, $signal, $this->getOutputSignalStrength());
            $event->call();
            $signal = $event->getNewSignal();
            if ($this->getOutputSignalStrength() === $signal) return;
        }
        $this->setOutputSignalStrength($signal);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
    }

    public function getStrongPower(int $face): int {
        return $face === Facing::UP ? $this->getOutputSignalStrength() : 0;
    }

    public function getWeakPower(int $face): int {
        return $this->getOutputSignalStrength();
    }

    public function isPowerSource(): bool {
        return $this->getOutputSignalStrength() !== 0;
}

}
