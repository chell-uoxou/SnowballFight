<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2017/05/24
 * Time: 午後 4:03
 */
namespace SnowballFight;


use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerChatEvent;

use pocketmine\scheduler\CallbackTask;

use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\Item;

use pocketmine\level\Position;
use pocketmine\level\sound\NoteblockSound; //無理やってん。音でぇへんかってん。つらい。
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\PopSound;

use pocketmine\math\Vector3;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;

use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;

use pocketmine\entity\Entity;
use pocketmine\entity\Snowball;

use SnowBallFight\BossEventPacket;

use xenialdan\BossBarAPI\API;

class Main extends PluginBase implements Listener
{
    private $config;
    private $playersData;
    public $participatingPlayers;

    public $teams = array(
        "Red" => array(
            "name" => "Red",
            "displayName" => "§c赤",
            "teamColor" => "§c",
            "id" => 1,
            "players" => array(),
            "points" => 0,
            "isPlaying" => false,
            "isWinner" => false
        ),
        "Blue" => array(
            "name" => "Blue",
            "displayName" => "§b青",
            "teamColor" => "§b",
            "id" => 2,
            "players" => array(),
            "points" => 0,
            "isPlaying" => false,
            "isWinner" => false
        ),
    );

    public $prifks = "§a[§dSBF§a]§f";

    private $s_countDownSeconds = 5;//don't change it!
    private $e_countDownSeconds = 5;//don't change it!

    private $gameRemainingSeconds;

    public $eid = null;

    private $tasks = array(
        "gameWillStartInFiveSeconds" => null,
        "gameStartWait" => null,
        "gameWillEndInFiveSeconds" => null,
    );

