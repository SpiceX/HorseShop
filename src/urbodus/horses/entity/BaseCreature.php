<?php /** @noinspection NullPointerExceptionInspection */
/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection ThrowRawExceptionInspection */

namespace urbodus\horses\entity;

use Exception;
use InvalidArgumentException;
use pocketmine\entity\Attribute;
use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\entity\Rideable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\particle\HeartParticle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use urbodus\horses\entity\util\Calculator;
use urbodus\horses\HorseShop;

abstract class BaseCreature extends Creature implements Rideable
{

    public const STATE_STANDING = 0;
    public const STATE_SITTING = 1;

    public const TIER_COMMON = 1;
    public const TIER_UNCOMMON = 2;
    public const TIER_SPECIAL = 3;
    public const TIER_EPIC = 4;
    public const TIER_LEGENDARY = 5;

    public const LINK_RIDING = 0;
    public const LINK_RIDER = 1;

    /** @var string */
    public $name = "";
    /** @var float */
    public $scale = 1.0;
    /** @var float */
    protected $follow_range_sq = 1.2;
    /** @var int */
    protected $waitingTime = 0;
    /** @var Player|null */
    protected $rider;
    /** @var Vector3 */
    protected $rider_seatpos;
    /** @var bool */
    protected $riding = false;
    /** @var Vector3 */
    protected $seatpos;
    /** @var bool */
    protected $visibility = true;
    /** @var int */
    protected $attackDamage = 4;
    /** @var float */
    protected $speed = 1.0;
    /** @var bool */
    protected $canBeRidden = true;
    /** @var bool */
    protected $canBeChested = true;
    /** @var bool */
    protected $canAttack = true;
    /** @var bool */
    protected $canRide = true;
    /** @var Calculator */
    protected $calculator;
    /** @var float */
    protected $xOffset = 0.0;
    /** @var float */
    protected $yOffset = 0.0;
    /** @var float */
    protected $zOffset = 0.0;
    /** @var EntityLink[] */
    private $links = [];
    /** @var Player */
    private $creatureOwner;
    /** @var bool */
    private $dormant = false;
    /** @var bool */
    private $shouldIgnoreEvent = false;
    /** @var int */
    private $positionSeekTick = 60;
    /** @var float */
    private $maxSize = 10.0;
    /** @var Item */
    private $armor;

    public function __construct(Level $level, CompoundTag $nbt)
    {
        $this->creatureOwner = $level->getServer()->getPlayerExact($nbt->getString("creatureOwner"));
        if ($this->creatureOwner === null) {
            $this->close();
            return;
        }

        parent::__construct($level, $nbt);
    }


    /**
     * Returns the HorseShop. For internal usage.
     *
     * @return HorseShop|null
     */
    public function getLoader(): ?HorseShop
    {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("HorseShop");
        if ($plugin instanceof HorseShop) {
            return $plugin;
        }
        return null;
    }

    /**
     * Internal.
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return strtr($this->getName(), [
            " " => ""
        ]);
    }

    /**
     * Returns the name of the creature type.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array $properties
     */
    public function useProperties(array $properties): void
    {
        $this->speed = (float)$properties["Speed"];
        $this->canBeRidden = (bool)$properties["Can-Be-Ridden"];
        $this->canBeChested = (bool)$properties["Can-Be-Chested"];
        $this->canAttack = (bool)$properties["Can-Attack"];
        $this->canRide = (bool)$properties["Can-Sit-On-Owner"];
        $this->maxSize = (float)$properties["Max-Size"];
    }

    /**
     * @return bool
     */
    public function isChested(): bool
    {
        return $this->getGenericFlag(self::DATA_FLAG_CHESTED);
    }

    /**
     * @return bool
     */
    public function isBaby(): bool
    {
        return $this->getGenericFlag(self::DATA_FLAG_BABY);
    }

