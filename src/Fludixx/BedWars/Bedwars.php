<?php

declare(strict_types=1);

namespace Fludixx\BedWars;

use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as f;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\sound\GhastShootSound;

class Bedwars extends PluginBase implements Listener {

	const IMPLEMENTED = array("8x1");
	const NAME = f::DARK_GRAY."[".f::RED."Bed".f::WHITE."Wars".f::DARK_GRAY."]";
	const PREFIX = self::NAME."".f::DARK_GRAY." | ".f::WHITE;
	const VERSION = 1;
	const API = 3;
	public $sagiri = null;
	public $teamcolors = null;
	public $setup = null;
	public $prefix = self::PREFIX;
	public $fjoin = false;
	public $kabstand = 6;
	public $dimension = null;
	public $arena = null;
	public $withSagiri = false;

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(self::PREFIX."Lade Bedwars...");
		$sagiri = $this->getServer()->getPluginManager()->getPlugin("Sagiri-API");
		//$running = $sagiri->isEnabled();
		$this->getLogger()->info("Sagiri-API wird geladen...");
		if($sagiri) {
			$sagiri->getLogger()->info($sagiri::PREFIX."Das Plugin Bedwars fragt nach SagiriAPI, zugriff wird erteilt!");
			$this->withSagiri = true;
		} else {
			$this->getLogger()->error(self::PREFIX."Konnte keinen Kontakt mit Sagiri aufnehmen!");
			$this->withSagiri = false;
			$this->setEnabled(false);
		}
		$this->getLogger()->info("Regestrierte Arenas:");
		foreach(glob('/cloud/bw/*.yml') as $file) {
			$c = new Config("$file");
			$c->set("players", 0);
			$c->set("countdown", 60);
			$c->set("busy", false);
			$c->save();
			$this->getLogger()->info(" - ".$file);
		}
		@mkdir("/cloud/bw");
		$this->sagiri = $sagiri;

