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

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;

class PowerHelper {

    public static function isNormalBlock(Block $block): bool {
        if (VanillaBlocks::SLIME()->isSameType($block)) return true;
        return !$block->isTransparent() && $block->isSolid() && !$this->isPowerSource($block);
    }

    public static function getStrongPower(Block $block, int $face): int {
        return $block instanceof IRedstoneComponent ? $block->getStrongPower($face) : 0;
    }

    public static function getWeakPower(Block $block, int $face): int {
        return $block instanceof IRedstoneComponent ? $block->getWeakPower($face) : 0;
    }

    public static function isPowerSource(Block $block): bool {
        return $block instanceof IRedstoneComponent ? $block->isPowerSource() : false;
    }

    public static function isPowered(Block $block, ?int $ignoreFace = null): bool {
        for ($face = 0; $face < 6; $face++) {
            if ($face === $ignoreFace) continue;
            if ($this->isSidePowered($block, $face)) return true;
        }

        return false;
    }

    public static function getPower(Block $block, int $face): int {
        return $this->isNormalBlock($block) ? $this->getAroundStrongPower($block) : self::getWeakPower($block, $face);
    }

    public static function isSidePowered(Block $block, int $face): bool {
        return $this->getPower($block->getSide($face), $face) > 0;
    }

    public static function getAroundStrongPower(Block $block): int {
        $power = 0;
        for ($face = 0; $face < 6; $face++) {
            $power = max($power, $this->getStrongPower($block->getSide($face), $face));
            if ($power >= 15) return $power;
        }
        return $power;
    }
}
