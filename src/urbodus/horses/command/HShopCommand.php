<?php

namespace urbodus\horses\command;

use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\Player;
use urbodus\horses\HorseShop;

class HShopCommand extends VanillaCommand
{
    /** @var HorseShop */
    private $plugin;

    /**
     * HShopCommand constructor.
     * @param HorseShop $plugin
     */
    public function __construct(HorseShop $plugin)
    {
        parent::__construct('hshop', 'hshop command', '/hshop help', ['hshop']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player){
            $this->plugin->getFormManager()->sendShop($sender);
        }
    }

    /**
     * @return HorseShop
     */
    public function getPlugin(): HorseShop
    {
        return $this->plugin;
    }
}