    public function follow(Entity $target, float $xOffset = 0.0, float $yOffset = 0.0, float $zOffset = 0.0): void {
        $x = $target->x + $xOffset - $this->x;
        $y = $target->y + $yOffset - $this->y;
        $z = $target->z + $zOffset - $this->z;

        $xz_sq = $x * $x + $z * $z;
        $xz_modulus = sqrt($xz_sq);

        if($xz_sq < $this->follow_range_sq) {
            $this->motion->x = 0;
            $this->motion->z = 0;
        } else {
            $speed_factor = $this->getSpeed() * 0.15;
            $this->motion->x = $speed_factor * ($x / $xz_modulus);
            $this->motion->z = $speed_factor * ($z / $xz_modulus);
        }
        $this->yaw = rad2deg(atan2(-$x, $z));
        $this->pitch = rad2deg(-atan2($y, $xz_modulus));

        $this->move($this->motion->x, $this->motion->y, $this->motion->z);
    }


    /**
     * @param bool $value
     */
    public function setChested(bool $value = true): void
    {
        if ($this->isChested() !== $value) {
            $this->setGenericFlag(self::DATA_FLAG_CHESTED, $value);
        }
    }

    /**
     * @return bool
     */
    public function getVisibility(): bool
    {
        return $this->visibility;
    }

    /**
     * @return Item
     */
    public function getArmor(): Item
    {
        return $this->armor;
    }

