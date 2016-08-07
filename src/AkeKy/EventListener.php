<?php

namespace AkeKy;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use AkeKy\task\ChatSession;

class EventListener implements Listener{
	/** @var ICore */
	private $plugin;

    private $canclebp;
    private $blocknoupdate;
    private $worldnopvp;

	public function __construct(ICore $plugin){
		$this->plugin = $plugin;
        $this->canclebp = $this->plugin->getConfig()->get("block-CancleBP");
        $this->blocknoupdate = $this->plugin->getConfig()->get("block-NoUpdate");
        $this->worldnopvp = $this->plugin->getConfig()->get("world-NoPvP");
        $this->inairticks = $this->plugin->getConfig()->get("TimeInAir") * 20;
	}

    public function onPlayerKick(PlayerKickEvent $event){
        if($event->getReason() === "disconnectionScreen.serverFull"){
            if($this->plugin->getPlayerVips(strtolower($event->getPlayer()->getName()))){
                $event->setCancelled(true);
            }
        }
    }

	public function onPlayerJoin(PlayerJoinEvent $event){
		$config = $this->plugin->getSimpleAuthDataProvider()->getPlayer($event->getPlayer());
		if($config !== null and $config["lastip"] === $event->getPlayer()->getUniqueId()->toString()){
			$this->plugin->authenticatePlayer($event->getPlayer());
		}else{
			$this->plugin->deauthenticatePlayer($event->getPlayer());
		}
        if(!$this->plugin->playerExists($event->getPlayer())){
            $this->plugin->addPlayer($event->getPlayer());
        }
	}

	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
        $player = $event->getPlayer();
        foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
            if($p !== $player and strtolower($player->getName()) === strtolower($p->getName())){
                if($this->plugin->isPlayerAuthenticated($p)){
                    $event->setCancelled(true);
                    $player->kick('§6already logged in', false);
                    return;
                }
            }
        }
    }

    public function onChat(PlayerChatEvent $event){
        if(!isset($this->sessions[$event->getPlayer()->getName()])){
            $this->sessions[$event->getPlayer()->getName()] = new ChatSession($this->plugin);
            $this->sessions[$event->getPlayer()->getName()]->bindToPlayer($event->getPlayer());
        }
        if(!$this->sessions[$event->getPlayer()->getName()]->sendMessage($event->getMessage())){
            $event->setCancelled(true);
        }
    }

	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$message = $event->getMessage();
			if($message{0} === "/"){ //Command
				$event->setCancelled(true);
				$command = substr($message, 1);
				$args = explode(" ", $command);
				if($args[0] === "register" or $args[0] === "login" or $args[0] === "help"){
					$this->plugin->getServer()->dispatchCommand($event->getPlayer(), $command);
				}else{
					$this->plugin->sendAuthenticateMessage($event->getPlayer());
				}
			}else{
				$event->setCancelled(true);
			}
		}
	}

	public function onPlayerMove(PlayerMoveEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
        if($event->isCancelled() or $event->getPlayer()->isCreative() or $event->getPlayer()->isSpectator() or $event->getPlayer()->getAllowFlight() or $event->getPlayer()->hasEffect(Effect::JUMP)){
        }else{
            if($event->getPlayer()->getInAirTicks() >= $this->inairticks){
                $event->getPlayer()->kick('§l§cYou have been kicked for fly hacks!§r §o§6Please disable mods to play.§r', false);
                return;
            }
        }
	}

	public function onPlayerDropItem(PlayerDropItemEvent $event){
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$event->setCancelled(true);
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
        if(isset($this->sessions[$event->getPlayer()->getName()])){
            unset($this->sessions[$event->getPlayer()->getName()]);
        }
		$this->plugin->closePlayer($event->getPlayer());
	}

	public function onBlockBreak(BlockBreakEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp)){
            if(!$event->getPlayer()->isOp()){
                $event->setCancelled(true);
            }
        }
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$event->setCancelled(true);
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp)){
            if(!$event->getPlayer()->isOp()){
                $event->setCancelled(true);
            }
        }
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$event->setCancelled(true);
		}
	}

    public function onBlockUpdate(BlockUpdateEvent $event){
        if(in_array($event->getBlock()->getId(), $this->blocknoupdate)){
            $event->setCancelled(true);
        }
    }

    public function onPvP(EntityDamageEvent $event){
    	if($event instanceof EntityDamageByEntityEvent){
    		if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
    			if(in_array($event->getEntity()->getLevel()->getFolderName(), $this->worldnopvp)){
    				$event->setCancelled(true);
    			}
    		}
    	}
    }

    public function onPlayerDeath(PlayerDeathEvent $event){
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player){
                $event->getPlayer()->sendMessage('§b- §aYou kill by §c'.$killer->getName().'§a.');
                $killer->sendMessage('§b- §eYou kill §6'.$event->getEntity()->getName().'§e.');
                $killer->setHealth(20);
                $this->plugin->updatePlayer($event->getEntity(), "deaths");
                $this->plugin->updatePlayer($killer, "kills");
            }
        }
    }
}
