<?php

use pocketmine\{
Player,
Server,

plugin\PluginBase,
utils\TextFormat as C,
event\Listener,

command\Command,
command\CommandSender,

scheduler\Task
};
use pocketmine\event\player\{
    PlayerDeathEvent,
	PlayerQuitEvent,
	PlayerCommandPreprocessEvent
};
use pocketmine\event\entity\{
	EntityDamageEvent,
	EntityDamageByEntityEvent,
	EntityLevelChangeEvent
};

class Main extends PluginBase implements Listener{
	
protected $inCombat = [];
protected $worlds = [];
const DEFAULT_TIME = 10;
const DEFAULT_MESSAGE = "&cO jogador &e[PLAYER] &cdeslogou em combate com o jogador &a[KILLER]";

public function onEnable(){
	$this->getServer()->getPluginManager()->registerEvents($this, $this);
	$this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task{

public function __construct(Main $pl){
	$this->pl = $pl;
}
public function onRun(int $t){
	$this->pl->processTask();
}
}, 20);
}

public function onDamage(EntityDamageEvent $e){
	if($e->isCancelled()) return false;
	if($e instanceof EntityDamageByEntityEvent){
		if(($en = $e->getEntity()) instanceof Player and ($d = $e->getDamager()) instanceof Player){
			if(in_array($en->getLevel()->getName(), $this->worlds)){
			$this->addInCombat($en, $d);
			$this->addInCombat($d, $en);
			}
		}
	}
/*	{
	if($e->getCause() == 4)
$e->setCancelled (true);
}*/
}
public function onPlayerDeath(PlayerDeathEvent $e){ $e->setDeathMessage ('');}

protected function addInCombat(Player $ent, Player $dm){
	$this->inCombat[$ent->getName()] = [
	"time" => self::DEFAULT_TIME,
	"damager" => $dm,
	"player" => $ent
	];
}
protected function removeCombat($p){
	if($p instanceof Player){
		$p = $p->getName();
	}
	if($this->isInCombat($p)){
		unset($this->inCombat[$p]);
	}
}
public function isInCombat($p){
	if($p instanceof Player){
		$p = $p->getName();
	}
	return isset($this->inCombat[$p]);
}
public function processTask(){
	foreach($this->getServer()->getLevels() as $l){
		$l->setTime(0);
		$l->stopTime(true);
	}
	foreach($this->inCombat as $n => $a){
		if($a["time"] > 0){
			$a["player"]->sendTip(C::colorize("&eCombate acaba em &a".$a["time"]." &asegundos"));
			$a["time"] = $a["time"] - 1;
			$this->inCombat[$n] = $a;
		}else{
			$a["player"]->sendMessage(C::colorize("&aVocê saiu de Combate."));
			$this->removeCombat($n);
		}
	}
}
public function getCombat($p){
	if($p instanceof Player){
		$p = $p->getName();
	}
	return $this->isInCombat($p) ? $this->inCombat[$p] : ["time" => 0, "damager" => null, "player" => null];
}
public function onQuit(PlayerQuitEvent $e){
	$p = $e->getPlayer();
	if($this->isInCombat($p)){
		$c = $this->getCombat($p);
		if($c["player"] !== null){
		    $p->kill();
			$m = str_replace(["[PLAYER]", "[KILLER]"], [$c["player"]->getName(), $c["damager"]->getName()], self::DEFAULT_MESSAGE);
			$this->getServer()->broadcastMessage(C::colorize($m));
		}
	}
}
public function onProcess(PlayerCommandPreprocessEvent $e){
	$p = $e->getPlayer();
	if($this->isInCombat($p)){
		$e->setCancelled();
		$p->sendMessage(C::colorize("&cVocê não pode usar comandos em Combate."));
	}
}
}
