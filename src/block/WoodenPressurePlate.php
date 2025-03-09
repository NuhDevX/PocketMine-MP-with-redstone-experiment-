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

use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\block\utils\SupportType;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\WoodType;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\event\block\RedstoneEvent;
use pocketmine\block\event\block\RedstonePowerUpdateEvent;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

class WoodenPressurePlate extends SimplePressurePlate implements IRedstoneComponent, ILinkRedstoneWire{
	use WoodTypeTrait;
	use LinkRedstoneWireTrait;

	public function __construct(
		BlockIdentifier $idInfo,
		string $name,
		BlockTypeInfo $typeInfo,
		WoodType $woodType,
		int $deactivationDelayTicks = 20 //TODO: make this mandatory in PM6
	){
		$this->woodType = $woodType;
		parent::__construct($idInfo, $name, $typeInfo, $deactivationDelayTicks);
	}

	public function getFuelTime() : int{
		return 300;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
        return true;
    }

    public function onNearbyBlockChange(): void {
        if ($this->canBeSupportedBy($this->getSide(Facing::DOWN))) return;
        $this->getPosition()->getWorld()->useBreakOn($this->getPosition());
    }

    public function onScheduledUpdate(): void {
        if (!$this->isPressed()) return;

        $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
        if (count($entities) !== 0) {
            $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 20);
            return;
        }

        $pressed = false;
        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstonePowerUpdateEvent($this, false, $this->isPressed());
            $event->call();
            $pressed = $event->getNewPowered();
        }
        $this->setPressed($pressed);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        $this->getPosition()->getWorld()->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
        UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
    }

    public function onEntityInside(Entity $entity): bool {
        if ($entity instanceof Player && $entity->isSpectator()) return true;

        $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
        if (count($entities) <= 0) return true;

        if (!$this->isPressed()) {
            $pressed = true;
            if (RedstoneEvent::isCallEvent()) {
                $event = new RedstonePowerUpdateEvent($this, true, $this->isPressed());
                $event->call();
                $pressed = $event->getNewPowered();
            }
            $this->setPressed($pressed);
            $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
            $this->getPosition()->getWorld()->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
            UpdateHelper::updateAroundDirectionRedstone($this, Facing::DOWN);
        }
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 20);
        return true;
    }

    public function hasEntityCollision(): bool {
        return true;
    }

    protected function getHitCollision(): AxisAlignedBB {
        return new AxisAlignedBB(
            $this->getPosition()->getX() + 0.0625,
            $this->getPosition()->getY(),
            $this->getPosition()->getZ() + 0.0625,
            $this->getPosition()->getX() + 0.9375,
            $this->getPosition()->getY() + 0.0625,
            $this->getPosition()->getZ() + 0.9375
        );
    }

    public function getStrongPower(int $face): int {
        return $this->isPressed() && $face == Facing::UP ? 15 : 0;
    }

    public function getWeakPower(int $face): int {
        return $this->isPressed() ? 15 : 0;
    }

    public function isPowerSource(): bool {
        return $this->isPressed();
    }

    private function canBeSupportedBy(Block $block): bool {
        return !$block->getSupportType(Facing::UP)->equals(SupportType::NONE());
    }
}
