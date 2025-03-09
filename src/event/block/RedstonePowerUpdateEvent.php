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

namespace pocketmine\block\event;

use pocketmine\block\Block;

class RedstonePowerUpdateEvent extends BlockEvent {

    private bool $newPowered;
    private bool $powered;

    public function __construct(Block $block, bool $newPower, bool $powered) {
        parent::__construct($block);

        $this->newPowered = $newPower;
        $this->powered = $powered;
    }

    public function getNewPowered(): bool {
        return $this->newPowered;
    }

    public function setNewPowered(bool $powered): void {
        $this->newPowered = $powered;
    }

    public function getPowered(): bool {
        return $this->powered;
    }
}