		$this->teamcolors = array(
			1 => $this->teamIntToColorInt(1),
			2 => $this->teamIntToColorInt(2),
			3 => $this->teamIntToColorInt(3),
			4 => $this->teamIntToColorInt(4),
			5 => $this->teamIntToColorInt(5),
			6 => $this->teamIntToColorInt(6),
			7 => $this->teamIntToColorInt(7),
			8 => $this->teamIntToColorInt(8),
		);
	}

	public function teamIntToColorInt(int $int) : int {
		if($int == 1) {return 14;}
		if($int == 2) {return 11;}
		if($int == 3) {return 5;}
		if($int == 4) {return 4;}
		if($int == 5) {return 6;}
		if($int == 6) {return 1;}
		if($int == 7) {return 10;}
		if($int == 8) {return 0;}
	}
	public function ColorIntToTeamInt(int $int) : int {
		if($int == 14) {return 1;}
		if($int == 11) {return 2;}
		if($int == 5) {return 3;}
		if($int == 4) {return 4;}
		if($int == 6) {return 5;}
		if($int == 1) {return 6;}
		if($int == 10) {return 7;}
		if($int == 0) {return 8;}
	}
	public function ColorInt2Color(int $int) : string {
		if($int == 14) {return f::RED."Rot".f::WHITE;}
		if($int == 11) {return f::BLUE."Blau".f::WHITE;}
		if($int == 5) {return f::GREEN."Grün".f::WHITE;}
		if($int == 4) {return f::YELLOW."Gelb".f::WHITE;}
		if($int == 6) {return f::LIGHT_PURPLE."Pink".f::WHITE;}
		if($int == 1) {return f::GOLD."Orange".f::WHITE;}
		if($int == 10) {return f::DARK_PURPLE."Violett".f::WHITE;}
		if($int == 0) {return f::WHITE."Weiß";}
	}
	public function count(Player $player, int $id = Item::BRICK): int{
		$all = 0;
		$inv = $player->getInventory();
		$content = $inv->getContents();
		foreach ($content as $item) {
			if ($item->getId() == $id) {
				$c = $item->count;
				$all = $all + $c;
			}
		}
		return $all;
	}
	public function rm(Player $player, int $id = Item::BRICK){
		$player->getInventory()->remove(Item::get($id, 0, 1));
	}
	public function add(Player $player, int $i, int $id = Item::BRICK){
		$name = $player->getName();
		$inv = $player->getInventory();
		$c = 0;
		while($c < $i){
			$inv->addItem(
				Item::get(
					$id,
					0,
					1));
			$c++;
		}
	}

	public function setPrice(Player $player, int $price, int $id) : bool {
		$woola = $this->count($player, $id);
		$name = $player->getName();
		if($woola < $price) {
			$need = (int)$price - (int)$woola;
			return false;
		} else {
			$woolprice = $price;
			$wooltot = $woola-$woolprice;
			$this->rm($player, $id);
			$this->add($player, $wooltot, $id);
			return true;}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
	{
		if ($command->getName() == "bw") {
			if ($this->withSagiri == true) {
				if ($args['0'] == "start") {
					if ($sender->hasPermission("bw.start")) {
						if ($sender instanceof Player) {
							$levelname = $sender->getLevel()->getFolderName();
							$c = new Config("/cloud/bw/$levelname.yml");
							$c->set("countdown", 10);
							$c->save();
							$sender->sendMessage(self::PREFIX . "Countdownwert wurde auf 10 gestellt.");
						} else {
							$sender->sendMessage(self::PREFIX . "Uhh.. Du bist kein Spieler?");
						}
					} else {
						$sender->sendMessage(self::PREFIX . "Uhh.. Komm wieder wenn du die Rechte hast.");
					}
				} elseif (!empty($args['0']) && empty($args['1'])) {
				} else {
					$sender->sendMessage(self::PREFIX . "Uhh.. /bw [ARENA] [8x1...]");
				}
				if (!$sender->isOp()) {
					$sender->sendMessage(self::PREFIX . "Uhh.. Komm wieder wenn du OP bist.");
					return false;
				} else {
					if (empty($args['0']) || empty($args['1'])) {
						$sender->sendMessage(self::PREFIX . "Uhh.. /bw [ARENA] [8x1...]");
						return false;
					} else {
						$sender->sendMessage(self::PREFIX . "OK. " . $args['1'] . " wurde als Deminsion ausgewählt.");
						$this->getServer()->loadLevel((string)$args['0']);
						$arena = $this->getServer()->getLevelByName((string)$args['0']);
						if (!$arena) {
							$sender->sendMessage(self::PREFIX . "Uhh.. Keine Arena namens " . $args['0'] . " gefunden.");
							return false;
						} else {
							$rechnung = $args[1];
							$allespieler = eval("return $rechnung;");
							if ($allespieler > 64) {
								$sender->sendMessage(self::PREFIX . "Es dürfen nicht mehr als 64 Spieler Teilnehmen!");
								return false;
							}
							$this->dimension = $args['1'];
							$pos = new Position($arena->getSafeSpawn()->getX(), $arena->getSafeSpawn()->getY(),
								$arena->getSafeSpawn()->getZ(), $arena);
							if ($sender instanceof Player) {
								$sender->teleport($pos);
								$sender->sendMessage(self::PREFIX . "Plaziere einen Block auf den Spawn vom 1 Spieler!");
								$inv = $sender->getInventory();
								$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(1), 1);
								$inv->setItem(0, $wolle);
								$this->setup = "8x8-1";
								return true;
							} else {
								$sender->sendMessage(self::PREFIX . "Uhh.. Du bist kein Spieler?");
								return false;
							}
						}
					}
				}
			} else {
				$sender->sendMessage(self::PREFIX . "Sorry! /bw is disabled due the missing of Sagiri-API! :(");
				return false;
			}
			}
		if ($this->withSagiri == true) {
			if ($command->getName() == "bwsign") {
				if (empty($args['0'])) {
					$sender->sendMessage(self::PREFIX . "Uhh.. /bwsign [ARENA]");
					return false;
				} else {
					$arena = $args['0'];
					if (is_file("/cloud/bw/$arena.yml")) {
						$c = new Config("/cloud/bw/$arena.yml");
						$sender->sendMessage(self::PREFIX . "OK. $arena wurde gefunden. Bitte klicke auf ein Schild.");
						$this->setup = "sign-1";
						$this->arena = $arena;
						return true;
					} else {
						$sender->sendMessage(self::PREFIX . "Uhh.. So eine Arena wurde nie regestriert.");
						return false;
					}
				}
			}
		} else {
			$sender->sendMessage(self::PREFIX."Sorry! /bw is disabled due the missing of Sagiri-API! :(");
			return false;
		}
	}

	public function onPlace(BlockPlaceEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$c = new Config("/cloud/users/$name.yml", Config::YAML);
		$pos = $c->get("pos");
		if (($this->setup == null || $this->setup == "sign-1") && $player->isOp() && $pos != false) {
			if($event->getBlock()->getId() == Item::WEB) {
				$this->getScheduler()->scheduleDelayedTask(new CobwebTask($this, $event->getBlock()), 100);
			}
			return true;
		} else {
		if
			($this->setup == "8x8-1"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("dimension", $this->dimension);
			$c->set("p1", $posarray);
			$c->save();
			$this->dimension = null;
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 2.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(2), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-2";
		} elseif
			($this->setup == "8x8-2"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p2", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 3.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(3), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-3";
		} elseif
			($this->setup == "8x8-3"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p3", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 4.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(4), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-4";
		} elseif
			($this->setup == "8x8-4"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p4", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 5.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(5), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-5";
		} elseif
			($this->setup == "8x8-5"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p5", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 6.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(6), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-6";
		} elseif
			($this->setup == "8x8-6"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p6", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 7.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(7), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-7";
		} elseif
			($this->setup == "8x8-7"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p7", $posarray);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt bitte den 8.");
			$inv = $player->getInventory();
			$wolle = Item::get(Item::WOOL, $this->teamIntToColorInt(8), 1);
			$inv->setItem(0, $wolle);
			$this->setup = "8x8-8";
		} elseif
			($this->setup == "8x8-8"){
			$event->setCancelled(true);
			$posarray = [$event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ()];
			$cname = $player->getLevel()->getFolderName();
			$c = new Config("/cloud/bw/$cname.yml", Config::YAML);
			$c->set("p8", $posarray);
			$c->save();
			$c->set("players", 0);
			$c->save();
			$player->sendMessage(self::PREFIX . "OK. Jetzt sind wir Fertig!");
			$inv = $player->getInventory();
			$wolle = Item::get(0, 0, 0);
			$inv->setItem(0, $wolle);
			$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
			$this->setup = null;
		}
		}
	}

	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		$itemname = $event->getItem()->getCustomName();
		if ($itemname == f::GREEN . "Zur Lobby") {
			$pos = $this->getServer()->getDefaultLevel()->getSafeSpawn()->asPosition();
			$player->teleport($pos);
			$player->getInventory()->clearAll();
			return true;
		}
		if ($itemname == f::GREEN . "Zur Lobby") {
			$pos = $this->getServer()->getDefaultLevel()->getSafeSpawn()->asPosition();
			$player->teleport($pos);
			$player->getInventory()->clearAll();
			return true;
		} elseif ($itemname == f::RED . "Runde Starten") {
			$sender = $player;
			if ($sender->hasPermission("bw.start")) {
				if ($sender instanceof Player) {
					$levelname = $sender->getLevel()->getFolderName();
					$c = new Config("/cloud/bw/$levelname.yml");
					$c->set("countdown", 10);
					$c->save();
					$sender->sendMessage($this->prefix . "Countdownwert wurde auf 10 gestellt.");
				} else {
					$sender->sendMessage($this->prefix . "Uhh.. Du bist kein Spieler?");
				}
			} else {
				$sender->sendMessage($this->prefix . "Uhh.. Komm wieder wenn du die Rechte hast.");
			}
		}
		if ($this->setup == "sign-1" && $player->isOp()) {
			if ($tile instanceof \pocketmine\tile\Sign) {
				$c = new Config("/cloud/bw/$this->arena.yml", Config::YAML);
				$dimension = $c->get("dimension");
				$c->set("players", 0);
				$c->save();
				$playeramout = 0;
				$playeramout = eval("return $dimension;");
				$dimension = str_replace("*", "x", $dimension);
				$tile->setText(
					self::NAME,
					f::DARK_GRAY . "[" . f::GREEN . "$dimension" . f::DARK_GRAY . "]",
					f::YELLOW . "0 " . f::DARK_GRAY . "/ " . f::GREEN . "$playeramout"
					. f::DARK_GRAY . "]",
					"$this->arena"
				);
				$player->sendMessage($this->prefix . "OK. Schild wurde erstellt.");
				$this->setup = null;
				$this->getScheduler()->scheduleRepeatingTask(new BwSignUpdater($this, $tile), 20);
				return true;
			} else {
				$player->sendMessage($this->prefix . "Uhh.. Das ist kein Schild.");
				$this->setup = null;
				return false;
			}
		} else {
			if ($tile instanceof \pocketmine\tile\Sign) {
				$text = $tile->getText();
				if ($text['0'] == self::NAME) {
					$player->sendMessage($this->prefix . "Du wirst Teleportiert...");
					$cp = new Config("/cloud/users/$name.yml", Config::YAML);
					$cp->set("pos", false);
					$cp->save();
					$this->getServer()->loadLevel((string)$text['3']);
					$cplayercount = (int)$text[2][3];
					$c = new Config("/cloud/bw/" . $text[3] . ".yml", Config::YAML);
					$dimension = $c->get("dimension");
					$playeramout = eval("return $dimension;");
					if ($cplayercount == $playeramout) {
						$tile->setLine(0, f::RED . "BedWars");
						$player->sendMessage($this->prefix . "Uhh.. Die Arena ist voll oder schon gestartet.");
						return false;
					} else {
						$cplayercount = $cplayercount + 1;
						$tile->setLine(2, f::YELLOW . "$cplayercount " . f::DARK_GRAY . "/ " . f::GREEN . "$playeramout");
						$arena = $this->getServer()->getLevelByName((string)$text['3']);
						$arena->setAutoSave(false);
						$pos = $arena->getSafeSpawn()->asPosition();
						$player->setGamemode(0);
						$player->teleport($pos);
						$this->getWaitingItems($player);
						$players = $this->getServer()->getOnlinePlayers();
						$counter = 0;
						$playerarray = array();
						foreach ($players as $person) {
							$level = $person->getLevel()->getFolderName();
							if ($level == $arena->getFolderName()) {
								$counter++;
								$playerarray[] = $person;
							}
						}
						$minplayers = (int)substr($dimension, -1)+1;
						$this->sagiri->sendLevelBrodcast($this->prefix."Es werden min. $minplayers Spieler benötigt!", $arena);
						if($counter == $minplayers) {
							$this->getLogger()->info("Es sind $minplayers Spieler in der Arena ".$arena->getFolderName());
							foreach($playerarray as $person) {
								$person->sendMessage($this->prefix."Das Spiel beginnt in 60 Sekunden!");
							}
							$c = new Config("/cloud/bw/".$arena->getFolderName().".yml", Config::YAML);
							$c->set("countdown", 60);
							$c->save();
							$this->getScheduler()->scheduleRepeatingTask(new BwCountdown($this, $arena, $minplayers), 20);
							$this->getLogger()->info("Countdown eingeleitet!");
							return false;
						}
						$this->sagiri->sendLevelBrodcast($this->prefix.$player->getName()." joined the Game! ".f::DARK_GRAY."["
							.f::YELLOW."$counter".f::DARK_GRAY."]", $arena, false);
					}
				}
			}
		}
	}

	public function printStats(Player $player) {
		$name = $player->getName();
		$c = new Config("/cloud/users/$name.yml", 2);
		if(!$c->get("bwplays")) {
			$c->set("bwplays", 0);
			$c->set("bwwins", 0);
			$c->set("bwkills", 0);
			$c->set("bwtode", 0);
			$c->save();
		}
		$kills = (int)$c->get("bwkills");
		$tode = (int)$c->get("bwtode");
		$kd = (float)$kills+1/$tode+1;
		$wins = (int)$c->get("bwwins");
		$spiele = (int)$c->get("bwplays");
		$beds = (int)$c->get("beds");
		$siegwarscheinlichkeit = (float)(($spiele / 100) * $wins);
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$pname = $player->getName();
		$c = new Config("/cloud/users/$pname.yml", 2);
		$c->set("pos", false);
		$c->save();
		$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn()->asPosition());
		if($this->fjoin == false) {
			$levelname = $this->getServer()->getDefaultLevel()->getFolderName();
			$this->getLogger()->info($this->prefix."Initialisiere SignUpdater auf $levelname...");
			$tiles = $this->getServer()->getDefaultLevel()->getTiles();
			foreach($tiles as $tile) {
				if($tile instanceof \pocketmine\tile\Sign) {
					$text = $tile->getText();
					if($text[0] == self::NAME || $text[0] == f::RED."BedWars") {
						$this->getScheduler()->scheduleRepeatingTask(new BwSignUpdater($this, $tile), 20);
						$this->getLogger()->info("SignUpdater Task wurde gestartet!");
					}
				}
			}
			$this->fjoin = true;
		}
	}

	public function getWaitingItems(Player $player) : bool {
		$inv = $player->getInventory();
		$startround = Item::get(Item::REDSTONE_TORCH, 0, 1);
		$startround->setCustomName(f::RED."Runde Starten");
		$lobby = Item::get(Item::SLIME_BALL, 0, 1);
		$lobby->setCustomName(f::GREEN."Zur Lobby");
		$inv->setItem(8, $startround);
		$inv->setItem(0, $lobby);
		return true;
	}
	public function getEq(Player $player) {
		$player->getInventory()->clearAll();
	}

	public function onBreak(BlockBreakEvent $event) {
		$block = $event->getBlock();
		$player = $event->getPlayer();
		$c = new Config("/cloud/users/".$player->getName().".yml", Config::YAML);
		$pos = (int)$c->get("pos");
		if($block->getId() == Item::BED_BLOCK && $pos != false) {
			$air = Item::get(0, 0, 0);
			$drops = array($air);
			$event->setDrops($drops);
			$tile = $player->getLevel()->getTile(new Vector3($block->getX(),$block->getY(),$block->getZ()));
			$bedblock = $player->getLevel()->getBlock(new Vector3($block->getX(), $block->getY() - 1, $block->getZ()),
				false);
			$this->getLogger()->info($bedblock->getDamage());
			if($pos == $this->ColorIntToTeamInt($bedblock->getDamage())) {$player->sendMessage
			($this->prefix."Du kannst nicht dein eigenes Bett abbauen!");
				$event->setCancelled(true);return 0;}
			$player->getLevel()->addSound(new GhastShootSound(new Vector3($block->getX(), $block->getY(),
				$block->getZ())));
			$arenaname = $player->getLevel()->getFolderName();
			$players = $this->getServer()->getOnlinePlayers();
			foreach($players as $person) {
				if($person->getLevel()->getFolderName() == $arenaname) {
					$cp = new Config("/cloud/users/".$person->getName().".yml", Config::YAML);
					$cpos = (int)$cp->get("pos");
					if($cpos == (int)$this->ColorIntToTeamInt($bedblock->getDamage())) {
						$cp->set("bett", false);
						$cp->save();
						$person->sendMessage($this->prefix."Dein Bett wurde abgebaut!");
						$person->addSubTitle(f::RED."Dein Bett wurde Zerstört!");
						$player->getLevel()->addSound(new GhastShootSound($player->getPosition()->asVector3()));
						/*
						$player->getLevel()->addParticle(new FloatingTextParticle(new Position($block->getX(),
							$block->getY(),$block->getZ(), $block->getLevel()), f::RED."Bett Abgebaut von: ".f::WHITE
							.$player->getName()));
						*/
					}
					$person->sendMessage($this->prefix."Das Bett von Team ".$this->ColorInt2Color
						($this->teamIntToColorInt($this->ColorIntToTeamInt((int)
						$bedblock->getDamage())))." wurde zerstört!");
					$c->set("beds",(int)($c->get("beds"))+1);$c->save();
				}
			}
		}
		elseif($block->getId() == Item::SANDSTONE && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::CLAY_BLOCK && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::WEB && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::STAINED_GLASS && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::GLASS && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::SLIME_BLOCK && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::TNT && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::END_STONE && $pos != false) {
			return true;
		}
		elseif($block->getId() == Item::BED_BLOCK && $pos != false) {
			$air = Item::get(Item::GOLDEN_APPLE, 0, 1);
			$drops = array($air);
			$event->setDrops($drops);
			return true;
		}
		elseif($pos == false && $player->isOp()) {
			$event->setCancelled(false);
		} else {
			$event->setCancelled(true);
		}
	}

	public function onHunger(PlayerExhaustEvent $event) {
		$event->getPlayer()->setFood(20);
	}

	public function onDmg(EntityDamageEvent $event) {
		$enity = $event->getEntity();
		if($enity instanceof Player) {
			$name = $enity->getName();
			$c = new Config("/cloud/users/$name.yml", 2);
			$pos = $c->get("pos");
			if(!$pos) {
				$event->setCancelled(true);
			}
		}
	}

	public function pvp(EntityDamageByEntityEvent $event) {
		$opfer = $event->getEntity();
		$damger = $event->getDamager();
		if($damger instanceof Player && $opfer instanceof Player) {
			$damage = $event->getFinalDamage();
			$herzen = $opfer->getHealth();
			$oc = new Config("/cloud/users/".$opfer->getName().".yml", 2);
			$dc = new Config("/cloud/users/".$damger->getName().".yml", 2);
			if($oc->get("pos") == $dc->get("pos")) {
				$event->setCancelled(true);
				return true;
			}
			if($herzen - $damage <= 0) {
				$event->setCancelled(true);
				$opfer->setHealth(20);
				$opfer->getInventory()->clearAll();
				$opfer->teleport($opfer->getSpawn());
				$this->sagiri->sendLevelBrodcast(self::PREFIX.f::YELLOW.$damger->getName().f::WHITE." hat ".f::YELLOW
					.$opfer->getName().f::WHITE." getötet!",
						$damger->getLevel(), false);
				$oname = $opfer->getName();
				$c = new Config("/cloud/users/$oname.yml", 2);
				$wool = $c->get("bett");
				if($wool == false) {
					$c->set("kills",(int)($c->get("kills"))+1);$c->save();
					$oname = $damger->getName();
					$c = new Config("/cloud/users/$oname.yml", 2);
					$c->set("tode",(int)($c->get("tode"))+1);$c->save();
					$c->set("pos", false);
					$c->save();
				}
			}
		}
	}
	public function onTnteract(PlayerInteractEvent $event) {
		$item = $event->getItem();
		if($item->getId() == Item::BLAZE_ROD) {
			$proc = $this->setPrice($event->getPlayer(), 1, Item::BLAZE_ROD);
			if($proc) {
				$this->kabstand = 6;
				$level = $event->getPlayer()->getLevel();
				$player = $event->getPlayer();
				$block = Block::get(Block::SLIME_BLOCK);
				$rand = Block::get(Block::STAINED_GLASS, 14);
				$x = $player->getX();
				$y = $player->getY();
				$z = $player->getZ();
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level = $player->getLevel();
				$level->setBlock($pos, $block);
				$x = $player->getX() + 1;
				$y = $player->getY();
				$z = $player->getZ();
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $block);
				$x = $player->getX() - 1;
				$y = $player->getY();
				$z = $player->getZ();
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $block);
				$x = $player->getX();
				$y = $player->getY();
				$z = $player->getZ() - 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $block);
				$x = $player->getX();
				$y = $player->getY();
				$z = $player->getZ() + 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $block);
				$x = $player->getX() + 1;
				$y = $player->getY();
				$z = $player->getZ() + 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $rand);
				$x = $player->getX() - 1;
				$y = $player->getY();
				$z = $player->getZ() - 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $rand);
				$x = $player->getX() + 1;
				$y = $player->getY();
				$z = $player->getZ() - 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $rand);
				$x = $player->getX() - 1;
				$y = $player->getY();
				$z = $player->getZ() + 1;
				$y = $y - (int)$this->kabstand;
				$pos = new Vector3($x, $y, $z);
				$level->setBlock($pos, $rand);
			}
		}
	}

	public function onCraft(CraftItemEvent $event) {
		$event->setCancelled(true);
	}
	public function onTnt(EntityExplodeEvent $event) {
		$event->setBlockList(array(Block::get(Block::SANDSTONE), Block::get(Block::WEB), Block::get(Block::END_STONE)));

	}

}