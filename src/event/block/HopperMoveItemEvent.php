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

namespace pocketmine\event\block;

use pocketmine\block\Hopper;
use pocketmine\block\Jukebox;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;

class HopperMoveItemEvent extends BlockEvent implements Cancellable {
    use CancellableTrait;

    private Hopper $hopper;
    private Inventory $source;
    private ?Inventory $targetInventory = null;
    private ?Jukebox $targetJukebox = null;
    private Item $item;

    public function __construct(Hopper $hopper, Inventory $source, Inventory|Jukebox $target, Item $item) {
        parent::__construct($hopper);

        $this->hopper = $hopper;
        $this->source = $source;
        if ($target instanceof Inventory) {
            $this->targetInventory = $target;
        } else {
            $this->targetJukebox = $target;
        }
        $this->item = $item;
    }

    public function getHopper(): Hopper {
        return $this->hopper;
    }

    public function getSourceInventory(): Inventory {
        return $this->source;
    }

    public function getTargetInventory(): ?Inventory {
        return $this->targetInventory;
    }

    public function getJukebox(): ?Jukebox {
        return $this->targetJukebox;
    }

    public function getItem(): Item {
        return $this->item;
    }
}
