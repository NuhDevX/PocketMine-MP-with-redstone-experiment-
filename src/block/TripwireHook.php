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

use pocketmine\event\block\RedstonePowerUpdateEvent;
use pocketmine\block\utils\SupportType;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\LinkRedstoneWireTrait;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\RedstonePowerOffSound;
use pocketmine\world\sound\RedstonePowerOnSound;
use pocketmine\world\sound\DetachSound;
use pocketmine\world\sound\AttachSound;

class TripwireHook extends Flowable implements IRedstoneComponent, ILinkRedstoneWire{
	use HorizontalFacingTrait;
	use LinkRedstoneWireTrait;
    use RedstoneComponentTrait;

	protected bool $connected = false;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->connected);
		$w->bool($this->powered);
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isPowered() : bool{ return $this->powered; }

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(Facing::axis($face) !== Axis::Y){
			if (!$this->canBeSupportedBy($this->getSide(Facing::opposite($face)))) return false;      
			$this->facing = $face;
			return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		}
		return false;
	}

    public function onPostPlace(): void {
        $this->tryConnect();
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        parent::onBreak($item, $player, $returnedItems);
        $this->disconnect();
        return true;
    }

    public function onNearbyBlockChange(): void {
        if ($this->canBeSupportedBy($this->getSide(Facing::opposite($this->getFacing())))) return;
        $this->getPosition()->getWorld()->useBreakOn($this->getPosition());
	}

	public function onScheduledUpdate(): void {
        $triggered = false;
        for ($i = 1; $i < 42; $i++) {
            $block = $this->getSide($this->getFacing(), $i);
            if ($block instanceof Tripwire) {
                if ($block->isTriggered()) $triggered = true;
                continue;
            }

            $world = $this->getPosition()->getWorld();
            if (!$block instanceof TripwireHook || $block->getFacing() !== Facing::opposite($this->getFacing())) {
                $sound = $this->isPowered() ? new RedstonePowerOffSound() : new DetachSound();
                $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), $sound);
                $this->setConnected(false);
                $this->setPowered(false);
                $world->setBlock($this->getPosition(), $this);
                UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
                break;
            }

            if (!$triggered) {
                $this->setPowered(false);
                $world->setBlock($this->getPosition(), $this);
                $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOffSound());
                UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
                break;
            }

            $world->scheduleDelayedBlockUpdate($this->getPosition(), 1);
            break;
        }
     }

	public function tryConnect(): void {
        /** @var BlockTripwire[] $blocks */
        $blocks = [];
        for ($i = 1; $i < 42; $i++) {
            $block = $this->getSide($this->getFacing(), $i);
            if ($block instanceof Tripwire) {
                $blocks[] = $block;
                continue;
            }

            if (!$block instanceof TripwireHook) break;
            if ($block->getFacing() !== Facing::opposite($this->getFacing())) break;

            $this->setConnected(true);
            $world = $this->getPosition()->getWorld();
            $world->setBlock($this->getPosition(), $this);
            $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new AttachSound());

            $block->setConnected(true);
            $world->setBlock($block->getPosition(), $block);
            $world->addSound($block->getPosition()->add(0.5, 0.5, 0.5), new AttachSound());

            for ($j = 0; $j < count($blocks); $j++) {
                $tripwire = $blocks[$j];
                $tripwire->setConnected(true);
                $world->setBlock($tripwire->getPosition(), $tripwire);
            }
            break;
        }
	}
	
	public function disconnect(int $step = 0, bool $powered = false): void {
        /** @var BlockTripwire[] $blocks */
        $blocks = [];
        for ($i = 1; $i < 42; $i++) {
            if ($step === $i) continue;

            $block = $this->getSide($this->getFacing(), $i);
            if ($block instanceof Tripwire) {
                $blocks[] = $block;
                continue;
            }

            if (!$block instanceof TripwireHook) break;
            if ($block->getFacing() !== Facing::opposite($this->getFacing())) break;

            $world = $this->getPosition()->getWorld();
            $block->setConnected(false);
            if ($step === 0) {
                $block->setPowered($this->callEvent($block, false));
                $world->setBlock($block->getPosition(), $block);

                $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new DetachSound());
                $world->addSound($block->getPosition()->add(0.5, 0.5, 0.5), new DetachSound());

                for ($j = 0; $j < count($blocks); $j++) {
                    $tripwire = $blocks[$j];
                    $tripwire->setConnected(false);
                    $tripwire->setSuspended(false);
                    $world->setBlock($tripwire->getPosition(), $tripwire);
                }
                return;
            }

            if ($powered) {
                $this->setPowered($this->callEvent($this, true));
                $world->setBlock($this->getPosition(), $this);
                UpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
                $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());

                $block->setPowered($this->callEvent($block, true));
                $world->setBlock($block->getPosition(), $block);
                UpdateHelper::updateAroundDirectionRedstone($block, Facing::opposite($block->getFacing()));
                $world->addSound($block->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
            }
            $world->scheduleDelayedBlockUpdate($this->getPosition(), 10);
            $world->scheduleDelayedBlockUpdate($block->getPosition(), 10);

            for ($j = 0; $j < count($blocks); $j++) {
                $tripwire = $blocks[$j];
                $world->scheduleDelayedBlockUpdate($tripwire->getPosition(), 10);
            }
            break;
        }
     }

	public function trigger(): void {
        if ($this->isPowered()) return;

        $triggered = false;
        for ($i = 1; $i < 42; $i++) {
            $block = $this->getSide($this->getFacing(), $i);
            if ($block instanceof BlockTripwire) {
                if ($block->isTriggered()) $triggered = true;
                continue;
            }

            if (!$block instanceof BlockTripwireHook) break;
            if ($block->getFacing() !== Facing::opposite($this->getFacing())) break;
            if (!$triggered) break;

            $this->setPowered($this->callEvent($this, true));
            $world = $this->getPosition()->getWorld();
            $world->setBlock($this->getPosition(), $this);
            BlockUpdateHelper::updateAroundDirectionRedstone($this, Facing::opposite($this->getFacing()));
            $world->addSound($this->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
            $world->scheduleDelayedBlockUpdate($this->getPosition(), 1);

            $block->setPowered($this->callEvent($block, true));
            $world->setBlock($block->getPosition(), $block);
            BlockUpdateHelper::updateAroundDirectionRedstone($block, Facing::opposite($block->getFacing()));
            $world->addSound($block->getPosition()->add(0.5, 0.5, 0.5), new RedstonePowerOnSound());
            $world->scheduleDelayedBlockUpdate($block->getPosition(), 1);
            break;
        }
    }

    private function callEvent(TripwireHook $block, bool $powered): bool {
        $event = new RedstonePowerUpdateEvent($block, $powered, $block->isPowered());
        $event->call();
        return $event->getNewPowered();
    }

    public function getStrongPower(int $face): int {
        return $this->isPowered() && $face == $this->getFacing() ? 15 : 0;
    }

    public function getWeakPower(int $face): int {
        return $this->isPowered() ? 15 : 0;
    }

    public function isPowerSource(): bool {
        return $this->isPowered();
    }

    private function canBeSupportedBy(Block $block): bool {
        return $block->getSupportType($this->getFacing())->equals(SupportType::FULL());
    }
}
