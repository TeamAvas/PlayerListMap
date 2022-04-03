<?php

declare(strict_types=1);

namespace melodylan\playerlistmap;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\convert\SkinAdapterSingleton;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class Loader extends PluginBase{
	private bool $show;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$this->setPlayerList((bool)$this->getConfig()->get("show-player-list", true));

		$this->getServer()->getPluginManager()->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $_): void{
			$this->sendPlayerListPacket();
		}, EventPriority::MONITOR, $this, false);
		$this->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $_): void{
			$this->sendPlayerListPacket();
		}, EventPriority::MONITOR, $this, false);
	}

	protected function onDisable() : void{
		$this->getConfig()->set('show-player-list', $this->show);
		$this->getConfig()->save();
	}

	private function setPlayerList(bool $value): void{
		$this->show = $value;
		$this->sendPlayerListPacket();
	}

	private function sendPlayerListPacket(): void{
		$packet = match($this->show){
			true => PlayerListPacket::add(array_map(static fn(Player $player): PlayerListEntry => PlayerListEntry::createAdditionEntry(
				uuid: $player->getUniqueId(),
				actorUniqueId: $player->getId(),
				username: $player->getDisplayName(),
				skinData: SkinAdapterSingleton::get()->toSkinData($player->getSkin()),
				xboxUserId: $player->getXuid()), $this->getServer()->getOnlinePlayers())),
			default => PlayerListPacket::remove(array_map(static fn(Player $player): PlayerListEntry => PlayerListEntry::createRemovalEntry(
				uuid: $player->getUniqueId()), $this->getServer()->getOnlinePlayers()))
		};
		$this->getServer()->broadcastPackets($this->getServer()->getOnlinePlayers(), [$packet]);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player || !$command->testPermission($sender)){
			return false;
		}
		switch(array_shift($args) ?? ''){
			case 'show':
			case 'enable':
				$this->setPlayerList(true);
				$sender->sendMessage(TextFormat::YELLOW . 'Enabled player list check');
				break;
			case 'hide':
			case 'disable':
				$this->setPlayerList(false);
				$sender->sendMessage(TextFormat::YELLOW . 'Disabled player list check');
				break;
			default:
				$sender->sendMessage(TextFormat::YELLOW . '/' . $command->getName() . ' [show/hide] - ' . $command->getDescription());
				break;
		}
		return true;
	}
}