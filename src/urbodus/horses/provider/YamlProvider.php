<?php


namespace urbodus\horses\provider;


use pocketmine\utils\Config;
use urbodus\horses\HorseShop;

class YamlProvider
{
    public const LEATHER_ARMOR = 'leather_armor';
    public const GOLD_ARMOR = 'gold_armor';
    public const IRON_ARMOR = 'iron_armor';
    public const DIAMOND_ARMOR = 'diamond_armor';

    /** @var HorseShop */
    private $plugin;
    /** @var array */
    private $data;
    /** @var Config */
    private $config;

    /**
     * YamlProvider constructor.
     * @param HorseShop $plugin
     */
    public function __construct(HorseShop $plugin)
    {
        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
        $this->init();
    }

    public function init(): void
    {
        $this->data[] = $this->config->getAll();
    }

    /**
     * @return int
     */
    public function getPricePerHorse(): int
    {
        return (int)($this->data['price_per_horse'] ?? 5000);
    }

    /**
     * @param string $armor
     * @return int
     */
    public function getArmorPrice(string $armor): int
    {
        return (int)($this->data['armor_prices'][$armor] ?? 0);
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return HorseShop
     */
    public function getPlugin(): HorseShop
    {
        return $this->plugin;
    }
}