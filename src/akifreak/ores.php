<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace akifreak;
use pocketmine\scheduler\PluginTask;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use akifreak\Annihilation;
use pocketmine\block\Block;


class ores extends PluginTask {
	
 public function __construct(Annihilation $plugin, $level,$blockz,$x,$y,$z){
        parent::__construct($plugin);
        $this->plugin = $plugin;
		$this->level = $level;
        $this->blockz = $blockz;
        $this->x=$x;
        $this->y=$y;
        $this->z=$z;
    }
	 public function onRun($t) {
		 //$this->plugin->setChest($this->blockz,$this->x,$this->y,$this->z,$this->level);
		 $this->level->setBlock(new Vector3($this->x,$this->y,$this->z), Block::get($this->blockz), true);
	 }
}
