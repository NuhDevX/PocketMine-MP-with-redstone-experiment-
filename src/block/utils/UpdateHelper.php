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
use pocketmine\math\Axis;
use pocketmine\math\Facing;

class UpdateHelper {

    public static function updateAroundRedstone(Block $center, ?int $ignoreFace = null): void {
        for ($face = 0; $face < 6; $face++) {
            if ($face === $ignoreFace) continue;

            $block = $center->getSide($face);
            if ($block instanceof IRedstoneComponent) $block->onRedstoneUpdate();
        }
    }

    public static function updateAroundDirectionRedstone(Block $block, int $face): void {
        $this->updateAroundRedstone($block);
        $this->updateAroundRedstone($block->getSide($face), Facing::opposite($face));
    }

    public static function updateAroundStrongRedstone(Block $center): void {
        for ($face1 = 0; $face1 < 6; $face1++) {
            $block = $center->getSide($face1);
            if ($block instanceof IRedstoneComponent) $block->onRedstoneUpdate();

            $opposite = Facing::opposite($face1);
            for ($face2 = 0; $face2 < 6; $face2++) {
                if ($face2 == $opposite) continue;
                if (Facing::axis($face1) == Axis::Y && Facing::axis($face2) != Axis::Y) continue;
                if (Facing::axis($face1) != Axis::Y && Facing::rotate($face1, Axis::Y, true) == $face2) continue;

                $sideBlock = $block->getSide($face2);
                if ($sideBlock instanceof IRedstoneComponent) $sideBlock->onRedstoneUpdate();
            }
        }
    }

    public static function updateDiodeRedstone(Block $center, int $face): void {
        $block = $center->getSide($face);
        if ($block instanceof IRedstoneComponent) $block->onRedstoneUpdate();

        $opposite = Facing::opposite($face);
        for ($face1 = 0; $face1 < 6; $face1++) {
            if ($face1 == $opposite) continue;

            $sideBlock = $block->getSide($face1);
            if ($sideBlock instanceof IRedstoneComponent) $sideBlock->onRedstoneUpdate();
        }
    }
}
