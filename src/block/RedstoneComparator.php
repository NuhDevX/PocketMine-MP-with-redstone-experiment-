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


use pocketmine\block\utils\RecordType;
use pocketmine\block\utils\ChiseledBookshelfSlot;
use pocketmine\block\tile\ChiseledBookshelf as TileChiseledBookshelf;
use pocketmine\block\tile\Lectern as TileLectern;
use pocketmine\block\tile\Cauldron as TileCauldron;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\block\tile\Furnace as TileFurnace;
use pocketmine\block\tile\BrewingStand as TileBrewingStand;
use pocketmine\block\tile\Barrel as TileBarrel;
use pocketmine\block\tile\ShulkerBox as TileShulkerBox;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Comparator;
use pocketmine\block\utils\RedstoneComponentTrait;
use pocketmine\block\utils\IRedstoneDiode;
use pocketmine\block\utils\IRedstoneComponent;
use pocketmine\block\utils\ILinkRedstoneWire;
use pocketmine\block\utils\UpdateHelper;
use pocketmine\block\utils\PowerHelper;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\event\block\RedstoneEvent;
use pocketmine\event\block\RedstoneSignalUpdateEvent;
use function assert;

class RedstoneComparator extends Flowable implements IRedstoneComponent, ILinkRedstoneWire{
	use HorizontalFacingTrait;
	use AnalogRedstoneSignalEmitterTrait;
	use PoweredByRedstoneTrait;
	use StaticSupportTrait;
	use RedstoneComponentTrait;

