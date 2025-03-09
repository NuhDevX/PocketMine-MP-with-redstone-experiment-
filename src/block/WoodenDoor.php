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
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\world\BlockTransaction;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\math\Facing;
use pocketmine\world\sound\DoorSound;
use pocketmine\player\Player;

class WoodenDoor extends Door implements IRedstoneComponent {
	use WoodTypeTrait;
	use RedstoneComponentTrait;

	public function getFuelTime() : int{
		return $this->woodType->isFlammable() ? 200 : 0;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool {
        $other = $this->getSide(Facing::UP);
        $powered = PowerHelper::isPowered($this) || PowerHelper::isPowered($other);
        $this->setPowered($powered);
        return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
    }

    public function onRedstoneUpdate(): void {
        $other = $this->getSide($this->isTop() ? Facing::DOWN : Facing::UP);
        $powered = PowerHelper::isPowered($this) || PowerHelper::isPowered($other);
        if ($powered === $this->isPowered()) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $powered, $this->isPowered());
            $event->call();
            $powered = $event->getNewPowered();
            if ($powered === $this->isPowered()) return;
        }

        $this->setPowered($powered);
        $world = $this->getPosition()->getWorld();
        if ($this->isOpen() !== $powered) {
            $this->setOpen($powered);
            $world->addSound($this->getPosition(), new DoorSound());
        }
        $world->setBlock($this->getPosition(), $this);

        if ($other instanceof Door && $this->isSameType($other)) {
            $other->setPowered($this->isPowered());
            $other->setOpen($this->isOpen());
            $world->setBlock($other->getPosition(), $other);
        }
	}
}
