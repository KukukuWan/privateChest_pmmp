<?php

namespace KukukuWan;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\block\Chest;

class PrivateChest extends PluginBase {

    private array $lockedChests = [];
    private array $sharedChests = [];

    public function onEnable(): void {
        $this->getLogger()->info("PrivateChest_pmmp Enabled");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) return false;
    
        if (count($args) < 1) {
            $sender->sendMessage("§e使用方法: /pcc <lock|unlock|share|help>");
            return true;
        }
    
        $sub = strtolower(array_shift($args));
        $block = $sender->getTargetBlock(5);
        if (!$block instanceof Chest) {
            $sender->sendMessage("§c目の前にチェストを見つけてください。");
            return true;
        }
    
        $posKeys = $this->getChestGroup($block->getPosition());
    
        switch ($sub) {
            case "lock":
                foreach ($posKeys as $posKey) {
                    $this->lockedChests[$posKey] = $sender->getName();
                    $this->sharedChests[$posKey] = [];
                    $this->placeSign($posKey, $sender->getName(), []);
                }
                $sender->sendMessage("§aチェストをロックしました。");
                break;
    
            case "unlock":
                foreach ($posKeys as $posKey) {
                    unset($this->lockedChests[$posKey], $this->sharedChests[$posKey]);
                    $this->removeSign($posKey);
                }
                $sender->sendMessage("§eチェストのロックを解除しました。");
                break;
    
            case "share":
                if (count($args) < 1) {
                    $sender->sendMessage("§c使用方法: /pcc share <プレイヤー名>");
                    break;
                }
                $target = $args[0];
                foreach ($posKeys as $posKey) {
                    if (($this->lockedChests[$posKey] ?? "") !== $sender->getName()) {
                        $sender->sendMessage("§cあなたが所有していないチェストがあります。");
                        return true;
                    }
                    $this->sharedChests[$posKey][] = $target;
                    $this->placeSign($posKey, $sender->getName(), $this->sharedChests[$posKey]);
                }
                $sender->sendMessage("§b$target §aとチェストを共有しました。");
                break;
    
            case "help":
                $sender->sendMessage("§6PrivateChest コマンド一覧:");
                $sender->sendMessage("§e/pcc lock §7- チェストをロック");
                $sender->sendMessage("§e/pcc unlock §7- ロック解除");
                $sender->sendMessage("§e/pcc share <player> §7- プレイヤーと共有");
                break;
    
            default:
                $sender->sendMessage("§c不明なサブコマンドです。 /pcc help を使ってください。");
                break;
        }
    
        return true;
    }
}
