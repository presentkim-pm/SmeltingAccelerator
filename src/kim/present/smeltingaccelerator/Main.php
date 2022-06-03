<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace kim\present\smeltingaccelerator;

use pocketmine\block\inventory\FurnaceInventory;
use pocketmine\block\tile\Furnace;
use pocketmine\event\inventory\FurnaceBurnEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase implements Listener{
    private SmeltingAccelerator $accelerator;

    protected function onEnable() : void{
        $accelerateMultiplier = $this->getConfig()->getNested("accelerate-multiplier", 2);
        if($accelerateMultiplier < 2){
            $this->getLogger()->error("Accelerate multiplier must be greater than 1");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $updateDelay = $this->getConfig()->getNested("update-delay", 0.25) * 20;
        if($updateDelay <= 0){
            $this->getLogger()->warning("Update delay must be greater than 0, It applied to 0.05 seconds.");
            $updateDelay = 1;
        }

        $this->accelerator = new SmeltingAccelerator(
            $this,
            (int) $accelerateMultiplier,
            (int) $updateDelay
        );

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function getAccelerator() : SmeltingAccelerator{
        return $this->accelerator;
    }

    /**
     * @priority HIGHEST
     * Handling the furnace burn event to register with the accelerator
     */
    public function onFurnaceBurn(FurnaceBurnEvent $event) : void{
        $this->accelerator->addFurnace($event->getFurnace());
    }

    /**
     * @priority HIGHEST
     * Handling inventory open event to re-register if furnace is not updated
     */
    public function onInventoryOpen(InventoryOpenEvent $event) : void{
        $inventory = $event->getInventory();
        if(!$inventory instanceof FurnaceInventory){
            return;
        }

        $pos = $inventory->getHolder();
        $tile = $pos->getWorld()->getTile($pos);
        if(!$tile instanceof Furnace){
            return;
        }

        $this->accelerator->addFurnace($tile);
    }
}