    const DO_SEND_BOSSBAR = false;
    const DO_RESET_INVENTORY = false;


    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $messages[] = ("§7Created by §dchell_uoxou §8(@chell_uoxou).");
        $messages[] = ("§c§l二次配布は厳禁です！");
        $messages[] = ("§7アップデートの確認はこちらから：§8https://github.com/chell-uoxou/SnowballFight");

        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
            $message[] = ("[NOTICE]§aデータ保管用のフォルダを作成しました。");
        }
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->playersData = new Config($this->getDataFolder() . "players.json", Config::JSON);

        $getPrifks = $this->config->get("Prifks");
        $this->prifks = "§a[§d{$getPrifks}§a]§f ";
        $messages[] = ("[STATUS]プラグインメッセージ接頭語：" . $this->prifks);
        $messages[] = ("[STATUS]試合時間：§e" . $this->config->get("Interval") . "秒");
        $messages[] = ("[STATUS]試合開始確認間隔：§e" . $this->config->get("Interval") . "秒");
        $messages[] = ("[STATUS]必要最小人数：§e" . $this->config->get("MinNumOfPeople") . "人");
        $messages[] = ("[STATUS]参加可能最大人数：§e" . $this->config->get("MaxNumOfPeople") . "人");

        $this->organizeArrays();
        $this->gameStartWait();
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SendTask($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTickedSecond"]), 20);

        if ($this->config->get("DoSendBossBer") == true) {
            if (!$this->getServer()->getPluginManager()->getPlugin("BossBarAPI")) {
                $messages[] = ("§6[WARNING]BossBarAPIプラグインが見つかりませんでした。");
                $messages[] = ("[STATUS]ウィザーバーの表示：§c無効");
                define('SnowballFight\DO_SEND_BOSSBAR', false);
            } else {
                define('SnowballFight\DO_SEND_BOSSBAR', true);
                $messages[] = ("[STATUS]ウィザーバーの表示；§a有効");
                $messages[] = ("[NOTICE]§6ウィザーバーのシステムは実験的なものです。");
                $messages[] = ("[NOTICE]§6不具合が生じた場合はconfig.ymlのDoSendBossBerの値をfalseに変更してください。");
            }
        } else {
            define('SnowballFight\DO_SEND_BOSSBAR', false);
            $messages[] = ("[STATUS]ウィザーバーの表示：§c無効");
        }

        if ($this->config->get("StartPoint_1")["x"] === null or $this->config->get("StartPoint_2")["x"] === null) {
            $messages[] = ("[NOTICE]§l§cチームのスポーン地点が未設定です。");
            $messages[] = ("[NOTICE]§6/sbf edit <pos1|pos2> にて、必ず設定してください。");
        }

        if ($this->config->get("DoResetInventory") == true) {
            $messages[] = ("[STATUS]インベントリリセット：§c有効");
            $messages[] = ("[NOTICE]§l§cワールドに入室したサバイバルモードのプレイヤーの持ち物がリセットされます。");
        } else {
            $messages[] = ("[STATUS]インベントリリセット：§c無効");
        }

        if ($this->config->get("DoResetInventory") == true) {
            $messages[] = ("[STATUS]インベントリリセット：§c有効");
            $messages[] = ("[NOTICE]§l§cワールドに入室したサバイバルモードのプレイヤーの持ち物がリセットされます。");
        } else {
            $messages[] = ("[STATUS]インベントリリセット：§c無効");
        }

        $longerLength = 0;
        foreach ($messages as $message) {
            $length = strlen(preg_replace('/§./', "", $message)) - (strlen(preg_replace('/§./', "", $message)) - mb_strlen(preg_replace('/§./', "", $message))) / 2;
            if ($longerLength < $length) {
                $longerLength = $length;
            }
        }

        $first_line_length = strlen("=====: " . $this->getFullName() . " :=====");
        $this->getLogger()->info("+ §l=====:§a " . $this->getFullName() . " §f:=====" . str_repeat("=", $longerLength - $first_line_length) . " +");
        foreach ($messages as $message) {
            $spaces = $longerLength - strlen(preg_replace('/§./', "", $message)) + (strlen(preg_replace('/§./', "", $message)) - mb_strlen(preg_replace('/§./', "", $message))) / 2 + 1;
            $this->getLogger()->info("| " . $message . str_repeat(" ", $spaces) . "§f|");
        }
        $this->getLogger()->info("+ " . str_repeat("=", $longerLength) . " +");
    }

    public function onCommand(CommandSender $sender, Command $command,string $label, array $args):bool
    {
        if (isset($args[0])) {
            switch (strtolower($args[0])) {
                case "start":
                    if (!$sender->isOp()) {
                        $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                    } else {
                        if ($this->getConfig()->get("MinNumOfPeople") <= count($this->participatingPlayers)) {
                            if (!$sender instanceof Player) {
                                $this->answerStatus("", $sender);
                            }
                            $sender->sendMessage($this->prifks . "ゲームを開始しました。");
                            $this->gameStart();
                        } else {
                            $sender->sendMessage($this->prifks . "§cゲーム開始に必要な参加者最少人数を満たしていないため、ゲームが開始できませんでした。");
                        }
                    }
                    return true;
                    break;

                case "end":
                    if (!$sender->isOp()) {
                        $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                    } else {
                        if ($this->isPlaying(1) or $this->isPlaying(2)) {
                            $this->end();
                        } else {
                            $sender->sendMessage($this->prifks . "§c現在、進行中のゲームはありません。");
                        }
                        return true;
                    }
                    return true;
                    break;

                case "edit":
                    if (!$sender->isOp()) {
                        $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                    } else {
                        $this->editSettings($args, $sender);
                        return true;
                    }
                    return true;
                    break;

                case "add":
                    if (isset($args[1])) {
                        if ($this->isOnlinePlayer($args[1])) {
                            $player = $this->getServer()->getPlayer($args[1]);
                            $playerName = $args[1];
                            if (!$this->getTeamIdFromPlayer($player)) {
                                $team = $this->add($player);
                                if ($team) {
                                    $teamName = $this->getTeamDisplayName($team);
                                    if ($this->isPlaying($team)) {
                                        $this->joinGame($player);
                                    } else {
                                        $player->sendMessage($this->prifks . "§fあなたは管理者によって{$teamName}チーム§fに参加させられました！\n§eゲーム開始までしばらくお待ちください！");
                                        $sender->sendMessage($this->prifks . "{$playerName}を{$teamName}チームに参加させました。");
                                    }
                                }
                            } else {
                                $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                                $sender->sendMessage($this->prifks . "§c{$playerName}は既に{$teamName}チーム§cに所属しています。");
                            }
                        } else {
                            $sender->sendMessage($this->prifks . "§c{$args[1]}というプレーヤーは見つかりませんでした。");
                        }
                    } else {
                        $sender->sendMessage($this->prifks . "Usage: /sbf add <player name>");
                    }
                    return true;
                    break;

                case "status":
                    if (!$sender->isOp()) {
                        if ($this->getConfig()->get("AllowStatusCommand")) {
                            $this->answerStatus($args, $sender);
                        } else {
                            $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                        }
                    } else {
                        $this->answerStatus($args, $sender);
                    }
                    return true;
                    break;

                case "join":
                    if ($sender instanceof Player) {
                        if (!$this->getTeamIdFromPlayer($sender)) {
                            $team = $this->add($sender);
                            if ($team) {
                                $teamName = $this->getTeamDisplayName($team);
                                if ($this->isPlaying($team)) {
                                    $this->joinGame($sender);
                                    $playerName = $sender->getName();
                                    $this->sendMessageInGame($this->prifks . "§a{$playerName}§aさんが{$teamName}チームに参加しました。");
                                } else {
                                    $sender->sendMessage($this->prifks . "§fあなたは{$teamName}チーム§fです！\n§eゲーム開始までしばらくお待ちください！");
                                }
                            }
                        } else {
                            $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                            $sender->sendMessage($this->prifks . "§fあなたは既に{$teamName}チーム§fに所属しています！\n§eゲーム開始までしばらくお待ちください！");
                        }
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    return true;
                    break;

                case "cancel":
                    if ($sender instanceof Player) {
                        $team = $this->getTeamIdFromPlayer($sender);
                        $playerName = $sender->getName();
                        if ($team) {
                            if ($this->isPlaying($sender)) {
                                $sender->sendMessage("§7ゲームをリタイアしました。");
                                $this->sendMessageInGame($this->prifks . "§7{$playerName}§7さんがゲームをリタイアしました。");
                                $pos = $sender->getLevel()->getSpawnLocation();
                                $sender->teleport($pos);
                                if ($sender->getGamemode() != 1) {
                                    $sender->getInventory()->clearAll();
                                    $sender->getInventory()->setArmorItem(1, Item::get(0, 0, 0));
                                }
                            } else {
                                $teamName = $this->getTeamDisplayName($team);
                                $sender->sendMessage($this->prifks . "{$teamName}チーム§fへの参加をキャンセルしました。");
                            }
                            $this->delPlayerFromTeam($sender);
                        } else {
                            $sender->sendMessage($this->prifks . "§cあなたはどこのチームにも所属していません！");
                        }
                    } else {
                        if (isset($args[1])) {
                            if ($this->isOnlinePlayer($args[1])) {
                                $player = $this->getServer()->getPlayer($args[1]);
                                $playerName = $args[1];
                                if ($this->isPlaying($player)) {
                                    $team = $this->getTeamIdFromPlayer($player);
                                    if ($team) {
                                        $this->delPlayerFromTeam($player);
                                        $teamName = $this->getTeamDisplayName($team);
                                        $sender->sendMessage($this->prifks . "{$playerName}の{$teamName}チーム§fへの参加をキャンセルしました。");
                                        $player->sendMessage($this->prifks . "§c管理者によって{$teamName}チーム§cへの参加がキャンセルされました。");
                                    } else {
                                        $sender->sendMessage($this->prifks . "§c{$playerName}はどこのチームにも所属していません！");
                                    }
                                }
                            } else {
                                $sender->sendMessage($this->prifks . "§c{$args[1]}というプレーヤーは見つかりませんでした。");
                            }
                        } else {
                            $sender->sendMessage($this->prifks . "Usage: /sbf cancel <player name>");
                        }
                    }
                    return true;
                    break;

                case "refill":
                    if ($sender instanceof Player) {
                        if ($this->isPlaying($sender)) {
                            $this->refillItems($sender);
                            $sender->sendMessage($this->prifks . "§b雪玉を補充しました！");
                        } else {
                            $sender->sendMessage($this->prifks . "§cゲーム中ではないため雪玉を得ることができません。");
                        }
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    return true;
                    break;
                default:
                    return false;
            }
        } else {
            return false;
        }
    }

    private function answerStatus($args, $sender)
    {
        if (isset($args[1])) {
            switch ($args[1]) {
                case "":
            }
        } else {
            $teamName1 = $this->getTeamDisplayName(1);
            $teamName2 = $this->getTeamDisplayName(2);
            $currentPlayerCount1 = $this->getTeamPlayersCount(1);
            $currentPlayerCount2 = $this->getTeamPlayersCount(2);
            $playerLimit = $this->getConfig()->get("MaxNumOfPeople");
            $playingTeams = array();
            $playingTeamsText = "";

            if ($this->isPlaying(1) or $this->isPlaying(2)) {
                $gameStatusText = "§aDuring the game";
                foreach ($this->teams as $team) {
                    if ($team["isPlaying"]) {
                        $playingTeams[] = $team["displayName"];
                    }
                }
                foreach ($playingTeams as $playingTeam) {
                    if (isset($playingTeams[0])) {
                        $playingTeamsText .= ", " . $playingTeam;
                    } else {
                        $playingTeamsText .= $playingTeam;
                    }
                }
            } else {
                $gameStatusText = "§6Waiting for join";
                $playingTeamsText = "N/A";
            }
            if (count($this->participatingPlayers) != 0) {
                $playersInTeam = "";
                foreach ($this->teams as $team) {
                    foreach ($team["players"] as $player) {
                        $playerName = $player->getName();
                        if ($playersInTeam != "") {
                            $playersInTeam .= ", §l§f[{$team["displayName"]}§f]§r§a {$playerName}";
                        } else {
                            $playersInTeam .= "§l§f[{$team["displayName"]}§f]§r§a {$playerName}";
                        }
                    }
                }
                $playersInTeamText = "\n-- Each team players --\n" . $playersInTeam;
            } else {
                $playersInTeamText = "";
            }

            $messages = array(
                "",
                "§b=== §fSnowball Fight System Status §b===",
                "  {$teamName1} Team players count : {$currentPlayerCount1}/{$playerLimit}",
                "  {$teamName2} Team players count : {$currentPlayerCount2}/{$playerLimit}",
                "  Game status : {$gameStatusText}",
                "  Playing teams : {$playingTeamsText}",
                $playersInTeamText
            );

            foreach ($messages as $message) {
                $sender->sendMessage($message);
            }
        }
    }

    private function editSettings($args, $sender)
    {
        if (isset($args[1])) {
            switch (strtolower($args[1])) {
                case "pos1":
                    if ($sender instanceof Player) {
                        $result = $this->setPosition("pos1", $sender);
                        $sender->sendMessage($this->prifks . "TeamID:1 のプレーヤースポーン地点を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    break;

                case "pos2":
                    if ($sender instanceof Player) {
                        $result = $this->setPosition("pos2", $sender);
                        $sender->sendMessage($this->prifks . "TeamID:2 のプレーヤースポーン地点を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    break;

                default:
                    $sender->sendMessage("Usage: /sbf edit < pos1 | pos2 >");
            }
        } else {
            $sender->sendMessage("Usage: /sbf edit < pos1 | pos2 >");
        }
    }

    private function setPosition($object, $player)
    {
        switch ($object) {
            case "pos1":
                $startPosition1 = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
                $this->getConfig()->set("StartPoint_1", array(
                    'world' => $startPosition1->getLevel()->getName(),
                    'x' => $startPosition1->getFloorX(),
                    'y' => $startPosition1->getFloorY(),
                    'z' => $startPosition1->getFloorZ()
                ));
                $this->getConfig()->save();
                return $startPosition1;
                break;

            case "pos2":
                $startPosition2 = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
                $this->getConfig()->set("StartPoint_2", array(
                    'world' => $startPosition2->getLevel()->getName(),
                    'x' => $startPosition2->getFloorX(),
                    'y' => $startPosition2->getFloorY(),
                    'z' => $startPosition2->getFloorZ()
                ));
                $this->getConfig()->save();
                return $startPosition2;
                break;

        }
    }

    private function add($player)
    {
        $team = $this->chooseTeamToJoin();
        //echo "chosenTeamId:$team\n";
        $this->addPlayerToTeam($player, $team);
        $this->organizeArrays();
        return $team;
    }

    private function teleportToEachSpawn($player)
    {
        $team = $this->getTeamIdFromPlayer($player);
        switch ($team) {
            case 1:
                $data = $this->getConfig()->get("StartPoint_1");
                $level = $this->getServer()->getLevelByName($data["world"]);
                $pos = new Position($data["x"], $data["y"], $data["z"], $level);
                break;
            case 2:
                $data = $this->getConfig()->get("StartPoint_2");
                $level = $this->getServer()->getLevelByName($data["world"]);
                $pos = new Position($data["x"], $data["y"], $data["z"], $level);
                break;
            default:
                $pos = $player->getLevel()->getSpawnLocation();
                break;
        }
        if ($this->eid !== null and self::DO_SEND_BOSSBAR == true) {
            API::removeBossBar([$player], $this->eid);
        }
        $player->teleport($pos);
        if ($this->eid !== null and self::DO_SEND_BOSSBAR == true) {
            API::sendBossBarToPlayer($player, $this->eid, '残り時間');
        }
    }

///  Game  /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function gameStart()
    {
        $this->getServer()->broadcastMessage($this->prifks . "ゲームを開始しました。");

        $this->gameRemainingSeconds = $this->getConfig()->get("Interval");

        $this->cancelAllTasks();
        $this->tasks["gameWillEndInFiveSeconds"] = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameWillEndInFiveSeconds"]), 20 * ($this->getConfig()->get("Interval") - 5));

        foreach ($this->participatingPlayers as $p) {
            $this->joinGame($p);
        }
        if (self::DO_SEND_BOSSBAR == true) {
            if ($this->eid === null) {
                $this->eid = API::addBossBar($this->participatingPlayers, '残り時間');
            } else {
                foreach ($this->participatingPlayers as $p) {
                    API::sendBossBarToPlayer($p, $this->eid, '残り時間');
                }
            }
        }

        foreach ($this->teams as $team) {
            $teamId = $team["id"];
            $this->setTeamData($teamId, "isPlaying", true);
        }
        $this->organizeArrays();
    }

    public function refillItems($player)
    {
        if ($player instanceof Player) {
            $item = array(
                "snowball_16" => Item::get(332, 0, 16),
                "tunic" => Item::get(229, 0, 1)
            );
            $player->getInventory()->clearAll();
            for ($i = 0; $i < 9; $i++) {
                $player->getInventory()->setItem($i, $item["snowball_16"]);
            }
            return true;
        } else {
            return false;
        }
    }

    private function joinGame($p)
    {
        if ($p instanceof Player) {
            $teamId = $this->getTeamIdFromPlayer($p);
            $teamDisplayName = $this->getTeamDisplayName($teamId);
            $teamName = $this->getTeamName($teamId);
            $playerName = $p->getName();
            $this->teleportToEachSpawn($p);
            if ($this->isPlaying($teamId)) {
                $this->sendMessageInGame($this->prifks . $playerName . "さんが{$teamDisplayName}としてゲームに参加しました！");
            }
            if ($p->getGamemode() != 1) {
                $p->setGamemode(2);
                $item = array(
                    "snowball_16" => Item::get(332, 0, 16),
                    "tunic" => Item::get(229, 0, 1)
                );
                $this->giveColorArmor($p, $item["tunic"], $teamName);
                $this->refillItems($p);
            }
            $p->addTitle("ゲームスタート！", "§fあなたは{$teamDisplayName}チーム§fです！", $fadein = 0, $duration = 2, $fadeout = 20);
        }
    }

    public function end($type = NULL)
    {
        $this->cancelAllTasks();

        if (self::DO_SEND_BOSSBAR == true) {
            API::removeBossBar($this->getServer()->getOnlinePlayers(), $this->eid);
        }

        $point1 = $this->getTeamPoint(1);
        $point2 = $this->getTeamPoint(2);
        switch (true) {
            case $point1 > $point2:
                $gameResult = $this->getTeamDisplayName(1) . "チームの勝利";
                $winnerTeam = $this->getTeamDisplayName(1);
                break;

            case $point1 < $point2:
                $gameResult = $this->getTeamDisplayName(2) . "チームの勝利";
                $winnerTeam = $this->getTeamDisplayName(2);
                break;

            case $point1 == $point2:
                if ($point1 = "") {
                    $point1 = 0;
                }
                if ($point2 = "") {
                    $point2 = 0;
                }
                $gameResult = "§a引き分け";
                break;
        }
        $winLoseRatio = $this->getTeamColor(1) . "$point1 §f: " . $this->getTeamColor(2) . "$point2 §f";

        foreach ($this->participatingPlayers as $p) {
            if (!isset($type)) {
                $p->addTitle($gameResult . "！", $winLoseRatio, 10, 3, 60);
            } else {
                switch ($type) {
                    case "too little":
                        $p->addTitle("§c対戦相手がいません！", $winLoseRatio . "で" . $gameResult . "§fです。", 10, 3, 60);
                        break;
                    case "big deviation":
                        $p->addTitle("§c人数差が発生しました！", $winLoseRatio . "で" . $gameResult . "§fです。", 10, 3, 60);
                        break;
                }
            }
            $pos = $p->getLevel()->getSpawnLocation();
            $p->teleport($pos);
            if ($p->getGamemode() != 1) {
                $p->getInventory()->clearAll();
                $p->getInventory()->setArmorItem(1, Item::get(0, 0, 0));
            }
            $p->setNameTag($p->getName());
            $p->setDisplayName($p->getName());
        }

        if (!isset($type)) {
            $this->getServer()->broadcastMessage($this->prifks . "§aゲーム終了、{$winLoseRatio}で{$gameResult}です。");
        } else {
            switch ($type) {
                case "too little":
                    $this->getServer()->broadcastMessage($this->prifks . "§6ゲームの最小参加人数を下回ったためゲームを終了しました。");
                    $this->getServer()->broadcastMessage($this->prifks . "終了段階の試合結果は、{$winLoseRatio}で{$gameResult}です。");
                    break;

                case "big deviation":
                    $this->getServer()->broadcastMessage($this->prifks . "§6チームの人数に大きな偏りが生じたためゲームを終了しました。");
                    $this->getServer()->broadcastMessage($this->prifks . "終了段階の試合結果は、{$winLoseRatio}で{$gameResult}です。");
                    break;
            }
        }

        $this->initTeamData();

        $this->gameRemainingSeconds = $this->getConfig()->get("Interval");

        $this->tasks["gameStartWait"] = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameStartWait"]), 20 * 15);

        $this->organizeArrays();
    }

    public function attacked(EntityDamageEvent $event)
    {
        $cause = $event->getCause();
        if (method_exists($event, "getDamager")) {
            $damager = $event->getDamager();
            $damaged = $event->getEntity();////////
            if ($this->isPlaying($damager) and $this->isPlaying($damaged)) {
                if ($cause == EntityDamageEvent::CAUSE_PROJECTILE and substr($event->getChild(), 0, 4) == "Snow") {
                    $damagerDisplayName = $damager->getNameTag();

                    if (!$this->isOnlinePlayer($damagerDisplayName)) {
                        $damagerTTLength = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $damagerDisplayName)) + 5;
                        $damagerName = mb_substr($damagerDisplayName, $damagerTTLength);
                    } else {
                        $damagerName = $damagerDisplayName;
                    }
                    $damagedPlayerDisplayName = $damaged->getNameTag();
                    if (!$this->isOnlinePlayer($damagedPlayerDisplayName)) {
                        $damagedPlayerTTLength = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $damagedPlayerDisplayName)) + 5;
                        $damagedPlayerName = mb_substr($damagedPlayerDisplayName, $damagedPlayerTTLength);
                    } else {
                        $damagedPlayerName = $damagedPlayerDisplayName;
                    }
                    $damagerPlayer = $this->getServer()->getPlayer($damagerName);
                    $damagedPlayer = $this->getServer()->getPlayer($damagedPlayerName);

                    $damagedTeam = $this->getTeamIdFromPlayer($damagedPlayer);
                    $damagerTeam = $this->getTeamIdFromPlayer($damagerPlayer);

                    if ($damagerTeam != $damagedTeam) {
                        $this->addTeamPoint($damagerTeam, 1);

                        $this->sendMessageInGame($this->prifks . "{$damagerDisplayName} §l->§r {$damagedPlayerDisplayName}");
                        $this->teleportToEachSpawn($damagedPlayer);

                        $damagerPlayer->sendTip("§a§lShot! >> {$damagedPlayerName}");
                        $damagedPlayer->sendTip("§c§lDamaged! << {$damagerName}");
                    }
                }
            }
        }
    }

//    public function ProjectileLaunch(ProjectileLaunchEvent $event)
//    {
//        $entity = $event->getEntity();
//        $launcherDisplayName = $entity->getNameTag();
//        if (!$this->isOnlinePlayer($launcherDisplayName)) {
//            $launcherTTLength = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $launcherDisplayName)) + 5;
//            $launcherName = mb_substr($launcherDisplayName, $launcherTTLength);
//        } else {
//            $launcherName = $launcherDisplayName;
//        }
//        echo "display:$launcherDisplayName";
//        $launcher = $this->getServer()->getPlayer($launcherName);
//        $item = Item::get(Item::SNOWBALL, 0, 3);
//        if (!$launcher->getInventory()->contains($item)) {
//            if ($launcher->getGamemode() != 1) {
//                $launcher->setGamemode(2);
//                $item = array(
//                    "snowball_16" => Item::get(332, 0, 16),
//                    "tunic" => Item::get(229, 0, 1)
//
//                );
//                $launcher->getInventory()->clearAll();
//                $launcher->getInventory()->setItem(0, $item["snowball_16"]);
//            }
//        } else {
//
//        }
//
//    }//玉の自動補充 破損してる

    public function sendMessageInGame($message)
    {
        foreach ($this->participatingPlayers as $player) {
            $player->sendMessage($message);
        }
    }

    public function sendMessageInTeam($message, $team, $toPlaying = false)
    {
        if ($toPlaying) {
            $players = $this->participatingPlayers;
        } else {
            $players = $this->getServer()->getOnlinePlayers();
        }

        foreach ($players as $player) {
            $playerName = $player->getName();
            if ($this->getTeamName($this->getTeamIdFromPlayer($playerName)) == $team) {
                $player->sendMessage($message);
            }
        }
    }

    private function isOnlinePlayer($name)
    {
        $return = false;
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if ($player->getName() == $name) {
                $return = true;
                break;
            }
        }
        return $return;
    }

/// System /////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function organizeArrays()
    {
        $this->participatingPlayers = array();
        foreach ($this->teams as $team) {
            $unique = array_unique($team["players"]);
            $team["players"] = array_values($unique);

            foreach ($team["players"] as $player) {
                $this->participatingPlayers[] = $player;
            }
        }

        foreach ($this->participatingPlayers as $player) {
            $teamId = $this->getTeamIdFromPlayer($player);
            if ($this->isPlaying($teamId)) {
                $teamDisplayName = $this->getTeamDisplayName($teamId);
                $player->setNameTag("§l§f[{$teamDisplayName}§f]§r" . $player->getName());
                $player->setDisplayName("§l§f[{$teamDisplayName}§f]§r" . $player->getName());
            } else {
                $player->setNameTag($player->getName());
                $player->setDisplayName($player->getName());
            }
        }
        if ($this->isPlaying(1)) {
            if (count($this->participatingPlayers) <= 1) {
                $this->end("too little");
            }
            switch (true) {
                case $this->getTeamPlayersCount(1) * 2 < $this->getTeamPlayersCount(2):
                    $lessTeam = (int)1;
                    break;

                case $this->getTeamPlayersCount(1) > $this->getTeamPlayersCount(2) * 2:
                    $lessTeam = (int)2;
            }
            if (isset($lessTeam)) {
                $this->end("big deviation");
            }
        }
    }

    private function initTeamData()
    {
        $this->teams = array(
            "Red" => array(
                "name" => "Red",
                "displayName" => "§c赤",
                "teamColor" => "§c",
                "id" => 1,
                "players" => array(),
                "points" => 0,
                "isPlaying" => false,
                "isWinner" => false
            ),
            "Blue" => array(
                "name" => "Blue",
                "displayName" => "§b青",
                "teamColor" => "§b",
                "id" => 2,
                "players" => array(),
                "points" => 0,
                "isPlaying" => false,
                "isWinner" => false
            ),
        );
    }

    private function getTeamData($id, $key)
    {
        foreach ($this->teams as $team) {
            if (is_int($id)) {
                if ($team["id"] == $id) {
                    $data = $team[$key];
                    break;
                }
            }
        }
        if (!isset($data)) {
            return false;
        } else {
            return $data;
        }
    }

    private function setTeamData($id, $key, $value)
    {
        foreach ($this->teams as $name => $team) {
            if ($team["id"] == $id) {
                $this->teams[$name][$key] = $value;
                break;
            }
        }
    }

    public function getTeamName($id)
    {
        return $this->getTeamData($id, "name");
    }

    public function getTeamColor($id)
    {
        return $this->getTeamData($id, "teamColor");

    }

    public function getTeamDisplayName($id)
    {
        return $this->getTeamData($id, "displayName");
    }

    public function getTeamPlayersCount($id)
    {
        return count($this->getTeamData($id, "players"));
    }

    public function getTeamCount()
    {
        return count($this->teams);
    }

    public function getTeamPoint($id)
    {
        if (isset($this->teams[$this->getTeamName($id)]["points"])) {
            return (int)$this->teams[$this->getTeamName($id)]["points"];
        } else {
            return 0;
        }
    }

    public function addTeamPoint($id, $point)
    {
        $teamName = $this->getTeamName($id);
        if (isset($this->teams[$teamName]["points"])) {
            $cuPoint = $this->teams[$teamName]["points"];
            $this->teams[$teamName]["points"] = $cuPoint + $point;
        } else {
            return false;
        }
    }

    public function addPlayerToTeam($player, $teamId)
    {
        //echo "teamid:$teamId\n";
        $this->teams[$this->getTeamName($teamId)]["players"][] = $player;
        //echo "349\n";
        //var_dump($this->teams);
        $this->organizeArrays();
    }

    public function delPlayerFromTeam($player)
    {
        $teamId = $this->getTeamIdFromPlayer($player);
        $arrayNum = array_search($player, $this->teams[$this->getTeamName($teamId)]["players"]);
        unset($this->teams[$this->getTeamName($teamId)]["players"][$arrayNum]);
        $this->organizeArrays();
    }

    public function getTeamIdFromPlayer($player)
    {
        if (is_string($player)) {
            $player = $this->getServer()->getPlayer($player);
        }
        foreach ($this->teams as $team) {
            if (in_array($player, $team["players"])) {
                //echo "368\n";
                //var_dump($this->teams);
                $teamId = $team["id"];
                break;
            }
        }
        if (isset($teamId)) {
            return $teamId;
        } else {
            return false;
        }
    }

    public function isPlaying($teamOrPlayer)
    {
        if ($teamOrPlayer instanceof Player) {
            $team = $this->getTeamIdFromPlayer($teamOrPlayer);
        } else {
            $team = $teamOrPlayer;
        }
        return $this->getTeamData($team, "isPlaying");
    }

    public function chooseTeamToJoin()
    {
        $pc1 = $this->getTeamPlayersCount(1);
        $pc2 = $this->getTeamPlayersCount(2);
        //echo("pc1:$pc1, pc2:$pc2\n");
        if ($pc1 == $pc2) {
            return rand(1, 2);
        }
        if ($pc1 > $pc2) {
            return (int)2;
        }
        if ($pc1 < $pc2) {
            return (int)1;
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if (!$player->isOp()) {
            $player->getInventory()->clearAll();
            $player->setGamemode(2);
        }
        $playerName = $player->getName();
        $this->playersData->set($playerName, array(
            "kill" => 0,
            "points" => 0,
            "killer" => array(),
            "killed" => array()
        ));
    }

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->getTeamIdFromPlayer($player)) {
            $this->delPlayerFromTeam($player);
        }
    }

    //Score/////////////////////////////////////////////////////////////

    //Chat//////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function onChat(PlayerChatEvent $e)
    {
        $message = $e->getMessage();
        $player = $e->getPlayer();
        $e->setCancelled();
        $playerName = $player->getName();
        $teamId = $this->getTeamIdFromPlayer($player);
        $team = $this->getTeamName($teamId);
        if ($teamId) {
            if ($this->config->get("ChatInTeam") == true) {
                if ($message[0] != "!" and $message[0] != "！") {
                    $this->sendMessageInTeam($this->getTeamColor($teamId) . "[team]$playerName §r>> §l" . $message . "　", $team);
                    $this->getLogger()->info($this->getTeamColor($teamId) . "[team]$playerName §r>> §l" . $message . "　");
                } else {
                    $message = substr($message, 1);
                    $this->getServer()->broadcastMessage("§6[chat]§r$playerName >> §l" . $message . "　");
                }
            } else {
                if ($message[0] == "!" or $message[0] == "！") {
                    $message = substr($message, 1);
                    $this->sendMessageInTeam($this->getTeamColor($teamId) . "[team]$playerName §r>> §l" . $message . "　", $team);
                    $this->getLogger()->info($this->getTeamColor($teamId) . "[team]$playerName §r>> §l" . $message . "　");
                } else {
                    $this->getServer()->broadcastMessage("§6[chat]§r$playerName >> §l" . $message . "　");
                }
            }
        } else {
            $this->getServer()->broadcastMessage("§6[chat]§r$playerName >> §l" . $message . "　");
        }
    }

    //Scheduler/////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function gameStartWait()
    {
        $waitSeconds = $this->getConfig()->get("WaitInterval");
        if ($this->getConfig()->get("MinNumOfPeople") <= count($this->participatingPlayers)) {
            $this->cancelAllTasks();
            $this->tasks["gameWillStartInFiveSeconds"] = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameWillStartInFiveSeconds"]), 20 * $waitSeconds);
        } else {
//            $this->getLogger()->info("Scheduler >> 参加人数不足。{$waitSeconds}秒後に再試行...");
            $this->cancelAllTasks();
            $this->tasks["gameStartWait"] = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameStartWait"]), 20 * 15);
        }
    }

    public function gameWillStartInFiveSeconds()
    {
        if ($this->s_countDownSeconds <= 0) {
            $this->s_countDownSeconds = 5;
            foreach ($this->participatingPlayers as $player) {
                $pos = $player->getPosition();
                $level = $pos->getLevel();
                $sound = new AnvilFallSound($pos);
                $level->addSound($sound);
            }
            $this->gameStart();
        } else {
            foreach ($this->participatingPlayers as $player) {
                if ($player instanceof Player) {
                    $player->sendPopup($this->s_countDownSeconds);
                    $pos = $player->getPosition();
                    $level = $pos->getLevel();
                    $sound = new PopSound($pos);
                    $level->addSound($sound);
                }
            }
            $this->getLogger()->info("Scheduler >> $this->s_countDownSeconds");
            $this->s_countDownSeconds--;
            $this->tasks["gameWillStartInFiveSeconds"] = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameWillStartInFiveSeconds"]), 20);
        }
    }

    public function gameWillEndInFiveSeconds()
    {
        if ($this->e_countDownSeconds <= 0) {
            $this->e_countDownSeconds = 5;
            foreach ($this->participatingPlayers as $player) {
                $pos = $player->getPosition();
                $level = $pos->getLevel();
                $sound = new AnvilFallSound($pos);
                $level->addSound($sound);
            }
            $this->end();
        } else {
            foreach ($this->participatingPlayers as $player) {
                if ($player instanceof Player) {
                    $player->sendPopup($this->e_countDownSeconds);
                    $pos = $player->getPosition();
                    $level = $pos->getLevel();
                    $sound = new PopSound($pos);
                    $level->addSound($sound);
                }
            }
            $this->getLogger()->info("Scheduler >> $this->e_countDownSeconds");
            $this->e_countDownSeconds--;
            $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gameWillEndInFiveSeconds"]), 20);
        }
    }

    private function cancelAllTasks()
    {
        foreach ($this->tasks as $key => $task) {
            if (method_exists($task, "cancel")) {
                $task->cancel();
//                echo $key . "  ";
            }
        }
//        $this->getLogger()->notice("Scheduler >> All tasks of [Snow Ball Fight] were canceled.");
    }

    private function cancelTask($taskID)
    {
        $this->getServer()->getScheduler()->cancelTask($taskID);
    }

    public function onTickedSecond()
    {
        if ($this->isPlaying(1)) {
            $this->getLogger()->info($this->gameRemainingSeconds);
            if (self::DO_SEND_BOSSBAR == true) {
                $this->sendBossBar();
            }
            $this->gameRemainingSeconds--;
        }
    }

    public function sendBossBar()
    {
        if (self::DO_SEND_BOSSBAR == true) {
            if ($this->eid === null) return;
            $maxTimeLimit = $this->getConfig()->get("Interval");
            $current = $this->gameRemainingSeconds;
            $percentage = $current / $maxTimeLimit * 100;
            API::setPercentage($percentage, $this->eid, $this->participatingPlayers);

            $minutes = floor(($current / 60) % 60);
            $seconds = $current % 60;
            $hms = sprintf("%02d:%02d", $minutes, $seconds);
            API::setTitle("§l残り時間\n\n§l§6[" . $hms . "]", $this->eid, $this->participatingPlayers);
        } else {
            return;
        }
    }

    //From API//////////////////////////////////////////////////////////////////////////////////////////////////////////
    /* Quotes from CreateColorArmor_v1.0.1 by vardo@お鳥さん
     * website : https://forum.pmmp.jp/threads/697/
     * Thanks to the author.
     */
    public function giveColorArmor(Player $player, Item $item, String $colonm)
    {
        $tempTag = new CompoundTag("", []);
        $color = $this->getColorByName($colonm);
        $tempTag->customColor = new IntTag("customColor", $color);
        $item->setCompoundTag($tempTag);

        switch ($item->getId()) {
            case "298":
                $player->getInventory()->setArmorItem(0, $item);
                break;


            case "299":
                $player->getInventory()->setArmorItem(1, $item);
                break;


            case "300":
                $player->getInventory()->setArmorItem(2, $item);
                break;


            case "301":
                $player->getInventory()->setArmorItem(3, $item);
                break;
        }

        $player->getInventory()->sendArmorContents($player);

        switch ($item->getId()) {
            case "302":
            case "303":
            case "304":
            case "305":
            case "306":
            case "307":
            case "308":
            case "309":
            case "310":
            case "311":
            case "312":
            case "313":
            case "314":
            case "315":
            case "316":
            case "317":
                $this->getLogger()->notice("CCA>> 革装備にのみ適用可能です");
                break;
        }
    }

    private function getColorByName(String $name)
    {
        $colornm = strtoupper($name);
        switch ($colornm) {
            case "RED":
            case "赤":
                return "16711680";
                break;

            case "ORANGE":
            case "オレンジ":
                return "16744192";
                break;

            case "BLUE":
            case "青":
                return "255";
                break;

            case "AQUA":
            case "アクア":
                return "39372";
                break;

            case "GREEN":
            case "緑":
                return "3100463";
                break;

            case "LIME":
            case "黄緑":
                return "3329330";
                break;

            case "PINK":
            case "ピンク":
                return "15379946";
                break;

            case "PURPLE":
            case "紫":
                return "8388736";
                break;

            case "WHITE":
            case "白":
                return "16777215";
                break;

            case "GRAY":
            case "灰":
                return "12632256";
                break;

            case "LIGHTGRAY":
            case "薄灰":
                return "14211263";
                break;

            case "BLACK":
            case "黒":
                return "0";
                break;

            case "MAGENTA":
            case "マゼンタ":
                return "16711935";
                break;

            case "BROWN":
            case "茶":
                return "10824234";
                break;

            case "CYAN":
            case "シアン":
                return "35723";
                break;

            case "SKY":
            case "空":
                return "65535";
                break;

            case "YELLOW":
            case "黄":
                return "16776960";
                break;

            case "GOLD":
            case "金":
                return "14329120";
                break;

            case "SILVER":
            case "銀":
                return "15132922";
                break;

            case "BRONZE":
            case "銅":
                return "9205843";
                break;
        }
    }

    /**
     * @return array
     */
    public function getJsonCommands()
    {
        // TODO: Implement getJsonCommands() method.
    }

    public function setJsonCommands($commands)
    {
        // TODO: Implement setJsonCommands() method.
    }

    public function generateJsonCommands($pluginCmds)
    {
        // TODO: Implement generateJsonCommands() method.
    }
}