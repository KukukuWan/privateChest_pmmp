<?php

namespace YourName;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\block\Chest;
use pocketmine\block\WallSign;
use pocketmine\block\SignPost;
use pocketmine\utils\Config;

class PrivateChest extends PluginBase implements Listener {

    private Config $data;
    private array $lockedChests = []; // posKey => owner
    private array $sharedChests = []; // posKey => [player1, player2, ...]

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        $this->lockedChests = $this->data->get("locked", []);
        $this->sharedChests = $this->data->get("shared", []);
    }

    public function onDisable(): void {
        $this->data->set("locked", $this->lockedChests);
        $this->data->set("shared", $this->sharedChests);
        $this->data->save();
    }

    public function onInventoryOpen(InventoryOpenEvent $event): void {
        $holder = $event->getInventory()->getHolder();
        if ($holder instanceof Position) {
            foreach ($this->getChestGroup($holder) as $posKey) {
                if (isset($this->lockedChests[$posKey])) {
                    $owner = $this->lockedChests[$posKey];
                    $shared = $this->sharedChests[$posKey] ?? [];
                    $player = $event->getPlayer();
                    if ($player->getName() !== $owner && !in_array($player->getName(), $shared)) {
                        $event->cancel();
                        $player->sendMessage("§cこのチェストはロックされています。所有者: §e$owner");
                        return;
                    }
                }
            }
        }
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

    public function onBlockBreak(BlockBreakEvent $event): void {
        $posKey = $this->posToString($event->getBlock()->getPosition());
        if (isset($this->lockedChests[$posKey])) {
            unset($this->lockedChests[$posKey], $this->sharedChests[$posKey]);
            $this->removeSign($posKey);
        }
    }

    private function posToString(Position $pos): string {
        return $pos->getWorld()->getFolderName() . ":" . $pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ();
    }

    private function getChestGroup(Position $pos): array {
        $keys = [$this->posToString($pos)];
        $block = $pos->getWorld()->getBlock($pos);
        if ($block instanceof Chest) {
            $facing = $block->getFacing();
            $adjacent = $pos->getSide($facing->rotateCounterClockwise());
            $adjBlock = $adjacent->getWorld()->getBlock($adjacent);
            if ($adjBlock instanceof Chest) {
                $keys[] = $this->posToString($adjacent);
            }
        }
        return $keys;
    }

    private function placeSign(string $posKey, string $owner, array $shared): void {
        [$worldName, $x, $y, $z] = explode(":", $posKey);
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
        if (!$world) return;

        $signPos = new Position((int)$x, (int)$y + 1, (int)$z, $world);
        $world->setBlock($signPos, VanillaBlocks::SIGN_POST());
        $tile = $world->getTile($signPos);
        if ($tile instanceof \pocketmine\tile\Sign) {
            $lines = [
                "§b🔒 PrivateChest",
                "§aOwner: $owner",
                count($shared) > 0 ? "§eShared: " . implode(", ", $shared) : "§7No shared"
            ];
            $tile->setText(...$lines);
        }
    }

    private function removeSign(string $posKey): void {
        [$worldName, $x, $y, $z] = explode(":", $posKey);
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
        if (!$world) return;

        $signPos = new Position((int)$x, (int)$y + 1, (int)$z, $world);
        $block = $world->getBlock($signPos);
        if ($block instanceof SignPost || $block instanceof WallSign) {
            $world->setBlock($signPos, VanillaBlocks::AIR());
        }
    }
}
