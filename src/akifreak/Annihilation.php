<?php

namespace akifreak;

use pocketmine\block\Block;
use pocketmine\Command\Command;
use pocketmine\Command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\tile\Chest;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use akifreak\ores;
use pocketmine\level\particle\DustParticle;

class Annihilation extends PluginBase implements Listener {

    public $prefix = TextFormat::GRAY."[".TextFormat::DARK_AQUA."Anni".TextFormat::GRAY."]".TextFormat::WHITE." ";
    public $registerSign = false;
    public $registerSignWHO = "";
    public $registerSignArena = "Arena1";
    public $registerNexus = false;
    public $registerNexusWHO = "";
    public $registerNexusArena = "Arena1";
    public $registerNexusTeam = "WHITE";
    public $mode = 0;
    public $arena = "Arena1";
    public $lasthit = array();
    public $pickup = array();
    public $isShopping = array();
    public $breakableblocks = array();
	public $inv = array();

    public function onEnable(){
error_reporting(E_ALL & ~E_NOTICE);
        //Entity::registerEntity(Villager::class, true);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info($this->prefix.TextFormat::GREEN."Plugin has been Enabled");
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder()."Arenas");
        @mkdir($this->getDataFolder()."Maps");

        $files = scandir($this->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $filename = str_replace(".yml", "", $filename);

                $this->resetArena($filename);

                $levels = $this->getArenaWorlds($filename);
                foreach($levels as $levelname){
                    $level = $this->getServer()->getLevelByName($levelname);
                    if($level instanceof Level){
                        $this->getServer()->unloadLevel($level);
                    }
                    $this->copymap($this->getDataFolder() . "Maps/" . $levelname, $this->getServer()->getDataPath() . "worlds/" . $levelname);
                    $this->getServer()->loadLevel($levelname);
                }

                $this->getServer()->loadLevel($this->getWarteLobby($filename));
            }
        }
        $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
        if(empty($cfg->get("LobbyTimer"))){
            $cfg->set("LobbyTimer", 61);
            $cfg->save();
        }
        if(empty($cfg->get("GameTimer"))){
            $cfg->set("GameTimer", 30*60 +1);
            $cfg->save();
        }
        if(empty($cfg->get("EndTimer"))){
            $cfg->set("EndTimer", 16);
            $cfg->save();
        }
        if(empty($cfg->get("BreakableBlocks"))){
            $cfg->set("BreakableBlocks", array(Item::SANDSTONE, Item::CHEST));
            $cfg->save();
        }
        $this->breakableblocks = $cfg->get("BreakableBlocks");
        $shop = new Config($this->getDataFolder()."shop.yml", Config::YAML);


        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWRefreshSigns($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWGameSender($this), 20);

    }
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    #################################    ===[EIGENE FUNKTIONEN]===     #########################################
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    public function copymap($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    public function getTeams($arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
        $array = array();
        foreach($this->getAllTeams() as $team){
            if(!empty($config->getNested("Spawn.".$team))){
                $array[] = $team;
            }
        }

        return $array;
    }
    public function getPlayers($arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $playersXXX = $config->get("Players");

        $players = array();

        foreach ($playersXXX as $x){
            if($x != "steve steve"){
                $players[] = $x;
            }
        }

        return $players;
    }
    public function getTeam($pn){

        $pn = str_replace("ยง", "", $pn);
        $pn = str_replace(TextFormat::ESCAPE, "", $pn);
        $color = $pn{0};
        return $this->convertColorToTeam($color);
    }
    public function getAvailableTeams($arena){
        $teams = $this->getTeams($arena);
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $players = $this->getPlayers($arena);

        $availableTeams = array();

        $ppt = (int) $config->get("PlayersPerTeam");

        $teamcount = 0;
        foreach($teams as $team){

            foreach($players as $pn){
                $p = $this->getServer()->getPlayerExact($pn);
                if($p != null){
                    $pnn = $p->getNameTag();
                    if($this->getTeam($pnn) === $team){
                        $teamcount++;
                    }
                }
            }
            if($teamcount < $ppt){
                $availableTeams[] = $team;
            }
            $teamcount = 0;
        }

        $array = array();
        $teamcount = 0;
        $teamcount2 = 0;
        foreach($availableTeams as $team){

            if(count($array) == 0){
                $array[] = $team;
            } else {
                foreach($players as $pn){
                    $p = $this->getServer()->getPlayerExact($pn);
                    if($p != null){
                        $pnn = $p->getNameTag();
                        if($this->getTeam($pnn) === $team){
                            $teamcount++;
                        }
                    }
                }
                foreach($players as $pn){
                    $p = $this->getServer()->getPlayerExact($pn);
                    if($p != null){
                        $pnn = $p->getNameTag();
                        if($this->getTeam($pnn) === $array[0]){
                            $teamcount2++;
                        }
                    }
                }
                if($teamcount >= $teamcount2){
                    array_push($array, $team);
                } else {
                    array_unshift($array, $team);
                }
                $teamcount = 0;
                $teamcount2 = 0;
            }

        }

        return $array;
    }
    public function getAvailableTeam($arena){

        $teams = $this->getAvailableTeams($arena);
        if(isset($teams[0])){
            return $teams[0];
        } else {
            return "WHITE";
        }
    }
    public function getAliveTeams($arena){
        $alive = array();

        $teams = $this->getTeams($arena);
        $players = $this->getPlayers($arena);

        $teamcount = 0;
        foreach($teams as $team){
            foreach($players as $pn){
                $p = $this->getServer()->getPlayerExact($pn);
                if($p != null) {
                    $pnn = $p->getNameTag();
                    if ($this->getTeam($pnn) == $team) {
                        $teamcount++;
                    }
                }
            }
            if($teamcount != 0){
                $alive[] = $team;
            }
            $teamcount = 0;
        }

        return $alive;
    }
    public function convertColorToTeam($color){

        if($color == "9")return "BLUE";
        if($color == "c")return "RED";
        if($color == "a")return "GREEN";
        if($color == "e")return "YELLOW";
        if($color == "5")return "PURPLE";
        if($color == "0")return "BLACK";
        if($color == "7")return "GRAY";
        if($color == "b")return "AQUA";

        return "WHITE";
    }
    public function convertTeamToColor($team){

        if($team == "BLUE")return "9";
        if($team == "RED")return "c";
        if($team == "GREEN")return "a";
        if($team == "YELLOW")return "e";
        if($team == "PURPLE")return "5";
        if($team == "BLACK")return "0";
        if($team == "GRAY")return "7";
        if($team == "AQUA")return "b";

        return "f";
    }
    public function getTeamColor($team){

        if($team == "BLUE")return TextFormat::BLUE;
        if($team == "RED")return TextFormat::RED;
        if($team == "GREEN")return TextFormat::GREEN;
        if($team == "YELLOW")return TextFormat::YELLOW;
        if($team == "PURPLE")return TextFormat::DARK_PURPLE;
        if($team == "BLACK")return TextFormat::BLACK;
        if($team == "GRAY")return TextFormat::GRAY;
        if($team == "AQUA")return TextFormat::AQUA;

        return TextFormat::WHITE;
    }
    public function resetArena($arena, $mapreset = false){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);

        if($mapreset === true){
            $this->resetMaps($arena);
        }

        $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
        $config->set("GameTimer", $cfg->get("GameTimer"));
        $config->set("EndTimer", $cfg->get("EndTimer"));
        $config->set("Status", "Lobby");
        $config->set("Players", array("steve steve"));
        $config->save();
        foreach($this->getTeams($arena) as $team){
            $config->setNested("Nexus.".$team.".Alive", true);
			$config->setNested("Nexus.".$team.".Health", 75);
            $config->save();
        }

        $this->getLogger()->info(TextFormat::GREEN."Arena ".TextFormat::AQUA.$arena.TextFormat::GREEN." was loaded");
    }
    public function createArena($arena, $teams, $ppt){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);

        $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
        $config->set("GameTimer", $cfg->get("GameTimer"));
        $config->set("EndTimer", $cfg->get("EndTimer"));
        $config->set("Status", "Lobby");
        $config->set("Players", array("steve steve"));
        $config->set("Teams", $teams);
        $config->set("PlayersPerTeam", $ppt);
        $config->save();

        $this->getLogger()->info(TextFormat::GREEN."Arena ".TextFormat::AQUA.$arena.TextFormat::GREEN." was successfully created");
    }
    public function resetMaps($arena){
        $levels = $this->getArenaWorlds($arena);
        foreach($levels as $levelname){
            $level = $this->getServer()->getLevelByName($levelname);
            if($level instanceof Level){
                $this->getServer()->unloadLevel($level);
            }
            $this->copymap($this->getDataFolder() . "Maps/" . $levelname, $this->getServer()->getDataPath() . "worlds/" . $levelname);
            $this->getServer()->loadLevel($levelname);
        }
    }
    public function saveMaps($arena){
        $levels = $this->getArenaWorlds($arena);
        foreach($levels as $levelname){
            $level = $this->getServer()->getLevelByName($levelname);
            $this->copymap($this->getServer()->getDataPath() . "worlds/" . $levelname, $this->getDataFolder() . "Maps/" . $levelname);
        }
    }
    public function getFigthWorld($arena){
        $level = "noWorld";
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        foreach($this->getTeams($arena) as $team){
            $level = $config->getNested("Spawn.".$team.".World");
        }

        return $level;
    }
    public function getWarteLobby($arena){
        $levels = array();
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
        return $config->getNested("Spawn.Lobby.World");
    }
    public function getArenaWorlds($arena){
        $levels = array();
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        foreach($this->getAllTeams() as $team){
            if(!empty($config->getNested("Spawn.".$team.".World"))){
                $newlevel = $config->getNested("Spawn.".$team.".World");
                if(!in_array($newlevel, $levels)){
                    $levels[] = $newlevel;
                }
            }
        }

        return $levels;
    }
    public function setSpawn($arena, $team, Player $p){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $config->setNested("Spawn.".$team.".World", $p->getLevel()->getFolderName());
        $config->setNested("Spawn.".$team.".X", $p->getX());
        $config->setNested("Spawn.".$team.".Y", $p->getY());
        $config->setNested("Spawn.".$team.".Z", $p->getZ());
        $config->setNested("Spawn.".$team.".Yaw", $p->getYaw());
        $config->setNested("Spawn.".$team.".Pitch", $p->getPitch());
        $config->save();
    }
    public function setLobby($arena, Player $p){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $config->setNested("Spawn.Lobby.World", $p->getLevel()->getFolderName());
        $config->setNested("Spawn.Lobby.X", $p->getX());
        $config->setNested("Spawn.Lobby.Y", $p->getY());
        $config->setNested("Spawn.Lobby.Z", $p->getZ());
        $config->setNested("Spawn.Lobby.Yaw", $p->getYaw());
        $config->setNested("Spawn.Lobby.Pitch", $p->getPitch());
        $config->save();
    }
    public function arenaExists($arena){
        $files = scandir($this->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $filename = str_replace(".yml", "", $filename);

                if($filename == $arena){
                    return true;
                }
            }
        }
        return false;
    }
    public function TeleportToWaitingLobby($arena, Player $p){

        $p->setHealth(20);
        $p->setFood(20);
        $p->setGamemode(0);
        $p->getInventory()->clearAll();

        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $world = $config->getNested("Spawn.Lobby.World");
        $x = $config->getNested("Spawn.Lobby.X");
        $y = $config->getNested("Spawn.Lobby.Y");
        $z = $config->getNested("Spawn.Lobby.Z");
        $yaw = $config->getNested("Spawn.Lobby.Yaw");
        $pitch = $config->getNested("Spawn.Lobby.Pitch");

        $p->teleport($this->getServer()->getLevelByName($world)->getSafeSpawn(), 0, 0);
        $p->teleport(new Vector3($x, $y, $z), $yaw, $pitch);
    }
    public function getAllTeams(){
        $teams = array(
            "BLUE",//1
            "RED",//2
            "GREEN",//3
            "YELLOW",//4

            "PURPLE",//5
            "BLACK",//6
            "GRAY",//7
            "AQUA"//8
        );
        return $teams;
    }
    public function Debug($debug){
        $this->getLogger()->info($debug);
    }
    public function addPlayerToArena($arena, $name){

        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $players = $this->getPlayers($arena);

        $players[] = $name;

        $config->set("Players", $players);
        $config->save();
        //$this->getLogger()->info("Spieler: ".$name." , wurde in arena -> ".$arena." geschickt");
    }
    public function removePlayerFromArena($arena, $name){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $playersXXX = $this->getPlayers($arena);

        $players = array();
        foreach ($playersXXX as $pn){
            if($pn != $name){
                $players[] = $pn;
            }
        }

        $config->set("Players", $players);
        $config->save();
    }
    public function getArena(Player $p){
        $files = scandir($this->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $arena = str_replace(".yml", "", $filename);

                $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                if(in_array($p->getName(), $config->get("Players"))){
                    return $arena;
                }
            }
        }
        return "-";
    }
    public function inArena(Player $p){
        $files = scandir($this->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $arena = str_replace(".yml", "", $filename);

                $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                if(in_array($p->getName(), $config->get("Players"))){
                    return true;
                }
            }
        }
        return false;
    }
    public function TeleportToTeamSpawn(Player $p, $team, $arena){
        $p->setHealth(20);
        $p->setFood(20);
        $p->setGamemode(0);
        $p->getInventory()->clearAll();
        $p->getInventory()->setItem(0,Item::get(Item::STONE_SWORD,0,1));
		$p->getInventory()->setItem(1,Item::get(Item::BOW,0,1));
		 $p->getInventory()->setItem(2,Item::get(Item::STONE_PICKAXE,0,1));
		  $p->getInventory()->setItem(3,Item::get(Item::STONE_AXE,0,1));
		   $p->getInventory()->setItem(4,Item::get(Item::CRAFTING_TABLE,0,1));
		    $p->getInventory()->setItem(5,Item::get(Item::CLOCK,0,1));
			 $p->getInventory()->setItem(6,Item::get(Item::COMPASS,0,1));
			  $p->getInventory()->setItem(12,Item::get(Item::ARROW,0,32));
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $world = $config->getNested("Spawn.".$team.".World");
        $x = $config->getNested("Spawn.".$team.".X");
        $y = $config->getNested("Spawn.".$team.".Y");
        $z = $config->getNested("Spawn.".$team.".Z");
        $yaw = $config->getNested("Spawn.".$team.".Yaw");
        $pitch = $config->getNested("Spawn.".$team.".Pitch");

        if($p->getLevel() != $this->getServer()->getLevelByName($world)){
            $p->teleport($this->getServer()->getLevelByName($world)->getSafeSpawn(), 0, 0);
        }
        $p->teleport(new Vector3($x, $y, $z), $yaw, $pitch);
    }
    public function getTeamByBlockDamage($damage){
        if($damage == 10){
            return "PURPLE";
        }
        if($damage == 9){
            return "AQUA";
        }
        if($damage == 4){
            return "YELLOW";
        }
        if($damage == 5){
            return "GREEN";
        }
        if($damage == 11){
            return "BLUE";
        }
        if($damage == 14){
            return "RED";
        }
        if($damage == 15){
            return "BLACK";
        }
        if($damage == 7){
            return "GRAY";
        }
        return "WHITE";
    }
    public function getWoolDamageByTeam($team){
        if($team == "BLUE"){
            return 11;
        }
        if($team == "RED"){
            return 14;
        }
        if($team == "GREEN"){
            return 5;
        }
        if($team == "YELLOW"){
            return 4;
        }
        if($team == "AQUA"){
            return 9;
        }
        if($team == "BLACK"){
            return 15;
        }
        if($team == "PURPLE"){
            return 10;
        }
        if($team == "GRAY"){
            return 7;
        }
        return 0;
    }
    public function setTeamSelectionItems(Player $player, $arena){
        $player->getInventory()->clearAll();

        $player->setNameTag($player->getName());

        $teams = $this->getTeams($arena);
        $num = 0;
        foreach($teams as $team){
            $teamwool = $this->getWoolDamageByTeam($team);
            $player->getInventory()->setItem($num, Item::get(Item::WOOL, $teamwool, 1));
			$num++;
        }
    }
    public function getArenaStatus($arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
        $status = $config->get("Status");

        return $status;
    }
    public function sendIngameScoreboard(Player $p, $arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
		$gametimer=$config->get("GameTimer");
		if($gametimer >= 1440 && $gametimer >= 1081){
			$phase = "Phase-1";
			$time = $gametimer - 1080;
		}
		elseif($gametimer >= 1080 && $gametimer >= 721){
			$phase = "Phase-2";
			$time = $gametimer - 720;
		}
		elseif($gametimer >= 720 && $gametimer >= 361){
			$phase = "Phase-3";
			$time = $gametimer - 360;
		}
		elseif($gametimer >= 360 ){
			$phase = "Phase-4";
			$time = $gametimer - 359;
		}
		else{
			$phase = "Phase-5";
			$time = $gametimer - 1080;
		}
        $popup = TextFormat::GRAY." [".TextFormat::GOLD."{$phase} {$time}".TextFormat::GRAY."]\n\n";
        $teams = $this->getTeams($arena);
        $teamscount = 0;
        if(count($teams) >= 4){
            foreach($teams as $team) {
                if($teamscount == 4){
                    $popup = $popup."\n";
                }
                if (in_array($team, $this->getAliveTeams($arena))) {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::GREEN . "+" . TextFormat::GRAY . "]";
                } else {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::RED . "-" . TextFormat::GRAY . "]";
                }

                $teamscount++;
            }

        } else {
            foreach($teams as $team) {
                if (in_array($team, $this->getAliveTeams($arena))) {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::GREEN . "x" . TextFormat::GRAY . "]";
                } else {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::RED . "x" . TextFormat::GRAY . "]";
                }
            }
        }
		$this->getServer()->getPluginManager()->getPlugin("ShadedCore")->addBossBar($p,$popup,$time);
      //  $p->sendPopup($popup);
    }
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    ###################################    ===[EVENTS]===     ##################################################
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
	
	
	
    public function onItemDrop(PlayerDropItemEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $item = $event->getItem();

        if($item->getId() == Item::WOOL){
            if($this->inArena($player)){
                $arena = $this->getArena($player);
                $team = $this->getTeamByBlockDamage($item->getDamage());
                $event->setCancelled();

                if($this->getArenaStatus($arena) == "Lobby") {
                    if($team != $this->getTeam($player->getNameTag())){
                        if (in_array($team, $this->getAvailableTeams($arena))) {
                            $player->setNameTag($this->getTeamColor($team) . $name);
                            $player->sendMessage($this->prefix . "You have joined team " . TextFormat::GOLD . $team);
                          //  $player->getInventory()->removeItem($item);
                          //  $player->getInventory()->addItem($item);
                        } else {
                            $player->sendMessage($this->prefix . "The team " . TextFormat::GOLD . $team . TextFormat::WHITE . " is full!");
                          //  $player->getInventory()->removeItem($item);
                          //  $player->getInventory()->addItem($item);
                        }
                    } else {
                        $player->sendMessage($this->prefix . "You are already in this team " . TextFormat::GOLD . $team);
                     //   $player->getInventory()->removeItem($item);
                     //   $player->getInventory()->addItem($item);
                    }
                }
            }
        }
    }
    public function onChat(PlayerChatEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();

        if($this->inArena($player)) {
            $arena = $this->getArena($player);
            $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
            $team = $this->getTeam($player->getNameTag());
            $players = $this->getPlayers($arena);
            $status = $config->get("Status");
            $msg = $event->getMessage();
            $words = explode(" ", $msg);

            if($status == "Lobby"){
                $event->setCancelled();
                foreach($players as $pn){
                    $p = $this->getServer()->getPlayerExact($pn);
                    if($p != null){
                        $p->sendMessage($name." >> ".$msg);
                    }
                }
            } else {
                if ($words[0] === "@a" or $words[0] === "@all") {
                    array_shift($words);
                    $msg = implode(" ", $words);
                    $event->setCancelled();
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if ($p != null) {
                            $p->sendMessage(TextFormat::GRAY . "[" . TextFormat::GREEN . "ALL" . TextFormat::GRAY . "] " . $player->getNameTag() . TextFormat::GRAY . " >> " . TextFormat::WHITE . $msg);
                        }
                    }
                } else {
                    $event->setCancelled();
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if ($p != null) {
                            if ($this->getTeam($p->getNameTag()) == $this->getTeam($player->getNameTag())) {
                                //teamchat
                                $p->sendMessage(TextFormat::GRAY . "[" . $this->getTeamColor($this->getTeam($player->getNameTag())) . "Team" . TextFormat::GRAY . "] " . $player->getNameTag() . TextFormat::GRAY . " >> " . TextFormat::WHITE . $msg);
                            }
                        }
                    }
                }
            }
        }
    }
    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $this->lasthit[$player->getName()] = "no";
        $this->isShopping[$player->getName()] = "nein";
    }
    public function onRespawn(PlayerRespawnEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();

        if($this->inArena($player)){
            $arena = $this->getArena($player);

            $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
            $team = $this->getTeam($player->getNameTag());

            if($config->getNested("Nexus.".$team.".Alive") == true){

                $world = $config->getNested("Spawn.".$team.".World");
                $x = $config->getNested("Spawn.".$team.".X");
                $y = $config->getNested("Spawn.".$team.".Y");
                $z = $config->getNested("Spawn.".$team.".Z");

                $level = $this->getServer()->getLevelByName($welt);

                $event->setRespawnPosition(new Position($x, $y, $z, $level));
            } else {
                $event->setRespawnPosition($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $player->sendMessage($this->prefix.TextFormat::RED."Your Nexus is destroyed; So, You are unable to Respawn!");
                $this->removePlayerFromArena($arena, $name);
                $this->lasthit[$player->getName()] = "no";
                $player->setNameTag($player->getName());
            }

        }
    }
    public function onDeath(PlayerDeathEvent $event){
        $player = $event->getEntity();
        if($player instanceof Player){
            if($this->inArena($player)){
                $event->setDeathMessage("");
                $arena = $this->getArena($player);
                $cause = $player->getLastDamageCause();
                $players = $this->getPlayers($arena);

                if ($cause instanceof EntityDamageByEntityEvent) {
                    $killer = $cause->getDamager();
                    if ($killer instanceof Player) {
                        foreach ($players as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if($p != null) {
                                $p->sendMessage($this->prefix . $killer->getNameTag() . TextFormat::GRAY. " has killed " . $player->getNameTag() . TextFormat::GRAY);
                            }
                        }
                    } else {
                        foreach ($players as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if($p != null) {
                                $p->sendMessage($this->prefix . $player->getNameTag() . TextFormat::GRAY . " died!");
                            }
                        }
                    }
                } else {
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if($p != null) {

                            if($this->lasthit[$player->getName()] != "no"){
                                $p2 = $this->getServer()->getPlayerExact($this->lasthit[$player->getName()]);
                                if($p2 != null){
                                    $p->sendMessage($this->prefix . $p2->getNameTag() . TextFormat::WHITE. " has killed " . $player->getNameTag() . TextFormat::WHITE);
                                    $this->lasthit[$player->getName()] = "no";
                                } else {
                                    $p->sendMessage($this->prefix . $player->getNameTag() . TextFormat::GRAY . " died");
                                }
                            } else {
                                $p->sendMessage($this->prefix . $player->getNameTag() . TextFormat::GRAY . " died");
                            }
                        }
                    }
                }
            }
        }
    }
    
    public function onHit(EntityDamageEvent $event){
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if($damager instanceof Player) {
                    if($this->inArena($damager)) {
                        $event->setCancelled();
                    }
                }
            }
        } else {
            if($this->inArena($player)) {
                $arena = $this->getArena($player);

                $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

                if($config->get("Status") == "Lobby"){
                    $event->setCancelled();
                }
            }
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if($damager instanceof Player){
                    if($this->inArena($player)) {
                        $arena = $this->getArena($player);

                        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

                        if($config->get("Status") == "Lobby"){
                            $event->setCancelled();
                        } else {
                            if($this->getTeam($damager->getNameTag()) == $this->getTeam($player->getNameTag())){
                                $event->setCancelled();
                                $damager->sendPopup(TextFormat::RED."Please don't attack your teamates");
                            } else {
                                $this->lasthit[$player->getName()] = $damager->getName();
                            }
                        }
                    }
                }
            }
        }
    }
    public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if($this->inArena($player)){
            $arena = $this->getArena($player);
            $cause = $player->getLastDamageCause();
            $players = $this->getPlayers($arena);

            if($player->getY() <= 4){
                $player->setHealth(0);
            }

        }
    }


    public function onPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        if($this->inArena($player)) {

            $arena = $this->getArena($player);

            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);

            if($config->get("Status") == "Lobby"){
                $event->setCancelled();

                if($block->getId() == Block::WOOL){
                    $item = Item::get($block->getId(), $block->getDamage(), 1);

                    $arena = $this->getArena($player);
                    $team = $this->getTeamByBlockDamage($block->getDamage());
                    $event->setCancelled();
                    if($team != $this->getTeam($player->getNameTag())){
                        if (in_array($team, $this->getAvailableTeams($arena))) {
                            $player->setNameTag($this->getTeamColor($team) . $name);
                            $player->sendMessage($this->prefix . "You are now in team " . TextFormat::GOLD . $team);

                          //  $player->getInventory()->removeItem($item);
                          //  $player->getInventory()->addItem($item);
                        } else {
                            $player->sendMessage($this->prefix . "The team " . TextFormat::GOLD . $team . TextFormat::WHITE . " is full!");
                         //   $player->getInventory()->removeItem($item);
                         //   $player->getInventory()->addItem($item);
                        }
                    } else {
                        $player->sendMessage($this->prefix . "You are already on team " . TextFormat::GOLD . $team);
                      //  $player->getInventory()->removeItem($item);
                      //  $player->getInventory()->addItem($item);
                    }
                }
            } else {
                if (!in_array($block->getId(), $this->breakableblocks)) {
                //    $event->setCancelled();
                }
            }
        }
    }
    public function onBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $block2 = $player->getLevel()->getBlock(new Vector3($block->getX(), $block->getY(), $block->getZ()), false);
		$level = $player->getLevel();
		 $blockz=$player->getLevel()->getBlockIdAt($block->getX(), $block->getY(), $block->getZ());
		 $x=$block->getX();
		 $y=$block->getY();
		 $z=$block->getZ();
        if($this->inArena($player)) {

            $arena = $this->getArena($player);

            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);

            $team = $this->getTeamByBlockDamage($block2->getDamage());
			if($config->get("Nexus")["GREEN"]["X"] == $block->getX() && $config->get("Nexus")["GREEN"]["Y"] == $block->getY() && $config->get("Nexus")["GREEN"]["Z"] == $block->getZ()){
            $team="GREEN";
			}
			elseif($config->get("Nexus")["YELLOW"]["X"] == $block->getX() && $config->get("Nexus")["YELLOW"]["Y"] == $block->getY() && $config->get("Nexus")["YELLOW"]["Z"] == $block->getZ()){
            $team="YELLOW";
			}
			elseif($config->get("Nexus")["RED"]["X"] == $block->getX() && $config->get("Nexus")["RED"]["Y"] == $block->getY() && $config->get("Nexus")["RED"]["Z"] == $block->getZ()){
            $team="RED";
			}
			elseif($config->get("Nexus")["BLUE"]["X"] == $block->getX() && $config->get("Nexus")["BLUE"]["Y"] == $block->getY() && $config->get("Nexus")["BLUE"]["Z"] == $block->getZ()){
            $team="BLUE";
			}
            if($config->get("Status") != "Lobby"){
if($blockz == 14 || $blockz == 15 || $blockz == 16 || $blockz == 21 || $blockz == 56 || $blockz == 73 || $blockz == 73 || $blockz == 129){
	$event->setCancelled();
foreach($event->getDrops() as $item){
	if($item->getID() == Item::IRON_ORE){
		$item = Item::get(Item::IRON_INGOT,0,1);
	}
	if($item->getID() == Item::GOLD_ORE){
		$item = Item::get(Item::GOLD_INGOT,0,1);
	}
$player->getInventory()->addItem($item);
switch($blockz){
case 14:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 240);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 15:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 300);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 16:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 120);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 21:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 120);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 56:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 500);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 73:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 120);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 74:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 120);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
case 129:
$this->getServer()->getScheduler()->scheduleDelayedTask(new ores($this, $level,$blockz,$x,$y,$z), 120);
$player->getLevel()->setBlock(new Vector3($x,$y,$z), Block::get(4), true);
break;
}
}
}
                if($block->getId() == 121) { // END STONE
                                               // ADD CODE TO REMOVE HEALTH FROM END STONE
                    if ($team != $this->getTeam($player->getNameTag())) {
						$health = $config->getNested("Nexus." . $team . ".Health");
						$hp = $health -1;
						if($health >= 1){
						$config->setNested("Nexus." . $team . ".Health", $hp);
						$config->save();
						$event->setCancelled();
							$x = $event->getBlock()->getX();
        $y = $event->getBlock()->getY();
        $z = $event->getBlock()->getZ();
 $r = 0;
        $g = 255;
        $b = 255;
        $center = new Vector3($x+1, $y, $z);
        $radius = 0.5;
        $count = 100;
        $particle = new DustParticle($center, $r, $g, $b, 1);
          for($yaw = 0, $y = $center->y; $y < $center->y + 4; $yaw += (M_PI * 2) / 20, $y += 1 / 20){
              $x = -sin($yaw) + $center->x;
              $z = cos($yaw) + $center->z;
              $particle->setComponents($x, $y, $z);
              $player->getLevel()->addParticle($particle); 
		  }
						$player->sendPopup($this->prefix . "Team " . $team . "'s Nexus is at {$hp}HP!");
						}
						else
						{
						$config->setNested("Nexus." . $team . ".Alive", false);
                        $config->save();
                        $event->setDrops(array());
                           	$x = $event->getBlock()->getX();
        $y = $event->getBlock()->getY();
        $z = $event->getBlock()->getZ();
 $r = 0;
        $g = 255;
        $b = 255;
        $center = new Vector3($x+1, $y, $z);
        $radius = 0.5;
        $count = 100;
        $particle = new DustParticle($center, $r, $g, $b, 1);
          for($yaw = 0, $y = $center->y; $y < $center->y + 4; $yaw += (M_PI * 2) / 20, $y += 1 / 20){
              $x = -sin($yaw) + $center->x;
              $z = cos($yaw) + $center->z;
              $particle->setComponents($x, $y, $z);
              $player->getLevel()->addParticle($particle); 
		  }
                        $player->sendMessage($this->prefix . "You have destroyed Team " . $team . "'s Nexus!");
						
                        foreach ($this->getPlayers($arena) as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                if ($team == $this->getTeam($p->getNameTag())) {
                                    $p->sendMessage($this->prefix . TextFormat::RED . "Your Nexus has been destroyed");
                                } else {
                                    $p->sendMessage($this->prefix . "The " . TextFormat::GOLD . $team . TextFormat::WHITE . " Team's Nexus has been destroyed!");
                                }

                            }
						}
                        }
                    } else {
                        $player->sendMessage($this->prefix . "You can't destroy your own Nexus!");
                        $event->setCancelled();
                    }
                }
                elseif(!in_array($block->getId(), $this->breakableblocks)){
                   // $event->setCancelled();
                }
            } else {
                $event->setCancelled();
            }



        }
	}
    public function onInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $tile = $player->getLevel()->getTile($block);

        if($this->registerNexus == true && $this->registerNexusWHO == $name){

            $arena = $this->registerNexusArena;
            $team = $this->registerNexusTeam;

            $this->registerNexus = false;

            $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

            $config->setNested("Nexus.".$team.".World", $block->getLevel()->getFolderName());
            $config->setNested("Nexus.".$team.".X", $block->getX());
            $config->setNested("Nexus.".$team.".Y", $block->getY());
            $config->setNested("Nexus.".$team.".Z", $block->getZ());
            $config->setNested("Nexus.".$team.".Alive", true);

            $config->save();

            $player->sendMessage(TextFormat::GREEN . "The Nexus for Team " . TextFormat::AQUA . $team . TextFormat::GREEN . " for the Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . " has been registered");
            $player->sendMessage(TextFormat::GREEN . "Setup -> /bw help");
        }

        if($tile instanceof Sign){
            $text = $tile->getText();


            if($this->registerSign == true && $this->registerSignWHO == $name){

                $arena = $this->registerSignArena;

                $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

                $teams = (int) $config->get("Teams");
                $ppt = (int) $config->get("PlayersPerTeam");

                $maxplayers = $teams * $ppt;


                $tile->setText($this->prefix, $arena." ".$teams."x".$ppt, TextFormat::GREEN."Loading...", TextFormat::YELLOW."0 / ".$maxplayers);
                $this->registerSign = false;

                $player->sendMessage(TextFormat::GREEN . "The sign for Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . " is set!");
                $player->sendMessage(TextFormat::GREEN . "Setup -> /bw help");
            }
            elseif($text[0] == $this->prefix){

                if($text[2] == TextFormat::GREEN."Join"){

                    $arena = substr($text[1], 0, -4);
                    $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                    $status = $config->get("Status");
                    $maxplayers = $config->get("PlayersPerTeam") * $config->get("Teams");
                    $players = count($config->get("Players"));

                    if($status == "Lobby"){
                        if($players < $maxplayers) {
                            $this->TeleportToWaitingLobby($arena, $player);
                            $this->setTeamSelectionItems($player, $arena);
                            $this->addPlayerToArena($arena, $name);
                        } else {
                            $player->sendMessage($this->prefix . TextFormat::RED . "You can't join this game");
                        }
                    } else {
                        $player->sendMessage($this->prefix.TextFormat::RED."You can't enter this match");
                    }
                } else {
                    $player->sendMessage($this->prefix.TextFormat::RED."You can't Appear in this match");
                }

            }
        }

    }
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    ###################################    ===[COMMANDS]===     ################################################
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){

        $name = $sender->getName();
        if($cmd->getName() == "Start" && $sender->hasPermission("bw.forcestart")){
            if($sender instanceof Player){
                if($this->inArena($sender)){
                    $arena = $this->getArena($sender);

                    $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

                    $config->set("LobbyTimer", 5);
                    $config->save();
                } else {
                    $sender->sendMessage(TextFormat::RED."You're not in a Arena");
                }
            }
        }
        if($cmd->getName() == "anni" && $sender->isOP()){
            if(!empty($args[0])){
                if(strtolower($args[0]) == "help" && $sender->isOP()){
                    $sender->sendMessage(TextFormat::GRAY."===============");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni help ".TextFormat::GRAY."[".TextFormat::RED."See all Anni Commands".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni regsign <Arena> ".TextFormat::GRAY."[".TextFormat::RED."Register a Arena Sign".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni savemaps <Arena> ".TextFormat::GRAY."[".TextFormat::RED."Save a Arena Map".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni addarena <ArenaName> <Teams> <Players per team> ".TextFormat::GRAY."[".TextFormat::RED."Add a Arena".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni setlobby <Arena>".TextFormat::GRAY."[".TextFormat::RED."set Arena Lobby".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni setspawn <Arena> <Team>".TextFormat::GRAY."[".TextFormat::RED."set Team Spawn".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."-> ".TextFormat::DARK_AQUA."/anni setnexus <Arena> <Team>".TextFormat::GRAY."[".TextFormat::RED."set Team's Nexus".TextFormat::GRAY."]");
                    $sender->sendMessage(TextFormat::GRAY."===============");
                }
                elseif(strtolower($args[0]) == "regsign" && $sender->isOP()){
                    if(!empty($args[1])) {
                        $arena = $args[1];
                        if($this->arenaExists($arena)) {
                            $this->registerSign = true;
                            $this->registerSignWHO = $name;
                            $this->registerSignArena = $arena;
                            $sender->sendMessage(TextFormat::GREEN . "Now tap a sign");
                        } else {
                            $sender->sendMessage(TextFormat::RED."Arena doesn't exist!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/bw regsign <ArenaName>");
                    }
                }
                elseif(strtolower($args[0]) == "savemaps" && $sender->isOP()){
                    if(!empty($args[1])) {
                        $arena = $args[1];
                        if($this->arenaExists($arena)) {
                            $this->saveMaps($arena);
                            $sender->sendMessage(TextFormat::GREEN . "You have saved the Map for Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . "!");
                        } else {
                            $sender->sendMessage(TextFormat::RED."Arena doesn't exist");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/bw savemaps <ArenaName>");
                    }
                }
                elseif(strtolower($args[0]) == "addarena" && $sender->isOP()){
                    if(!empty($args[1]) && !empty($args[2]) && !empty($args[3])) {
                        $arena = $args[1];
                        $teams = (int)$args[2];
                        $ppt = (int)$args[3]; //ppt = PlayersPerTeam

                        if($teams <= 8){
                            $this->createArena($arena, $teams, $ppt);
                            $this->arena = $arena;
                            $sender->sendMessage(TextFormat::GREEN . "You have created Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . " Sucessfully!");
                            $sender->sendMessage(TextFormat::GREEN . "Setup -> /bw help");
                        } else {
                            $sender->sendMessage(TextFormat::RED."Can Only be a Maxium of 8 Teams");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/bw addarena <ArenaName> <Teams> <Players per Team>");
                    }
                }
                elseif(strtolower($args[0]) == "setlobby" && $sender->isOP()){
                    if(!empty($args[1])) {
                        $arena = $args[1];
                        if($this->arenaExists($arena)) {

                            $this->setLobby($arena, $sender);

                            $sender->sendMessage(TextFormat::GREEN . "You have set the Lobby for Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . " Sucessfully!");
                            $sender->sendMessage(TextFormat::GREEN . "Setup -> /bw help");

                        } else {
                            $sender->sendMessage(TextFormat::RED."Arena doesn't exist");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/bw setlobby <ArenaName>");
                    }
                }
                elseif(strtolower($args[0]) == "setnexus" && $sender->isOP()){
                    if(!empty($args[1]) && !empty($args[2])) {
                        $arena = $args[1];
                        $team = $args[2];
                        if($this->arenaExists($arena)) {
                            if (in_array($team, $this->getAllTeams())) {

                                $this->registerNexus = true;
                                $this->registerNexusWHO = $name;
                                $this->registerNexusArena = $arena;
                                $this->registerNexusTeam = $team;
                                $sender->sendMessage(TextFormat::GREEN . "Please tap the {$team} Nexus!");

                                $this->resetArena($arena);
                            } else {
                                $alleteams = implode(" ", $this->getAllTeams());

                                $sender->sendMessage(TextFormat::RED . "The Team " . TextFormat::GOLD . $team . TextFormat::RED . " doesn't exist!");
                                $sender->sendMessage(TextFormat::RED . "Teams: " . $alleteams);
                            }
                        } else {
                            $sender->sendMessage(TextFormat::RED."Arena doesn't exist!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/anni setnexus <ArenaName> <Team>");
                    }
                }
				elseif(strtolower($args[0]) == "join"){
					
					//$arena = substr($text[1], 0, -4);
					$arena= $args[1];
                    $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                    $status = $config->get("Status");
                    $maxplayers = $config->get("PlayersPerTeam") * $config->get("Teams");
                    $players = count($config->get("Players"));
$player = $sender;
                    if($status == "Lobby"){
                        if($players < $maxplayers) {
                            $this->TeleportToWaitingLobby($arena, $player);
                            $this->setTeamSelectionItems($player, $arena);
                            $this->addPlayerToArena($arena, $name);
                        } else {
                            $player->sendMessage($this->prefix . TextFormat::RED . "You can't join this match!");
                        }
                    } else {
                        $player->sendMessage($this->prefix.TextFormat::RED."You can't join this match!");
                    }
					
					
					
				}
                elseif(strtolower($args[0]) == "setspawn" && $sender->isOP()){
                    if(!empty($args[1]) && !empty($args[2])) {
                        $arena = $args[1];
                        $team = $args[2];
                        if($this->arenaExists($arena)) {
                            if (in_array($team, $this->getAllTeams())) {

                                $this->setSpawn($arena, $team, $sender);

                                $sender->sendMessage(TextFormat::GREEN . "You have set the Spawn for Team ".TextFormat::AQUA . $team . TextFormat::GREEN." for the Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . "!");
                                $sender->sendMessage(TextFormat::GREEN . "Setup -> /anni help");

                                $this->resetArena($arena);
                            } else {
                                $alleteams = implode(" ", $this->getAllTeams());

                                $sender->sendMessage(TextFormat::RED . "The Team " . TextFormat::GOLD . $team . TextFormat::RED . " doesn't exist!");
                                $sender->sendMessage(TextFormat::RED . "Teams: " . $alleteams);
                            }
                        } else {
                            $sender->sendMessage(TextFormat::RED."Arena doesn't exist!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED."/anni setspawn <ArenaName> <Team>");
                    }
                }
                elseif(strtolower($args[0]) == "test" && $sender->isOP()){
                  //
                } else {
                    $this->getServer()->dispatchCommand($sender, "anni help");
                }
            } else {
                $this->getServer()->dispatchCommand($sender, "anni help");
            }
        }
    }

}
############################################################################################################
############################################################################################################
############################################################################################################
###################################    ===[SCHEDULER]===     ###############################################
############################################################################################################
############################################################################################################
############################################################################################################
class BWRefreshSigns extends PluginTask {

    public $prefix = "";
    public $plugin;

    public function __construct(Annihilation $plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->prefix;
        parent::__construct($plugin);
    }

    public function onRun($tick) {
        $levels = $this->plugin->getServer()->getDefaultLevel();
        $tiles = $levels->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Sign) {
                $text = $t->getText();
                if ($text[0] == $this->prefix) {
                    $arena = substr($text[1], 0, -4);
                    $config = new Config($this->plugin->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                    $players = $this->plugin->getPlayers($arena);
                    $status = $config->get("Status");

                    $world = $this->plugin->getArenaWorlds($arena)[0];
                    $level = $this->plugin->getServer()->getLevelByName($world);

                    $arenasign = $text[1];

                    $teams = (int) $config->get("Teams");
                    $ppt = (int) $config->get("PlayersPerTeam");

                    $maxplayers = $teams * $ppt;
                    $ingame = TextFormat::GREEN."Join";

                    if ($status != "Lobby") {
                        $ingame = TextFormat::RED . "Ingame";
                    }
                    if (count($players) >= $maxplayers) {
                        $ingame = TextFormat::RED . "Voll";
                    }
                    if ($status == "Ende") {
                        $ingame = TextFormat::RED . "Restart";
                    }
                    $t->setText($this->prefix, $arenasign, $ingame, TextFormat::WHITE . (count($players)) . TextFormat::GRAY . " / ". TextFormat::RED . $maxplayers);
                }
            }
        }
    }
}
class BWGameSender extends PluginTask {

    public $prefix = "";
    public $plugin;

    public function __construct(Annihilation $plugin) {
        $this->plugin = $plugin;
        $this->prefix = $plugin->prefix;
        parent::__construct($plugin);
    }

    public function onRun($tick) {

        $files = scandir($this->plugin->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $arena = str_replace(".yml", "", $filename);
                $config = new Config($this->plugin->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
                $cfg = new Config($this->plugin->getDataFolder()."config.yml", Config::YAML);
                $players = $this->plugin->getPlayers($arena);
                $status = $config->get("Status");
                $teams = (int) $config->get("Teams");
                $ppt = (int) $config->get("PlayersPerTeam");
                $lobbytimer = (int) $config->get("LobbyTimer");
                $gametimer = (int) $config->get("GameTimer");
                $endtimer = (int) $config->get("EndTimer");
                $maxplayers = (int) $teams * $ppt;
                $world = $this->plugin->getFigthWorld($arena);
                $level = $this->plugin->getServer()->getLevelByName($world);

                $aliveTeams = $this->plugin->getAliveTeams($arena);

               // $minplayers = $ppt +1;
			   $minplayers = 2;

                /*
                if((Time() % 20) == 0){
                    $this->plugin->Debug(TextFormat::GREEN."== Players Array ==");
                    var_dump($players);
                    $this->plugin->Debug(TextFormat::GREEN."== Players Array ==");
                }
                */
                if($status == "Lobby"){

                    if(count($players) < $minplayers){

                        if((Time() % 10) == 0){
                            $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
                            $config->set("GameTimer", $cfg->get("GameTimer"));
                            $config->set("EndTimer", $cfg->get("EndTimer"));
                            $config->set("Status", "Lobby");
                            $config->save();
                        }


                        foreach($players as $pn){
                            $p = $this->plugin->getServer()->getPlayerExact($pn);
                            if($p != null) {
                                $p->sendPopup(TextFormat::RED . "Wait for ".TextFormat::GOLD.$minplayers.TextFormat::RED." Players");
                            } else {
                                $this->plugin->removePlayerFromArena($arena, $pn);
                            }
                        }

                        if((Time() % 20) == 0){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null) {
                                    $p->sendMessage(TextFormat::GOLD . $minplayers . TextFormat::RED ." Other Players are missing");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                    } else {

                        $lobbytimer--;
                        $config->set("LobbyTimer", $lobbytimer);
                        $config->save();

                        if($lobbytimer == 60 ||
                            $lobbytimer == 45 ||
                            $lobbytimer == 30 ||
                            $lobbytimer == 20 ||
                            $lobbytimer == 10
                        ){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->prefix."Round starting in ".$lobbytimer." Second(s)!");
                                }
                            }
                        }
                        if($lobbytimer >= 1 && $lobbytimer <= 5){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendPopup(TextFormat::YELLOW."Starting ".TextFormat::RED.$lobbytimer);
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        if($lobbytimer == 0){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    if($p->getNameTag() == $p->getName()) {
                                        $AT = $this->plugin->getAvailableTeam($arena);

                                        $p->setNameTag($this->plugin->getTeamColor($AT) . $pn);
                                    }
                                    $this->plugin->TeleportToTeamSpawn($p, $this->plugin->getTeam($p->getNameTag()), $arena);
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
/*
                            $tiles = $level->getTiles();
                            foreach ($tiles as $tile) {
                                if ($tile instanceof Sign) {
                                    $text = $tile->getText();
                                    if ($text[0] == "SHOP" || $text[1] == "SHOP" || $text[2] == "SHOP" || $text[3] == "SHOP") {
                                        //spawn Villager for Shop
                                        $this->plugin->createVillager($tile->getX(), $tile->getY(), $tile->getZ(), $tile->getLevel());
                                        $tile->getLevel()->setBlock(new Vector3($tile->getX(), $tile->getY(), $tile->getZ()), Block::get(Block::AIR));
                                    }
                                }
                            }
                            */

                            $config->set("Status", "Ingame");
                            $config->save();
                        }
                    }

                }
                elseif ($status == "Ingame"){
                    if(count($aliveTeams) <= 1){
                        if(count($aliveTeams) == 1){
                            $winnerteam = $aliveTeams[0];
                            $this->plugin->getServer()->broadcastMessage($this->prefix."Team ".TextFormat::GOLD.$winnerteam.TextFormat::WHITE." has won in Arena ".TextFormat::GOLD.$arena.TextFormat::WHITE);
                        }
                        $config->set("Status", "Ende");
                        $config->save();
                    } else {

                       
                       
               


                        foreach($players as $pn){
                            $p = $this->plugin->getServer()->getPlayerExact($pn);
                            if($p != null){
                                $this->plugin->sendIngameScoreboard($p, $arena);
                            } else {
                                $this->plugin->removePlayerFromArena($arena, $pn);
                            }
                        }

                        $gametimer--;
                        $config->set("GameTimer", $gametimer);
                        $config->save();

                        if($gametimer==900||$gametimer==600|| $gametimer==300|| $gametimer==240 || $gametimer==180){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->plugin->prefix.$gametimer/60 . " Minutes left");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        elseif($gametimer == 2||$gametimer == 3|| $gametimer==4||$gametimer==5||$gametimer==15||$gametimer==30||$gametimer==60){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->plugin->prefix.$gametimer . " Seconds Left");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        elseif($gametimer == 1){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->plugin->prefix."1 Second left");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        elseif($gametimer==0){
                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->plugin->prefix."Deathmatch Starting");

                                    $p->sendMessage($this->plugin->prefix."There is no Winner");
                                    $config->set($arena."Status", "Ende");
                                    $config->save();
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                    }
                }
                elseif($status == "Ende"){

                    if($endtimer >= 0){
                        $endtimer--;
                        $config->set("EndTimer", $endtimer);
                        $config->save();

                        if($endtimer == 15 ||
                            $endtimer == 10 ||
                            $endtimer == 5 ||
                            $endtimer == 4 ||
                            $endtimer == 3 ||
                            $endtimer == 2 ||
                            $endtimer == 1){

                            foreach($players as $pn){
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->sendMessage($this->plugin->prefix."Arena restarting in ".$endtimer." Seconds!");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        if($endtimer == 0){
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if($p != null){
                                    $p->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                    $p->setFood(20);
                                    $p->setHealth(20);
                                    $p->getInventory()->clearAll();
                                    $p->removeAllEffects();
                                    $p->setNameTag($p->getName());
                                }
                            }
                            $this->plugin->resetArena($arena, true);
                        }
                    }
                }
            }
        }
    }

}
