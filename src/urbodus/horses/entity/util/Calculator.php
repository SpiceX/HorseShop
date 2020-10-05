<?php

namespace urbodus\horses\entity\util;

use urbodus\horses\entity\BaseCreature;

class Calculator {

    /** @var BaseCreature */
    private $creature;

    public function __construct(BaseCreature $creature) {
        $this->creature = $creature;
    }

    /**
     * Recalculates every property of the creature and saves/updates it to the database.
     */
    public function recalculateAll(): void {
        $this->recalculateHealth();
        $this->recalculateSize();
        $this->recalculateDamage();
    }

    /**
     * Recalculates maximum health that the creature should have according to its configuration scalings.
     */
    public function recalculateHealth(): void {
        $creature = $this->getCreature();
        $creature->setMaxHealth(20);
        $creature->fullHeal();
    }

    /**
     * @return BaseCreature
     */
    public function getCreature(): BaseCreature {
        return $this->creature;
    }

    /**
     * Recalculates size that the creature should have according to its configuration scalings.
     *
     * @return bool
     */
    public function recalculateSize(): bool {
        $creature = $this->getCreature();
        $creatureOwner = $creature->getCreatureOwner();
        if($creatureOwner === null) {
            return false;
        }

        if($creature->getScale() > $creature->getMaxSize()) {
            $creature->setScale($creature->getMaxSize());
        } else {
            $scalingSize = 0.02;
            $creature->setScale(($creature->getStartingScale() + $scalingSize));
        }
        return true;
    }

    /**
     * Recalculates attack damage that the creature should have according to its configuration attack damage.
     */
    public function recalculateDamage(): void {
        $creature = $this->getCreature();

        $baseDamage = 4;
        $scalingDamage = 0.1;

        $creature->setAttackDamage((int) round($baseDamage + $scalingDamage));
    }

}
