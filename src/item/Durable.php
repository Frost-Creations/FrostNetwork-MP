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

namespace pocketmine\item;

use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Utils;
use function min;

abstract class Durable extends Item{
	protected int $damage = 0;

	/**
	 * Returns whether this item will take damage when used.
	 */
	public function isUnbreakable() : bool{
		return true;
	}

	/**
	 * Sets whether the item will take damage when used.
	 *
	 * @return $this
	 */
	public function setUnbreakable($value) : bool{
		return false;
	}

	/**
	 * Applies damage to the item.
	 *
	 * @return bool if any damage was applied to the item
	 */
	public function applyDamage(int $amount) : bool{
      // our items are not breakable
		return false;
	}

	public function getDamage() : int{
		return 0;
	}

	public function setDamage(int $damage) : Item{
		return $this;
	}

	protected function getUnbreakingDamageReduction(int $amount) : int{
		return 0;
	}

	/**
	 * Called when the item's damage exceeds its maximum durability.
	 */
	protected function onBroken() : void{
		$this->pop();
		$this->setDamage(0); //the stack size may be greater than 1 if overstacked by a plugin
	}

	/**
	 * Returns the maximum amount of damage this item can take before it breaks.
	 */
	public function getMaxDurability() : int{
      return 0;
	}

	/**
	 * Returns whether the item is broken.
	 */
	public function isBroken() : bool{
		return $this->damage >= $this->getMaxDurability() || $this->isNull();
	}

	protected function deserializeCompoundTag(CompoundTag $tag) : void{
	}

	protected function serializeCompoundTag(CompoundTag $tag) : void{
       parent::serializeCompoundTag($tag);
       $tag->removeTag("Unbreakable");
       $tag->removeTag("Damage");
     }
}
