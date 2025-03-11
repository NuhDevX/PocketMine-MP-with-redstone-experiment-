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

namespace pocketmine\entity\object;

use pocketmine\entity\MinecartBase;
use pocketmine\block\ActivatorRail;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\PoweredRail;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class Minecart extends MinecartBase {

    public const TAG_NAME = "Minecart";

    public ?Player $rider = null;

   public static array $inMinecart = [];

    private int $hurtsTick = 15;

    public static function getNetworkTypeId(): string
    {
        return EntityIds::MINECART;
    }

    public function onUpdate(int $currentTick): bool
    {
        $this->hurtsTick--;
        if ($this->hurtsTick === 0) {
            $rail = $this->getCurrentRail();
            if ($rail instanceof ActivatorRail and $rail->isPowered()) {
                $this->hurtsTick = 15;
                $this->performHurtAnimation();
                $this->dismount();
            }
        }
        return parent::onUpdate($currentTick);
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $this->setRider($player);
        return parent::onInteract($player, $clickPos);
    }

    public function onCollideWithEntity(): void {
        $entity = $this->getWorld()->getNearestEntity($this->getPosition(), 5);
        if ($entity instanceof Living && !$entity instanceof Player) {
            if ($entity->getPosition()->equals($this->getPosition())) {
                $this->setRider($entity);
            }
        }
    }

    public function setRider(Player $entity): bool {
        if (isset(self::$inMinecart[$entity->getName()])) {
            return false;
        }

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->getId(), $entity->getId(), EntityLink::TYPE_RIDER, true, true, 1.0);

        $entity->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, 1, 0));
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);

        $this->rider = $entity;
        self::$inMinecart[$entity->getName()] = $this;
        NetworkBroadcastUtils::broadcastPackets($entity->getWorld()->getPlayers(), [$pk]);
        return true;
    }

    public function dismount(): bool {
        if ($this->rider === null) {
            return false;
        }

        $entity = $this->rider;
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, false);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);
        $entity->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, null);

        $this->rider = null;
        unset(self::$inMinecart[$entity->getName()]);
        return true;
    }

    public function getName(): string
    {
        return self::TAG_NAME;
    }
}
