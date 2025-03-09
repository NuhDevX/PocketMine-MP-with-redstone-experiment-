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

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\world\BlockTransaction;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

class Tripwire extends Flowable{
	protected bool $triggered = false;
	protected bool $suspended = false; //unclear usage, makes hitbox bigger if set
	protected bool $connected = false;
	protected bool $disarmed = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->triggered);
		$w->bool($this->suspended);
		$w->bool($this->connected);
		$w->bool($this->disarmed);
	}

	public function isTriggered() : bool{ return $this->triggered; }

	/** @return $this */
	public function setTriggered(bool $triggered) : self{
		$this->triggered = $triggered;
		return $this;
	}

	public function isSuspended() : bool{ return $this->suspended; }

	/** @return $this */
	public function setSuspended(bool $suspended) : self{
		$this->suspended = $suspended;
		return $this;
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isDisarmed() : bool{ return $this->disarmed; }

	/** @return $this */
	public function setDisarmed(bool $disarmed) : self{
		$this->disarmed = $disarmed;
		return $this;
	}

	public function asItem() : Item{
		return VanillaItems::STRING();
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool {
        $this->setSuspended(true);
        return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace(): void {
        $faces = [Facing::SOUTH, Facing::WEST];
        for ($i = 0; $i < count($faces); $i++) {
            $face = $faces[$i];
            for ($j = 1; $j < 41; $j++) {
                $block = $this->getSide($face, $j);
                if ($block instanceof Tripwire) continue;
                if ($block instanceof TripwireHook && $block->getFacing() == Facing::opposite($face)) {
                    $block->tryConnect();
                }
                break;
            }
        }
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems =[]): bool {
        parent::onBreak($item, $player, $returnedItems);
        if (!$this->isConnected()) return true;

        $faces = [Facing::SOUTH, Facing::WEST];
        for ($i = 0; $i < count($faces); $i++) {
            $face = $faces[$i];
            for ($j = 1; $j < 41; $j++) {
                $block = $this->getSide($face, $j);
                if ($block instanceof Tripwire) 
					continue;
			    }
			
                if ($block instanceof TripwireHook && $block->getFacing() == Facing::opposite($face)) {
                    $block->disconnect($j, $item->getTypeId() !== ItemTypeIds::SHEARS);
                }
                break;
            }
        }
        return true;
    }

    public function onScheduledUpdate(): void {
        if ($this->isTriggered()) {
            $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
            if (count($entities) > 0) return;

            $this->setTriggered(false);
            $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
            return;
        }

        $this->setConnected(false);
        $this->setSuspended(false);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
    }

    public function onEntityInside(Entity $entity): bool {
        $entities = $this->getPosition()->getWorld()->getNearbyEntities($this->getHitCollision());
        if (count($entities) <= 0) return true;

        $this->setTriggered(true);
        $world = $this->getPosition()->getWorld();
        $world->setBlock($this->getPosition(), $this);
        $world->scheduleDelayedBlockUpdate($this->getPosition(), 1);

        $faces = [Facing::SOUTH, Facing::WEST];
        for ($i = 0; $i < count($faces); $i++) {
            $face = $faces[$i];
            for ($j = 1; $j < 41; $j++) {
                $block = $this->getSide($face, $j);
                if ($block instanceof Tripwire) continue;
                if ($block instanceof TripwireHook && $block->getFacing() == Facing::opposite($face)) {
                    $block->trigger();
                }
                break;
            }
        }
        return true;
    }

    public function hasEntityCollision(): bool {
        return true;
    }

    protected function getHitCollision(): AxisAlignedBB {
        return new AxisAlignedBB(
            $this->getPosition()->getX(),
            $this->getPosition()->getY(),
            $this->getPosition()->getZ(),
            $this->getPosition()->getX() + 1,
            $this->getPosition()->getY() + 0.0625,
            $this->getPosition()->getZ() + 1
        );
    }

}