    /**
     * @param Item $armor
     */
    public function setArmor(Item $armor): void
    {
        $this->armor = $armor;
        $pk = new MobArmorEquipmentPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->chest = $armor;
        $pk->legs = Item::get(Item::AIR);
        $pk->head = Item::get(Item::AIR);
        $pk->feet = Item::get(Item::AIR);
        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->dataPacket($pk);
        }
    }

    public function setImmobile(bool $value = true): void
    {
        if (!$this->visibility && $value) {
            return;
        }
        parent::setImmobile($value);
    }


    public function spawnTo(Player $player): void
    {
        if (!$this->visibility) {
            return;
        }

        parent::spawnTo($player);
    }

    /**
     * Returns the player that owns this creature if they are online.
     *
     * @return Player
     */
    final public function getCreatureOwner(): Player
    {
        return $this->creatureOwner;
    }

    /**
     * Returns the actual name of the creature. Not to be confused with getName(), which returns the creature type name.
     *
     * @return string
     */
    public function getCreatureName(): string
    {
        return "";
    }

    /**
     * @param EntityDamageEvent $source
     */
    public function attack(EntityDamageEvent $source): void
    {
        if (!$this->visibility) {
            return;
        }
        if ($source instanceof EntityDamageByEntityEvent) {
            $player = $source->getDamager();
            if ($player instanceof Player) {
                $hand = $player->getInventory()->getItemInHand();
                if ($hand instanceof Food) {
                    if ($this->getHealth() === $this->getMaxHealth()) {
                        parent::attack($source);
                        return;
                    }
                    $nutrition = $hand->getFoodRestore();
                    $heal = (int)($nutrition / 40 * $this->getMaxHealth() + 2);
                    if ($this->getHealth() + $heal > $this->getMaxHealth()) {
                        $heal = $this->getMaxHealth() - $this->getHealth();
                    }
                    $hand->pop();
                    $player->getInventory()->setItemInHand($hand);
                    $this->heal(new EntityRegainHealthEvent($this, $heal, EntityRegainHealthEvent::CAUSE_SATURATION));
                    $this->getLevel()->addParticle(new HeartParticle($this->add(0, 2), 4));


                    $source->setCancelled();

                } elseif ($player->getName() === $this->getCreatureOwnerName()) {
                    if ($this->isChested() && $hand->getId() === Item::AIR) {
                        $source->setCancelled();
                    } elseif ($player->isSneaking() && $this->canRide) {
                        $this->sitOnOwner();
                    }
                }
            }
            if($player instanceof Player && !$player->isSneaking() && $this->canBeRidden && $player->getName() === $this->getCreatureOwnerName()) {
                $item = $player->getInventory()->getItemInHand();
                if($item->getId() === Item::SADDLE) {
                    $this->setRider($player);
                    $player->sendTip(TextFormat::GRAY . "Sneak to dismount...");
                    $source->setCancelled();
                }
                if ($item->getId() === Item::HORSE_ARMOR_LEATHER){
                    $this->setArmor(Item::get(Item::HORSE_ARMOR_LEATHER));
                    $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                    $source->setCancelled();
                }
                if ($item->getId() === Item::HORSE_ARMOR_GOLD){
                    $this->setArmor(Item::get(Item::HORSE_ARMOR_GOLD));
                    $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                    $source->setCancelled();
                }
                if ($item->getId() === Item::HORSE_ARMOR_IRON){
                    $this->setArmor(Item::get(Item::HORSE_ARMOR_IRON));
                    $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                    $source->setCancelled();
                }
                if ($item->getId() === Item::HORSE_ARMOR_DIAMOND){
                    $this->setArmor(Item::get(Item::HORSE_ARMOR_DIAMOND));
                    $player->getInventory()->setItemInHand(Item::get(Item::AIR));
                    $source->setCancelled();
                }
            }
        }
        parent::attack($source);
    }

    /**
     * @return bool
     */
    public function isRiding(): bool
    {
        return $this->riding;
    }

    /**
     * Returns the name of the owner of this creature.
     *
     * @return string
     */
    final public function getCreatureOwnerName(): string
    {
        return $this->creatureOwner->getName();
    }

    /**
     * Internal.
     *
     * @return string
     */
    public function getNameTag(): string
    {
        return "";
    }

    protected function initEntity(): void
    {
        parent::initEntity();

        $this->scale = $this->namedtag->getFloat("scale", $this->getScale());
        $this->setGenericFlag(self::DATA_FLAG_CHESTED, (bool)$this->namedtag->getByte("chested", 0));
        $this->setGenericFlag(self::DATA_FLAG_BABY, (bool)$this->namedtag->getByte("isBaby", 0));
        $this->setGenericFlag(self::DATA_FLAG_TAMED, true);

        $this->calculator = new Calculator($this);

        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);

        $this->setScale($this->scale);

        $this->spawnToAll();

        $this->getAttributeMap()->addAttribute(Attribute::getAttribute(Attribute::FOLLOW_RANGE));
        $this->setCanSaveWithChunk(false);

        $this->generateCustomCreatureData();
        $this->setImmobile();

        $scale = $this->getScale();
        $this->rider_seatpos = new Vector3(0, 1.8 + $scale * 0.9, -0.25);

        $this->seatpos = new Vector3(0, $scale * 0.4 - 0.3, 0);
    }

    public function generateCustomCreatureData(): void
    {

    }

    /**
     * Returns the network (entity) ID of the entity.
     *
     * @return int
     */
    final public function getNetworkId(): int
    {
        return static::NETWORK_ID;
    }

    /**
     * Returns the speed of this creature.
     *
     * @return float
     */
    public function getSpeed(): float
    {
        return $this->speed;
    }

    /**
     * @return float
     */
    public function getStartingScale(): float
    {
        return $this->scale;
    }

    /**
     * Returns the attack damage of this creature.
     *
     * @return float
     */
    public function getAttackDamage(): float
    {
        return $this->attackDamage;
    }

    /**
     * Sets the attack damage to the given amount.
     *
     * @param float $amount
     */
    public function setAttackDamage(float $amount): void
    {
        $this->attackDamage = $amount;
    }

    /**
     * Performs a special action of a creature every tick.
     */
    public function doCreatureUpdate(int $currentTick): bool
    {
        return true;
    }

    protected function applyGravity(): void
    {
        if ($this->isRiding()) {
            return;
        }

        parent::applyGravity();
    }

    protected function broadcastMovement(bool $teleport = false): void
    {
        if ($this->isRiding()) {
            return;
        }

        parent::broadcastMovement($teleport);
    }

    /**
     * @param $currentTick
     *
     * @return bool
     * @throws Exception
     */
    final public function onUpdate(int $currentTick): bool
    {
        if (!parent::onUpdate($currentTick) && $this->isClosed()) {
            return false;
        }
        if ($this->isRiding()) {
            $creatureOwner = $this->getCreatureOwner();

            $x = $creatureOwner->x - $this->x;
            $y = $creatureOwner->y - $this->y;
            $z = $creatureOwner->z - $this->z;

            if ($x !== 0.0 || $z !== 0.0 || $y !== -$creatureOwner->height) {
                $this->fastMove($x, $y + $creatureOwner->height, $z);
            }
            return false;
        }
        if (!$this->checkUpdateRequirements()) {
            return true;
        }
        if (!$this->isRidden()) {
            if (random_int(1, 60) === 1) {
                if ($this->getHealth() !== $this->getMaxHealth()) {
                    $this->heal(new EntityRegainHealthEvent($this, 1, EntityRegainHealthEvent::CAUSE_REGEN));
                }
            }
            $creatureOwner = $this->getCreatureOwner();
            if (!$this->isDormant() && ($this->getLevel()->getEntity($creatureOwner->getId()) === null || $this->distance($creatureOwner) >= 50)) {
                $this->teleport($creatureOwner);
                return true;
            }
            ++$this->positionSeekTick;
            if ($this->shouldFindNewPosition()) {

                if ((bool)random_int(0, 1)) {
                    $multiplicationValue = 1;
                } else {
                    $multiplicationValue = -1;
                }
                $offset_factor = $multiplicationValue * (3 + $this->getScale());
                $this->xOffset = lcg_value() * $offset_factor;
                $this->yOffset = lcg_value() * $offset_factor;
                $this->zOffset = lcg_value() * $offset_factor;

            }
        }
        $this->doCreatureUpdate($currentTick);
        return true;
    }

    /**
     * @return bool
     */
    public function shouldFindNewPosition(): bool
    {
        if ($this->positionSeekTick >= 60) {
            $this->positionSeekTick = 0;
            return true;
        }
        return false;
    }

    /**
     * @param bool $ignore
     */
    public function kill($ignore = false): void
    {
        $this->shouldIgnoreEvent = $ignore;
        parent::kill();
    }

    /**
     * Detaches the rider from the creature.
     *
     * @return bool
     */
    public function throwRiderOff(): bool
    {
        if (!$this->isRidden()) {
            return false;
        }

        $rider = $this->getRider();
        $this->rider = null;
        $rider->canCollide = true;
        $this->removeLink($rider, self::LINK_RIDER);

        $rider->setGenericFlag(self::DATA_FLAG_RIDING, false);
        if ($rider->isSurvival()) {
            $rider->setAllowFlight(false);
        }
        $rider->onGround = true;

        $this->width = $this->getDataPropertyManager()->getFloat(self::DATA_BOUNDING_BOX_WIDTH);
        $this->height = $this->getDataPropertyManager()->getFloat(self::DATA_BOUNDING_BOX_HEIGHT);
        $this->recalculateBoundingBox();
        return true;
    }

    /**
     * Returns the rider of the creature if it has a rider, and null if this is not the case.
     *
     * @return Player|null
     */
    public function getRider(): ?Player
    {
        return $this->rider;
    }

    /**
     * Sets the given player as rider on the creature, connecting it to it and initializing some things.
     *
     * @param Player $player
     *
     * @return bool
     */
    public function setRider(Player $player): bool
    {
        if ($this->isRidden()) {
            return false;
        }

        $this->rider = $player;
        $player->canCollide = false;
        $owner = $this->getCreatureOwner();
        $player->getDataPropertyManager()->setVector3(self::DATA_RIDER_SEAT_POSITION, $this->rider_seatpos);

        $this->addLink($player, self::LINK_RIDER);

        $player->setGenericFlag(self::DATA_FLAG_RIDING, true);
        $this->setGenericFlag(self::DATA_FLAG_SADDLED, true);

        if ($owner->isSurvival()) {
            $owner->setAllowFlight(true); // Set allow flight to true to prevent any 'kicked for flying' issues.
        }

        $this->width = max($player->width, $this->width);//adding more vertical area to the BB, so the horizontal can just be the maximum.
        $this->height = max(($this->rider_seatpos->y / 2.5) + $player->height, $this->height);
        $this->recalculateBoundingBox();
        return true;
    }

    /**
     * Heals the current creature back to full health.
     */
    public function fullHeal(): bool
    {
        $health = $this->getHealth();
        $maxHealth = $this->getMaxHealth();
        if ($health === $maxHealth) {
            return false;
        }
        $diff = $maxHealth - $health;
        $this->heal(new EntityRegainHealthEvent($this, $diff, EntityRegainHealthEvent::CAUSE_CUSTOM));
        return true;
    }


    /**
     * Returns the calculator connected to this creature, used to recalculate health, size, experience etc.
     *
     * @return Calculator
     */
    public function getCalculator(): Calculator
    {
        return $this->calculator;
    }

    /**
     * @return bool
     */
    public function shouldIgnoreEvent(): bool
    {
        return $this->shouldIgnoreEvent;
    }

    /**
     * @param float $motionX
     * @param float $motionZ
     *
     * @return void
     */
    abstract public function doRidingMovement(float $motionX, float $motionZ): void;

    /**
     * @return bool
     */
    protected function checkUpdateRequirements(): bool
    {
        if (!$this->visibility) {
            return false;
        }
        if ($this->isDormant()) {
            $this->despawnFromAll();
            return false;
        }
        if ($this->getCreatureOwner()->isClosed()) {
            $this->rider = null;
            $this->riding = false;
            $this->despawnFromAll();
            $this->setDormant();
            $this->close();
            return false;
        }
        if (!$this->getCreatureOwner()->isAlive()) {
            return false;
        }
        return true;
    }

    public function close(): void
    {
        if (!$this->closed) {
            parent::close();
        }
    }

    /**
     * Returns whether this creature is being ridden or not.
     *
     * @return bool
     */
    public function isRidden(): bool
    {
        return $this->rider !== null;
    }

    /**
     * Returns whether this creature is dormant or not. If this creature is dormant, it will not move.
     *
     * @return bool
     */
    public function isDormant(): bool
    {
        return $this->dormant;
    }

    /**
     * Sets the dormant state to this creature with the given value.
     *
     * @param bool $value
     */
    public function setDormant(bool $value = true): void
    {
        $this->dormant = $value;
    }

    /**
     * Adds a link to this creature.
     *
     * @param Entity $entity
     * @param int $type
     */
    public function addLink(Entity $entity, int $type): void
    {
        $this->removeLink($entity, $type);
        $viewers = $this->getViewers();

        switch ($type) {
            case self::LINK_RIDER:
                $link = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false
                );
                $link->fromEntityUniqueId = $this->getId();
                $link->type = self::STATE_SITTING;
                $link->toEntityUniqueId = $entity->getId();
                $link->bool1 = true;

                if ($entity instanceof Player) {
                    $pk = new SetActorLinkPacket();
                    $pk->link = $link;
                    $entity->dataPacket($pk);

                    $link_2 = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                    $link_2->fromEntityUniqueId = $this->getId();
                    $link_2->type = self::STATE_SITTING;
                    $link_2->toEntityUniqueId = 0;
                    $link_2->bool1 = true;

                    $pk = new SetActorLinkPacket();
                    $pk->link = $link_2;
                    $entity->dataPacket($pk);
                    unset($viewers[$entity->getLoaderId()]);
                }
                break;
            case self::LINK_RIDING:
                $link = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                $link->fromEntityUniqueId = $entity->getId();
                $link->type = self::STATE_SITTING;
                $link->toEntityUniqueId = $this->getId();
                $link->bool1 = true;

                if ($entity instanceof Player) {
                    $pk = new SetActorLinkPacket();
                    $pk->link = $link;
                    $entity->dataPacket($pk);

                    $link_2 = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                    $link_2->fromEntityUniqueId = $entity->getId();
                    $link_2->type = self::STATE_SITTING;
                    $link_2->toEntityUniqueId = 0;
                    $link_2->bool1 = true;

                    $pk = new SetActorLinkPacket();
                    $pk->link = $link_2;
                    $entity->dataPacket($pk);
                    unset($viewers[$entity->getLoaderId()]);
                }
                break;
            default:
                throw new InvalidArgumentException();
        }

        if (!empty($viewers)) {
            $pk = new SetActorLinkPacket();
            $pk->link = $link;
            $this->server->broadcastPacket($viewers, $pk);
        }

        $this->links[$type] = $link;
    }

    /**
     * Removes a link from this creature.
     *
     * @param Entity $entity
     * @param int $type
     */
    public function removeLink(Entity $entity, int $type): void
    {
        if (!isset($this->links[$type])) {
            return;
        }

        $viewers = $this->getViewers();

        switch ($type) {
            case self::LINK_RIDER:
                $link = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                $link->fromEntityUniqueId = $this->getId();
                $link->type = self::STATE_STANDING;
                $link->toEntityUniqueId = $entity->getId();
                $link->bool1 = true;

                if ($entity instanceof Player) {
                    $pk = new SetActorLinkPacket();
                    $pk->link = $link;
                    $entity->dataPacket($pk);

                    $link_2 = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                    $link_2->fromEntityUniqueId = $entity->getId();
                    $link_2->type = self::STATE_STANDING;
                    $link_2->toEntityUniqueId = 0;
                    $link_2->bool1 = true;

                    $pk = new SetActorLinkPacket();
                    $pk->link = $link_2;
                    $entity->dataPacket($pk);
                    unset($viewers[$entity->getLoaderId()]);
                }
                break;
            case self::LINK_RIDING:
                $link = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                $link->fromEntityUniqueId = $entity->getId();
                $link->type = self::STATE_STANDING;
                $link->toEntityUniqueId = $this->getId();
                $link->bool1 = true;

                if ($entity instanceof Player) {
                    $pk = new SetActorLinkPacket();
                    $pk->link = $link;
                    $entity->dataPacket($pk);

                    $link_2 = new EntityLink($this->getId(), 0, self::STATE_SITTING, true, false);
                    $link_2->fromEntityUniqueId = $entity->getId();
                    $link_2->type = self::STATE_STANDING;
                    $link_2->toEntityUniqueId = 0;
                    $link_2->bool1 = true;

                    $pk = new SetActorLinkPacket();
                    $pk->link = $link_2;
                    $entity->dataPacket($pk);
                    unset($viewers[$entity->getLoaderId()]);
                }
                break;
            default:
                throw new InvalidArgumentException();
        }

        unset($this->links[$type]);

        if (!empty($viewers)) {
            $pk = new SetActorLinkPacket();
            $pk->link = $link;
            $this->server->broadcastPacket($viewers, $pk);
        }
    }

    /**
     * @return bool
     */
    public function sitOnOwner(): bool
    {
        if ($this->riding) {
            return false;
        }
        $this->riding = true;
        $this->getDataPropertyManager()->setVector3(self::DATA_RIDER_SEAT_POSITION, $this->seatpos);
        $this->setGenericFlag(self::DATA_FLAG_RIDING, true);
        $this->setGenericFlag(self::DATA_FLAG_SADDLED, false);

        $this->addLink($this->getCreatureOwner(), self::LINK_RIDING);
        return true;
    }

    /**
     * @return bool
     */
    public function dismountFromOwner(): bool
    {
        if (!$this->riding) {
            return false;
        }
        $this->riding = false;
        $this->setGenericFlag(self::DATA_FLAG_RIDING, false);
        $creatureOwner = $this->getCreatureOwner();
        $this->removeLink($creatureOwner, self::LINK_RIDING);
        $this->teleport($creatureOwner);
        return true;
    }

    /**
     * @return float
     */
    public function getMaxSize(): float
    {
        return $this->maxSize;
    }
}
