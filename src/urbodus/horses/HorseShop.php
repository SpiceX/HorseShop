<?php


namespace urbodus\horses;


use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use urbodus\horses\command\HShopCommand;
use urbodus\horses\entity\creature\Horse;
use urbodus\horses\entity\EntityManager;
use urbodus\horses\form\FormManager;
use urbodus\horses\provider\YamlProvider;

class HorseShop extends PluginBase implements Listener
{
    /** @var YamlProvider */
    private $yamlProvider;
    /** @var FormManager */
    private $formManager;
    /** @var EntityManager */
    private $entityManager;

    public function onEnable(): void
    {
        $plugin = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if ($plugin === null) {
            $this->getLogger()->error("This plugin needs EconomyAPI, please download and put into the plugins folder.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        Entity::registerEntity(Horse::class, true, ['Horse']);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->yamlProvider = new YamlProvider($this);
        $this->formManager = new FormManager($this);
        $this->entityManager = new EntityManager($this);
        $this->getServer()->getCommandMap()->register('hshop', new HShopCommand($this));
        $this->saveDefaultConfig();
    }

    public function ridePet(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        if ($packet instanceof PlayerInputPacket) {
            $player = $event->getPlayer();
            if ($this->isRidingAHorse($player)) {
                if ($packet->motionX === 0 && $packet->motionY === 0) {
                    return;
                }
                $pet = $this->entityManager->getRiddenHorse($player);
                if ($pet !== null) {
                    $pet->doRidingMovement($packet->motionX, $packet->motionY);
                }
            }
        } elseif ($packet instanceof InteractPacket) {
            if ($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
                $player = $event->getPlayer();
                if ($this->isRidingAHorse($player)) {
                    $ridden = $this->entityManager->getRiddenHorse($player);
                    if ($ridden !== null) {
                        $ridden->throwRiderOff();
                    }
                }
            }
        }
    }

    public function isRidingAHorse(Player $player): bool
    {
        foreach ($this->entityManager->getHorsesFrom($player) as $horse) {
            /** @var Horse $horse */
            if ($horse->isRidden()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return YamlProvider
     */
    public function getYamlProvider(): YamlProvider
    {
        return $this->yamlProvider;
    }

    /**
     * @return FormManager
     */
    public function getFormManager(): FormManager
    {
        return $this->formManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }
}