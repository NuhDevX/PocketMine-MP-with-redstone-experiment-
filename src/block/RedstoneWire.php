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
use pocketmine\event\block\RedstoneSignalUpdateEvent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\SlabType;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\math\Facing;

class RedstoneWire extends Flowable implements IRedstoneComponent, ILinkRedstoneWire{
	use AnalogRedstoneSignalEmitterTrait;
	use StaticSupportTrait;
	use RedstoneComponentTrait;

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$this->onNearbyBlockChange();

		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}

	public function onPostPlace(): void {
        $this->calculatePower();
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        UpdateHelper::updateAroundStrongRedstone($this);
        return parent::onBreak($item, $player, $returnedItems);
    }

    public function onNearbyBlockChange(): void {
        parent::onNearbyBlockChange();

        if ($this->getPosition()->getWorld()->getBlock($this->getPosition()) === VanillaBlocks::AIR()) return;
        if ($this->calculatePower()) return;
        UpdateHelper::updateAroundStrongRedstone($this);
    }

    public function getStrongPower(int $face): int {
        return $this->getWeakPower($face);
    }

    public function getWeakPower(int $face): int {
        if ($face == Facing::UP) return $this->getOutputSignalStrength();
        if ($face == Facing::DOWN) return 0;
        if ($this->isConnected(Facing::opposite($face))) return $this->getOutputSignalStrength();

        $right = Facing::rotateY($face, true);
        $left = Facing::rotateY($face, false);

        return $this->isConnected($right) || $this->isConnected($left) ? 0 : $this->getOutputSignalStrength();
    }

    private function isConnected(int $face): bool {
        $block = $this->getSide($face);
        if ($block instanceof ILinkRedstoneWire && $block->isConnect($face)) return true;

        if (BlockPowerHelper::isNormalBlock($block)) {
            $sideBlock = $block->getSide(Facing::UP);
            return $sideBlock instanceof RedstoneWire;
        }

        if ($block->isTransparent()) {
            $sideBlock = $block->getSide(Facing::DOWN);
            return $sideBlock instanceof RedstoneWire;
        }
        return false;
    }

    public function onRedstoneUpdate(): void {
        $this->calculatePower();
    }

	private function calculatePower(): bool {
        $power = 0;
        for ($face = 0; $face < 6; $face++) {
            $block = $this->getSide($face);
            if ($block instanceof RedstoneWire) {
                $power = max($power, $block->getOutputSignalStrength() - 1);
                continue;
            }

            if (PowerHelper::isPowerSource($block)) {
                $power = max($power, PowerHelper::getWeakPower($block, $face));
                continue;
            }

            if (PowerHelper::isNormalBlock($block)) {
                for ($sideFace = 0; $sideFace < 6; $sideFace++) {
                    if ($sideFace == Facing::opposite($face)) continue;

                    $sideBlock = $block->getSide($sideFace);
                    if (!PowerHelper::isPowerSource($sideBlock)) continue;

                    $power = max($power, PowerHelper::getStrongPower($sideBlock, $sideFace));
                }
                continue;
            }

            if ($face == Facing::DOWN) continue;

            if ($block->isTransparent()) {
                if ($face == Facing::UP) {
                    for ($sideFace = 2; $sideFace < 6; $sideFace++) {
                        $sideBlock = $block->getSide($sideFace);
                        if (!$sideBlock instanceof RedstoneWire) continue;

                        $down = $sideBlock->getSide(Facing::DOWN);
                        if (($down instanceof Slab && $down->getSlabType() !== SlabType::DOUBLE()) || $down instanceof Stair) continue;

                        $power = max($power, $sideBlock->getOutputSignalStrength() - 1);
                    }
                    continue;
                }

                $sideBlock = $block->getSide(Facing::DOWN);
                if (!$sideBlock instanceof RedstoneWire) continue;

                $power = max($power, $sideBlock->getOutputSignalStrength() - 1);
            }
        }

        if ($this->getOutputSignalStrength() == $power) return false;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstoneSignalUpdateEvent($this, $power, $this->getOutputSignalStrength());
            $event->call();

            $power = $event->getNewSignal();
            if ($this->getOutputSignalStrength() == $power) return false;
        }

        $this->setOutputSignalStrength($power);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateAroundStrongRedstone($this);
        return true;
    }

    public function isConnect(int $face): bool {
        return true;
    }
}
