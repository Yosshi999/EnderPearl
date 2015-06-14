<?php
namespace EnderPearl;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\entity\EntityDespawnEvent;

use pocketmine\entity\Snowball;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

	/** @var Array */
	private $order;
	/** @var Config */ 
	private $config;
	
	public function __construct(){
		$this->order = array();
	}
	
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0755, true);
		$this->config = new Config( $this->getDataFolder()."config.properties",Config::PROPERTIES,array("damage"=>5) );
		$this->config->save();
		
		$this->getLogger()->info("EnderPerl loaded!");
	}

	public function onDisable(){
		$this->config->save();
		$this->getLogger()->info("EnderPearl unloaded!");
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label,array $params){
		if($cmd->getName() === "enderpearl"){
			$sub = array_shift($params);
			switch($sub){
				case "damage":
					$amount = array_shift($params);
					if( !is_numeric($amount) or $amount < 0 ){$sender->sendMessage("invalid value");return true;}
					
					$amount = floor($amount);
					$this->config->set("damage",$amount);
					$sender->sendMessage("teleport damage has changed into ".$amount);
					return true;
					
				default:
					$sender->sendMessage("Usage: /enderpearl damage <value> :Change the amount of teleport damage");
					return true;
			}
		}
	}


/* ============================ system ==============================================*/	
	public function onProjectileLaunch(ProjectileLaunchEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Snowball){
			$shooter = $entity->shootingEntity;
			$ballid = $entity->getId();
			if($shooter instanceof Player){
				$id = $shooter->getId();
				if( array_key_exists($id,$this->order) ){array_push($this->order[$id],$ballid);}
					else{$this->order += array($id => array($ballid));}
			}
		}
	}
	
	public function onPlayerDeath(PlayerDeathEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$id = $entity->getId();
//			$this->getLogger()->info($entity->getName()."is dead");
			if(array_key_exists($id,$this->order)){$this->order[$id]=array();}
		}
	}
	
	public function onEntityClose(EntityDespawnEvent $event){
		if($event->getType() === 81){	//81=Snowball
			$entity = $event->getEntity();
			$ballid = $entity->getId();
			$shooter = $entity->shootingEntity;
			$posTo = $entity->getPosition();
			
			if($posTo instanceof Position){
				if($shooter instanceof Player && $shooter->hasPermission("enderpearl.teleport")){
					$id = $shooter->getId();
					$key = array_search($ballid,$this->order[$id]);
					if(array_key_exists($id,$this->order) && $key!==false ){
						unset($this->order[$id][$key]);
						$posFrom = $shooter->getPosition();
						
						$shooter->teleport($posTo);
						if(!$shooter->isCreative()){
							$ev = new EntityDamageEvent( $shooter, EntityDamageEvent::CAUSE_MAGIC, $this->config->get("damage") );
							$shooter->attack($ev->getFinalDamage(), $ev);
						}
					}
				}
			}
		}
		if( $event->isHuman() ){		// log out
			$entity = $event->getEntity();
			$id = $entity->getId();
			if(array_key_exists($id,$this->order)){
//				$this->getLogger()->info($entity->getName());
				unset($this->order[$id]);
			}
		}
	}

}

