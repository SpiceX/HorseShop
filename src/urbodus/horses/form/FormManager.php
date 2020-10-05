<?php

/**
 * Copyright 2020-2022 LiTEK - Josewowgame
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace urbodus\horses\form;

use onebone\economyapi\EconomyAPI;
use pocketmine\item\Item;
use pocketmine\Player;
use urbodus\horses\form\elements\Button;
use urbodus\horses\form\elements\Image;
use urbodus\horses\form\types\MenuForm;
use urbodus\horses\HorseShop;
use urbodus\horses\provider\YamlProvider;

class FormManager
{
    /**
     * @var HorseShop
     */
    private $plugin;

    public function __construct(HorseShop $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param Player $player
     */
    public function sendShop(Player $player): void
    {
        $form = new MenuForm("§aHorse Shop", "§7Buy a horse, or armor horse.",
            [
                new Button("§2Buy Horse\n§7Get a pal with you", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/7/77/Chestnut_Horse.png", Image::TYPE_URL)),
                new Button("§2Armors\n§7Select a variety of armor", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/6/6e/Diamond_Horse_Armor_JE4_BE3.png", Image::TYPE_URL)),
            ],
            function (Player $player, Button $selected): void {
                if ($selected->getValue() === 0){
                    $horsePrice = $this->plugin->getYamlProvider()->getPricePerHorse();
                    if (EconomyAPI::getInstance()->myMoney($player) > $horsePrice){
                        $this->plugin->getEntityManager()->createHorse($player);
                        $player->sendMessage("§aYou have purchased a horse for §e$horsePrice");
                    }
                } else {
                    $this->sendArmorShop($player);
                }
            });
        $player->sendForm($form);
    }

    /**
     * @param Player $player
     */
    public function sendArmorShop(Player $player): void
    {
        $form = new MenuForm("§aArmor Horse Shop", "§7Get a nice armor: ", [
            new Button("Leather Armor", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/5/54/Leather_Horse_Armor_JE1_BE3.png", Image::TYPE_URL)),
            new Button("Gold Armor", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/d/d8/Iron_Horse_Armor_JE5_BE3.png", Image::TYPE_URL)),
            new Button("Iron Armor", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/8/8d/Golden_Horse_Armor_JE4_BE3.png", Image::TYPE_URL)),
            new Button("Diamond Armor", new Image("https://static.wikia.nocookie.net/minecraft_gamepedia/images/6/6e/Diamond_Horse_Armor_JE4_BE3.png", Image::TYPE_URL)),
        ], function (Player $player, Button $selected): void {
            $item = null;
            switch ($selected->getValue()){
                case 0:
                    if (!$this->canBuyArmor($player, YamlProvider::LEATHER_ARMOR)){
                        $player->sendMessage("§cYou do not have enough money.");
                        return;
                    }
                    $item = Item::get(Item::LEATHER_HORSE_ARMOR);
                    break;
                case 1:
                    if (!$this->canBuyArmor($player, YamlProvider::GOLD_ARMOR)){
                        $player->sendMessage("§cYou do not have enough money.");
                        return;
                    }
                    $item = Item::get(Item::GOLDEN_HORSE_ARMOR);
                    break;
                case 2:
                    if (!$this->canBuyArmor($player, YamlProvider::IRON_ARMOR)){
                        $player->sendMessage("§cYou do not have enough money.");
                        return;
                    }
                    $item = Item::get(Item::IRON_HORSE_ARMOR);
                    break;
                case 3:
                    if (!$this->canBuyArmor($player, YamlProvider::DIAMOND_ARMOR)){
                        $player->sendMessage("§cYou do not have enough money.");
                        return;
                    }
                    $item = Item::get(Item::DIAMOND_HORSE_ARMOR);
            }
            if ($item !== null){
                if ($player->getInventory()->canAddItem($item)){
                    $player->getInventory()->addItem($item);
                } else {
                    $player->getLevel()->dropItem($player->asVector3(), $item);
                }
                $player->sendMessage("§aArmor purchased with success.");
            }
        });
        $player->sendForm($form);
    }

    /**
     * @param Player $player
     * @param string $type
     * @return bool
     */
    private function canBuyArmor(Player $player, string $type): bool
    {
        return EconomyAPI::getInstance()->myMoney($player) > $this->plugin->getYamlProvider()->getArmorPrice($type);
    }

}