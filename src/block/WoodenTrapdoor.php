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

use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\IRedstoneComponentTrait;
use pocketmine\world\sound\DoorSound;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\event\block\RedstoneEvent;

class WoodenTrapdoor extends Trapdoor implements IRedstoneComponentTrait{
	use WoodTypeTrait;
	use RedstoneComponentTrait;

	public function getFuelTime() : int{
		return 300;
	}

	public function onRedstoneUpdate(): void {
        $powered = PowerHelper::isPowered($this);
        if ($powered === $this->isOpen()) return;

		if(RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $powered, $this->isOpen());
            $event->call();
            $powered = $event->getNewPowered();
            if ($powered === $this->isOpen()) return;
		}

        $this->setOpen($powered);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        $this->getPosition()->getWorld()->addSound($this->getPosition(), new DoorSound());
	}
}
