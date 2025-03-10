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

namespace pocketmine\utils;

use pocketmine\entity\MinecartBase;
use pocketmine\block\ActivatorRail;
use pocketmine\block\Block;
use pocketmine\block\DetectorRail;
use pocketmine\block\PoweredRail;
use pocketmine\block\Rail;
use pocketmine\block\VanillaBlocks;
use pocketmine\Server;

class Rails {

    private Block $rail;
    private MinecartBase $minecart;

    public function __construct(Block $rail, MinecartBase $minecart) {
        $this->rail = $rail;
        $this->minecart = $minecart;
    }

    public function onUpdate(): void {
        $minecart = $this->minecart;

        $rail = $minecart->getCurrentRail();
        if ($rail instanceof DetectorRail) {
            switch ($minecart->getHorizontalFacing()) {
              case MinecartBase::NORTH:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->add(0, 0, $i);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, 0)->add(0, 0, $i);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
              case MinecartBase::SOUTH:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->subtract(0, 0, $i);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, $i);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }
                        
                    }
                    break;
              case MinecartBase::EAST:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->subtract($i, 0, 0);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract($i, 1, 0);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
              case MinecartBase::WEST:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->add($i, 0, 0);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, 0)->add($i, 0, 0);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
            }

            if ($minecart->isAlive() === true and $minecart->isClosed() === true) {
                $blockPos->setActivated(false);
                $blockDown->setActivated(false);

                $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
            }
        }
    }


    public function handle(): void {
        $rail = $this->rail;
        $minecart = $this->minecart;

        if ($rail instanceof PoweredRail) {
            if ($rail->isPowered()) {
                $minecart->moveSpeed = 0.4;
            } else {
                $minecart->moveSpeed = 0.2;
            }
        }

        if ($rail instanceof DetectorRail) {
            $rail->setActivated(true);
            $minecart->getWorld()->setBlock($rail->getPosition(), $rail);
        }
    }

}