	protected bool $isSubtractMode = false;
	private ?CallbackInventoryListener $callBack = null;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->isSubtractMode);
		$w->bool($this->powered);
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof Comparator){
			$this->signalStrength = $tile->getSignalStrength();
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof Comparator);
		$tile->setSignalStrength($this->signalStrength);
		 $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 1);
	}

	public function isSubtractMode() : bool{
		return $this->isSubtractMode;
	}

	/** @return $this */
	public function setSubtractMode(bool $isSubtractMode) : self{
		$this->isSubtractMode = $isSubtractMode;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 7 / 8)];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->isSubtractMode = !$this->isSubtractMode;
		$this->position->getWorld()->setBlock($this->position, $this);
		UpdateHelper::updateDiodeRedstone($this, Facing::opposite($this->getFacing()));
        return true;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN) !== SupportType::NONE;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        if ($this->callBack != null) {
            $block = $this->getSide($this->getFacing());
            $tile = $this->getPosition()->getWorld()->getTile($block->getPosition());
            if ($tile instanceof Container) {
                $inventory = $tile->getInventory();
                $inventory->getListeners()->remove($this->callBack);
            }

            if (PowerHelper::isNormalBlock($block)) {
                $block = $this->getSide($this->getFacing(), 2);
                $tile = $this->getPosition()->getWorld()->getTile($block->getPosition());
                if ($tile instanceof Container) {
                    $inventory = $tile->getInventory();
                    $inventory->getListeners()->remove($this->callBack);
                }
            }
        }

        parent::onBreak($item, $player, $returnedItems);
        UpdateHelper::updateDiodeRedstone($this, Facing::opposite($this->getFacing()));
        return true;
    }

	public function onScheduledUpdate(): void {
        $power = $this->recalculateUtilityPower();
        if ($power === null) $power = PowerHelper::getPower($this->getSide($this->getFacing()), $this->getFacing());

        $sidePower = 0;
        $face = Facing::rotateY($this->getFacing(), true);
        $side = $this->getSide($face);
        if ($side instanceof IRedstoneDiode || $side instanceof RedstoneWire) {
            $sidePower = $side->getWeakPower($face);
        }

        $face = Facing::opposite($face);
        $side = $this->getSide($face);
        if ($side instanceof IRedstoneDiode || $side instanceof RedstoneWire) {
            $sidePower = max($sidePower, $side->getWeakPower($face));
        }

        $power = $this->isSubtractMode() ? max(0, $power - $sidePower) : ($power >= $sidePower ? $power : 0);
        if ($this->getOutputSignalStrength() === $power) return;

        if (RedstoneEvent::isCallEvent()) {
            $event = new RedstoneSignalUpdateEvent($this, $power, $this->getOutputSignalStrength());
            $event->call();

            $power = $event->getNewSignal();
            if ($this->getOutputSignalStrength() == $power) return;
        }

        $this->setPowered($power > 0);
        $this->setOutputSignalStrength($power);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        UpdateHelper::updateDiodeRedstone($this, Facing::opposite($this->getFacing()));
	}

	private function recalculateUtilityPower(int $step = 1): ?int {
        $block = $this->getSide($this->getFacing(), $step);
        $tile = $this->getPosition()->getWorld()->getTile($block->getPosition());
        $power = 0;
        if ($tile instanceof Container) {
            $inventory = $tile->getInventory();
            $this->createCallBack($inventory);

            if (count($inventory->getContents()) != 0) {
                $stack = 0;
                for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
                    $item = $inventory->getItem($slot);
                    if ($item->getTypeId() === BlockTypeIds::AIR) continue;
                    $stack += $item->getCount() / $item->getMaxStackSize();
                }
                $power = 1 + ($stack / $inventory->getSize()) * 14;
            }
            return $power;
        }
        $this->callBack = null;

        if ($step === 2) $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 1);

        if ($block instanceof Cake) return (7 - $block->getBites()) * 2;
        if ($block instanceof EndPortalFrame) return $block->hasEye() ? 15 : 0;
		if ($block instanceof Furnace) {
          if ($tile instanceof TileFurnace) {
            return $tile->isLit() ? 15 : 0; 
          }
		}
		
        if ($block instanceof Jukebox) {
            $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 1);
            $record = $block->getRecord();
            if ($record === null) return 0;

            return match ($record->getRecordType()) {
                RecordType::DISK_13() => 1,
                RecordType::DISK_CAT() => 2,
                RecordType::DISK_BLOCKS() => 3,
                RecordType::DISK_CHIRP() => 4,
                RecordType::DISK_FAR() => 5,
                RecordType::DISK_MALL() => 6,
                RecordType::DISK_MELLOHI() => 7,
                RecordType::DISK_STAL() => 8,
                RecordType::DISK_STRAD() => 9,
                RecordType::DISK_WARD() => 10,
                RecordType::DISK_11() => 11,
                RecordType::DISK_WAIT() => 12,
				RecordType::DISK_PIGSTEP() => 13,
				RecordType::DISK_OTHERSIDE() => 14,
                default => 15
            };
        }
        if ($block instanceof ItemFrame) {
            if ($block->getFacing() !== $this->getFacing()) return 0;
            if ($block->getFramedItem() === null) return 0;
            return $block->getItemRotation() + 1;
        }

		if ($block instanceof BrewingStand) {
            if ($tile instanceof TileBrewingStand) {
               $inventory = $tile->getInventory();
                $this->createCallBack($inventory);

                $filledSlots = 0;
                for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
                   if (!$inventory->getItem($slot)->isNull()) {
                   $filledSlots++;
             }
          }

           return (int) round(($filledSlots / 4) * 15);
          }
		}

    if ($block instanceof Hopper) {    
		if ($tile instanceof TileHopper) {
    $inventory = $tile->getInventory();    
    $this->createCallBack($inventory);    

    if (count($inventory->getContents()) !== 0) {    
        $stack = 0;    
        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {    
            $item = $inventory->getItem($slot);    
            if ($item->getTypeId() === BlockTypeIds::AIR) continue;    
            $stack += $item->getCount() / $item->getMaxStackSize();    
            }    
            return 1 + ($stack / $inventory->getSize()) * 14;    
           }    
         return 0;    
		  }
		}

    if (block instanceof Chest && $block instanceof TrappedChest) {
    if ($tile instanceof TileChest) {
        $inventory = $tile->getInventory();
        $this->createCallBack($inventory);

        $totalSlots = $inventory->getSize();
        $filledSlots = 0;
        $stack = 0;

        foreach ($inventory->getContents() as $item) {
            if (!$item->isNull()) {
                $filledSlots++;
                $stack += $item->getCount() / $item->getMaxStackSize();
            }
        }

        if ($filledSlots > 0) {
            $power = 1 + ($stack / $totalSlots) * 14;
            return (int) min(15, $power);
        }

          return 0; 
	
   	    }
     }

		if ($block instanceof Barrel) {
        if ($tile instanceof TileBarrel) {      
        $inventory = $tile->getInventory();      
        $this->createCallBack($inventory);      

        if (count($inventory->getContents()) > 0) {      
            $stack = 0;      
            foreach ($inventory->getContents() as $item) {      
                $stack += $item->getCount() / $item->getMaxStackSize();      
            }      
            $power = 1 + ($stack / $inventory->getSize()) * 14;      
        }      
        return $power;      
      }
     }

		if ($block instanceof ShulkerBox) {
    if ($tile instanceof TileShulkerBox) {
       $inventory = $tile->getInventory();
        $this->createCallBack($inventory);

    if (count($inventory->getContents()) != 0) {
        $stack = 0;
        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            $item = $inventory->getItem($slot);
            if ($item->getTypeId() === BlockTypeIds::AIR) continue;
            $stack += $item->getCount() / $item->getMaxStackSize();
            }
             $power = 1 + ($stack / $inventory->getSize()) * 14;
             }
            return $power;
           }
		}

		if ($block instanceof Cauldron) {
        if ($tile instanceof TileCauldron) {
            return $tile->getFillLevel(); 
          }
          return 0;
		}

		if ($block instanceof Lectern) {
           if ($tile instanceof TileLectern) {
            return min(15, $tile->getViewedPage() + 1);
           }
          return 0;
		}

        if ($block instanceof ChiseledBookshelf) {    
         if ($tile instanceof TileChiseledBookshelf) {
        $bookCount = count(array_filter(ChiseledBookshelfSlot::cases(), fn($slot) => $tile->hasSlot($slot)));
        return $bookCount > 0 ? 1 + ($bookCount / 6) * 14 : 0; // Skala 1-15
         }
    
         return 0;
		}		
		//minecart with hopper, minecart with chest dispenser and dropper will be coming soon

        if ($step === 1 && PowerHelper::isNormalBlock($block)) return $this->recalculateUtilityPower(2);
        return null;
	}

    private function createCallBack(Inventory $inventory): void {
        if ($this->callBack === null) {
            $block = $this;
            $this->callBack = CallbackInventoryListener::onAnyChange(
                fn(Inventory $inventory) => $block->getPosition()->getWorld()->scheduleDelayedBlockUpdate($block->getPosition(), 1)
            );
        }

        $listeners = $inventory->getListeners();
        if ($listeners->contains($this->callBack)) return;

        $listeners->add($this->callBack);
    }

    public function getStrongPower(int $face): int {
        return $this->getWeakPower($face);
    }

    public function getWeakPower(int $face): int {
        return $this->isPowered() && $face === $this->getFacing() ? $this->getOutputSignalStrength() : 0;
    }

    public function isPowerSource(): bool {
        return $this->isPowered();
    }

    public function onRedstoneUpdate(): void {
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 1);
    }

    public function isConnect(int $face): bool {
        return true;
	}
}
