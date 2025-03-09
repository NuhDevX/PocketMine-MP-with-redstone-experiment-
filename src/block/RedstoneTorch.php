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

use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\LightableTrait;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\math\Facing;
use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\event\block\RedstoneEvent;

class RedstoneTorch extends Torch implements IRedstoneComponent, ILinkRedstoneWire{
	use LightableTrait;
	use LinkRedstoneWireTrait;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		$this->lit = true;
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->lit);
	}

	public function getLightLevel() : int{
		return $this->lit ? 7 : 0;
	}

	public function onPostPlace(): void {
        $this->onRedstoneUpdate();
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
        return true;
    }

    public function onScheduledUpdate(): void {
        $lit = !$this->isLit();
        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, $lit, $lit);
            $event->call();
            $lit = $event->getNewPowered();
        }
        $this->setLit($lit);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
    }

    public function getStrongPower(int $face): int {
        return $this->isLit() && $face === Facing::DOWN ? 15 : 0;
    }

    public function getWeakPower(int $face): int {
        if  (!$this->isLit()) return 0;
        if ($face === Facing::DOWN) return $this->getFacing() !== Facing::DOWN ? 15 : 0;
        return $face !== $this->getFacing() ? 15 : 0;
    }

    public function isPowerSource(): bool {
        return $this->isLit();
    }

    public function onRedstoneUpdate(): void {
        if (PowerHelper::isSidePowered($this, Facing::opposite($this->getFacing())) !== $this->isLit()) return;
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 2);
	}
}
