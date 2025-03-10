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

use pocketmine\block\utils\RailPoweredByRedstoneTrait;
use pocketmine\block\utils\RailConnectionInfo;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\item\Item;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\player\Player;

class PoweredRail extends StraightOnlyRail implements IRedstoneComponent{
	use RailPoweredByRedstoneTrait;
	use RedstoneComponentTrait;

	public function onPostPlace(): void {
        parent::onPostPlace();
        $this->updatePower($this);
        $this->updateConnectedRails();
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        $this->updateConnectedRails();
        return true;
    }

    public function onRedstoneUpdate(): void {
        $this->updatePower($this);
        $this->updateConnectedRails();
    }

    protected function updateConnectedRails(): void {
        $connections = $this->getCurrentShapeConnections();
        for ($i = 0; $i < count($connections); $i++) {
            $face = $connections[$i];
            $up = false;
            if (($face & RailConnectionInfo::FLAG_ASCEND) > 0) {
                $face = $face ^ RailConnectionInfo::FLAG_ASCEND;
                $up = true;
            }

            $side = $this;
            for ($j = 0; $j < 8; $j++) {
                $side = $side->getSide($face);
                if ($up) $side = $side->getSide(Facing::UP);
                if (!$side instanceof PoweredRail) {
                    $side = $side->getSide(Facing::DOWN);
                    if (!$side instanceof PoweredRail) break;
                }

                $faces = $side->getCurrentShapeConnections();
                if (in_array($face, $faces, true)) {
                    $this->updatePower($side);
                    $up = false;
                    continue;
                }

                if (in_array($face | RailConnectionInfo::FLAG_ASCEND, $faces, true)) {
                    $this->updatePower($side);
                    $up = true;
                    continue;
                }
                break;
            }
        }
	}

	protected function updatePower(PoweredRail $block): void {
        if (BlockPowerHelper::isPowered($block)) {
            $this->updatePowered($block, true);
            return;
        }

        $connections = $block->getCurrentShapeConnections();
        for ($i = 0; $i < count($connections); $i++) {
            $face = $connections[$i];
            $up = false;
            if (($face & RailConnectionInfo::FLAG_ASCEND) > 0) {
                $face = $face ^ RailConnectionInfo::FLAG_ASCEND;
                $up = true;
            }

            $side = $block;
            for ($j = 0; $j < 8; $j++) {
                $side = $side->getSide($face);
                if ($up) $side = $side->getSide(Facing::UP);
                if (!$side instanceof PoweredRail) {
                    $side = $side->getSide(Facing::DOWN);
                    if (!$side instanceof PoweredRail) break;
                }

                $faces = $side->getCurrentShapeConnections();
                if (in_array($face, $faces, true)) {
                    if (BlockPowerHelper::isPowered($side)) {
                        $this->updatePowered($block, true);
                        return;
                    }
                    $up = false;
                    continue;
                }

                if (in_array($face | RailConnectionInfo::FLAG_ASCEND, $faces, true)) {
                    if (BlockPowerHelper::isPowered($side)) {
                        $this->updatePowered($block, true);
                        return;
                    }
                    $up = true;
                    continue;
                }
                break;
            }
        }

        $this->updatePowered($block, false);
    }

    protected function updatePowered(PoweredRail $block, bool $powered): void {
        $oldPowered = $block->isPowered();
        if ($oldPowered === $powered) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $powered, $oldPowered);
            $event->call();
            $powered = $event->getNewPowered();
            if ($oldPowered === $powered) return;
        }

        $block->setPowered($powered);
        $block->getPosition()->getWorld()->setBlock($block->getPosition(), $block);
	}
}
