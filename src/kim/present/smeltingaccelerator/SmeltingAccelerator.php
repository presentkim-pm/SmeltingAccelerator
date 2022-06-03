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

use Closure;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\tile\Furnace;
use pocketmine\block\tile\Tile;
use pocketmine\crafting\FurnaceRecipe;
use pocketmine\scheduler\Task;
use pocketmine\Server;

use function min;

final class SmeltingAccelerator extends Task{
    private int $lastTick;

    /**
     * @var Closure
     * @phpstan-var Closure(Furnace, int) : void $accelerate
     */
    private Closure $accelerate;

    /** @var array<string, Furnace> */
    private array $furnaces = [];

    public function __construct(
        private Main $plugin,
        private int $accelerateMultiplier,
        private int $updateDelay
    ){
        $this->accelerate = Closure::bind( //HACK: Closure bind hack to access inaccessible members
            closure: function(Furnace $furnace, int $additionalTime) : void{
                /** @var SmeltingAccelerator $this */
                while($additionalTime > 0){
                    if($furnace->maxFuelTime <= 0){
                        $this->removeFurnace($furnace);
                        return;
                    }
                    $inv = $furnace->getInventory();
                    $raw = $inv->getSmelting();
                    $product = $inv->getResult();
                    $type = $furnace->getFurnaceType();
                    $smelt = Server::getInstance()->getCraftingManager()->getFurnaceRecipeManager($type)->match($raw);
                    $canSmelt = ($smelt instanceof FurnaceRecipe && $raw->getCount() > 0 && (($smelt->getResult()->equals($product) && $product->getCount() < $product->getMaxStackSize()) || $product->isNull()));

                    if($canSmelt){
                        $currentAdditionalTime = min(
                                $additionalTime,
                                $type->getCookDurationTicks() - $furnace->cookTime,
                                $furnace->remainingFuelTime
                            ) - 1;
                        if($currentAdditionalTime <= 0){
                            $currentAdditionalTime = 0;
                        }
                        $furnace->remainingFuelTime -= $currentAdditionalTime;
                        $furnace->cookTime += $currentAdditionalTime;
                        $furnace->onUpdate();

                        $additionalTime -= $currentAdditionalTime + 1;
                    }else{
                        return;
                    }
                }
            },
            newThis: $this,
            newScope: Furnace::class
        );
    }

    public function getAccelerateMultiplier() : int{
        return $this->accelerateMultiplier;
    }

    public function getUpdateDelay() : int{
        return $this->updateDelay;
    }

    public function setAccelerateMultiplier(int $accelerateMultiplier) : void{
        $this->accelerateMultiplier = $accelerateMultiplier;
    }

    public function setUpdateDelay(int $updateDelay) : void{
        if($this->updateDelay === $updateDelay){
            return;
        }
        if($updateDelay < 1){
            throw new InvalidArgumentException("Update delay must be at least 1 tick");
        }
        $this->stopSchedule();
        $this->updateDelay = $updateDelay;
        $this->stopSchedule();
    }

    public function addFurnace(Furnace $furnace) : void{
        $this->furnaces[self::hashBlockPos($furnace)] = $furnace;

        if(!$this->isScheduled()){
            $this->startSchedule();
        }
    }

    public function removeFurnace(Furnace $furnace) : void{
        unset($this->furnaces[self::hashBlockPos($furnace)]);

        if(empty($this->furnaces)){
            $this->stopSchedule();
        }
    }

    public function isScheduled() : bool{
        return $this->getHandler() !== null;
    }

    public function startSchedule() : void{
        $this->lastTick = Server::getInstance()->getTick();
        $this->plugin->getScheduler()->scheduleRepeatingTask($this, $this->updateDelay);
    }

    public function stopSchedule() : void{
        $this->getHandler()?->cancel();
    }

    public function onRun() : void{
        $currentTick = Server::getInstance()->getTick();
        $additionalCookTime = ($currentTick - $this->lastTick) * ($this->accelerateMultiplier - 1) - 1;
        $this->lastTick = $currentTick;

        foreach($this->furnaces as $furnace){
            if($furnace->isClosed()){
                $this->removeFurnace($furnace);
                continue;
            }
            if($additionalCookTime > 0){
                ($this->accelerate)($furnace, $additionalCookTime);
            }
        }
    }

    public static function hashBlockPos(Block|Tile $obj) : string{
        $pos = $obj->getPosition();
        return $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ() . ":" . $pos->getWorld()->getFolderName();
    }
}