<?php

namespace urbodus\horses\entity;

use pocketmine\entity\Entity;
use pocketmine\Player;
use urbodus\horses\HorseShop;

class EntityManager
{
    /** @var HorseShop */
    private $plugin;

    private $playerHorses;

    /**
     * EntityManager constructor.
     * @param HorseShop $plugin
     */
    public function __construct(HorseShop $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @param Player $player
     * @param float $scale
     * @param bool $isBaby
     * @param bool $chested
     * @return BaseCreature|null
     */
    public function createHorse(Player $player, float $scale = 1.0, bool $isBaby = false, bool $chested = false): ?BaseCreature {
        $horses = $this->getHorsesFrom($player);
        if(is_array($horses)) {
            foreach ($horses as $horse) {
                $this->removePet($horse);
            }
        }

        $nbt = Entity::createBaseNBT($player, null, $player->yaw, $player->pitch);
        $nbt->setString("creatureOwner", $player->getName());
        $nbt->setFloat("scale", $scale);
        $nbt->setByte("isBaby", (int) $isBaby);
        $nbt->setByte("chested", (int) $chested);

        $entity = Entity::createEntity("Horse", $player->getLevel(), $nbt);
        if($entity instanceof BaseCreature) {
            $this->playerHorses[$player->getLowerCaseName()][] = $entity;
            return $entity;
        }
        return null;
    }

    /**
     * @param Player $player
     * @return BaseCreature
     */
    public function getRiddenHorse(Player $player): ?BaseCreature {
        foreach($this->getHorsesFrom($player) as $pet) {
            if($pet->isRidden()) {
                return $pet;
            }
        }
        return null;
    }

    /**
     * @param Player $player
     * @return array
     */
    public function getHorsesFrom(Player $player): array {
        return $this->playerHorses[$player->getLowerCaseName()] ?? [];
    }

    /**
     * Closes and removes the specified pet from cache
     * and calls PetRemoveEvent events.
     *
     * @param BaseCreature $pet
     * @param bool $close
     */
    public function removePet(BaseCreature $pet, bool $close = true): void {
        if($pet->isRidden()) {
            $pet->throwRiderOff();
        }
        if($close && !$pet->isClosed()) {
            $pet->close();
        }
        unset($this->playerHorses[strtolower($pet->getCreatureOwnerName())][array_search($pet, $this->playerHorses[strtolower($pet->getCreatureOwnerName())], true)]);
    }

    /**
     * @return HorseShop
     */
    public function getPlugin(): HorseShop
    {
        return $this->plugin;
    }
}