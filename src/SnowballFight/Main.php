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

use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\Item;

use pocketmine\level\Position;

use pocketmine\math\Vector3;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;

class Main extends PluginBase implements Listener
{

    private $config;
    public $participatingPlayers;

    public $teams = array(
        "Red" => array(
            "name" => "Red",
            "displayName" => "§c赤",
            "id" => 1,
            "players" => array(),
            "isPlaying" => false,
            "isWinner" => false
        ),
        "Blue" => array(
            "name" => "Blue",
            "displayName" => "§b青",
            "id" => 2,
            "players" => array(),
            "isPlaying" => false,
            "isWinner" => false
        ),
    );

    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info($this->getFullName() . " by chell_uoxou Loaded!");
//        $this->getLogger()->info("§c二次配布は厳禁です！");
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
        }
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML,
            array(
                'time' => 60,
                'MinNumOfPeople' => 2,
                'MaxNumOfPeople' => 10,
                'startPoint_1' => array(
                    'world' => null,
                    'x' => null,
                    'y' => null,
                    'z' => null
                ),
                'startPoint_2' => array(
                    'world' => null,
                    'x' => null,
                    'y' => null,
                    'z' => null
                )
            ));

        $this->organizeArrays();
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (isset($args[0])) {
            switch (strtolower($args[0])) {
                case "start":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§cYou don't have permission to use this command.");
                    } else {
                        if ($this->getConfig()->get("MinNumOfPeople") <= count($this->participatingPlayers)) {
                            $this->gameStart();
                        } else {
                            $sender->sendMessage("§cゲーム開始に必要な参加者最少人数を満たしていないため、ゲームが開始できませんでした。");
                        }
                    }
                    return true;
                    break;

                case "end":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§cYou don't have permission to use this command.");
                    } else {
                        if ($this->isPlaying(1) or $this->isPlaying(2)) {
                            $this->end();
                        } else {
                            $sender->sendMessage("§c現在、進行中のゲームはありません。");
                        }
                        return true;
                    }
                    return true;
                    break;

                case "edit":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§cYou don't have permission to use this command.");
                    } else {
                        $this->editSettings($args, $sender);
                        return true;
                    }
                    return true;
                    break;

                case "add":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§cYou don't have permission to use this command.");
                    } else {
                        $this->end();
                        return true;
                    }
                    return true;
                    break;

                case "status":
                    $this->answerStatus($args, $sender);
                    return true;
                    break;

                case "join":
                    if ($sender instanceof Player) {
                        if (!$this->getTeamIdFromPlayer($sender)) {
                            $team = $this->add($sender);
                            if ($team) {
                                $teamName = $this->getTeamDisplayName($team);
                                if ($this->isPlaying($team)) {
                                    $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                                    $this->teleportToEachSpawn($sender);
                                    $sender->sendTitle("ゲームスタート！", "§fあなたは{$teamName}チーム§fです！", $fadein = 0, $duration = 2, $fadeout = 20);
                                } else {
                                    $sender->sendMessage("§fあなたは{$teamName}チーム§fです！\n§eゲーム開始までしばらくお待ちください！");
                                }
                            }
                        } else {
                            $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                            $sender->sendMessage("§fあなたは既に{$teamName}チーム§fに所属しています！\n§eゲーム開始までしばらくお待ちください！");
                        }
                    } else {
                        $sender->sendMessage("§cPlease run this command in-game.");
                    }
                    return true;
                    break;

                case "cancel":
                    if ($sender instanceof Player) {
                        $team = $this->getTeamIdFromPlayer($sender);
                        if ($team) {
                            $this->delPlayerFromTeam($sender);
                            $teamName = $this->getTeamDisplayName($team);
                            $sender->sendMessage("{$teamName}チーム§fへの参加をキャンセルしました。");
                        } else {
                            $sender->sendMessage("§cあなたはどこのチームにも所属していません！");
                        }
                    } else {
                        $sender->sendMessage("§cPlease run this command in-game.");
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

    private function onStruck($player)
    {

    }

    private function answerStatus($args, $sender)
    {
        if (isset($args[1])) {
            switch ($args[1]) {
                case "":
            }
        } else {
            $teamName1 = $this->getTeamName(1);
            $teamName2 = $this->getTeamName(2);
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
        switch (strtolower($args[1])) {
            case "pos1":
                if ($sender instanceof Player) {
                    $result = $this->setPosition("pos1", $sender);
                    $sender->sendMessage("TeamID:1 のプレーヤースポーン地点を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                } else {
                    $sender->sendMessage("§cPlease run this command in-game.");
                }
                break;

            case "pos2":
                if ($sender instanceof Player) {
                    $result = $this->setPosition("pos2", $sender);
                    $sender->sendMessage("TeamID:2 のプレーヤースポーン地点を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                } else {
                    $sender->sendMessage("§cPlease run this command in-game.");
                }
                break;

            default:
                $sender->sendMessage("Usage: /sbf edit < pos1 | pos2 >");
        }
    }

    private function setPosition($object, $player)
    {
        switch ($object) {
            case "pos1":
                $startPosition1 = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
                $this->getConfig()->set("startPoint_1", array(
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
                $this->getConfig()->set("startPoint_2", array(
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

    private function gameStart()
    {
        foreach ($this->participatingPlayers as $p) {
            $teamId = $this->getTeamIdFromPlayer($p);
            $teamName = $this->getTeamName($teamId);
            $this->teleportToEachSpawn($p);
            if ($p->getGamemode() != 1) {
                $item = array(
                    "snowball_16" => Item::get(332, 0, 16),
                    "tunic" => Item::get(229, 0, 1)

                );
                $this->giveColorArmor($p, $item["tunic"], $teamName);
                $p->getInventory()->clearAll();
                $p->getInventory()->setItem(0, $item["snowball_16"]);
                $p->getInventory()->setItem(1, $item["snowball_16"]);
                $p->getInventory()->setItem(2, $item["snowball_16"]);
                $p->getInventory()->setItem(3, $item["snowball_16"]);
                $p->getInventory()->setItem(4, $item["snowball_16"]);
            }
            $p->sendTitle("ゲームスタート！", "§fあなたは{$teamName}チーム§fです！", $fadein = 0, $duration = 2, $fadeout = 20);
        }
        foreach ($this->teams as $team) {
            $teamId = $team["id"];
            $this->setTeamData($teamId, "isPlaying", true);
        }
        $this->organizeArrays();
    }

    private function teleportToEachSpawn($player)
    {
        $team = $this->getTeamIdFromPlayer($player);
        switch ($team) {
            case 1:
                $data = $this->getConfig()->get("startPoint_1");
                $pos = new Vector3($data["x"], $data["y"], $data["z"]);
                break;
            case 2:
                $data = $this->getConfig()->get("startPoint_2");
                $pos = new Vector3($data["x"], $data["y"], $data["z"]);
                break;
            default:
                $pos = $player->getLevel()->getSpawnLocation();
                break;
        }
        $player->teleport($pos);
    }

    private function end()
    {
        $winnerTeam = "red";
        foreach ($this->participatingPlayers as $p) {
            $p->sendTitle($winnerTeam . "チームの勝利！", "0:0", $fadein = 10, $duration = 3, $fadeout = 60);
            $pos = $p->getLevel()->getSpawnLocation();
            $p->teleport($pos);
            if ($p->getGamemode() != 1) {
                $p->getInventory()->clearAll();
                $p->getInventory()->setArmorItem(1, Item::get(0, 0, 0));
            }
            $p->setNameTag($p->getName());
            $p->setDisplayName($p->getName());
        }
        $this->initTeamData();
        //echo "262\n";
        //var_dump($this->teams);
        $this->organizeArrays();
    }

    private function add($player)
    {
        $team = $this->chooseTeamToJoin();
        //echo "chosenTeamId:$team\n";
        $this->addPlayerToTeam($player, $team);
        $this->organizeArrays();
        return $team;
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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
    }

    private function initTeamData()
    {
        $this->teams = array(
            "Red" => array(
                "name" => "Red",
                "displayName" => "§c赤",
                "id" => 1,
                "players" => array(),
                "isPlaying" => false,
                "isWinner" => false
            ),
            "Blue" => array(
                "name" => "Blue",
                "displayName" => "§b青",
                "id" => 2,
                "players" => array(),
                "isPlaying" => false,
                "isWinner" => false
            ),
        );
    }

    private function getTeamData($id, $key)
    {
        foreach ($this->teams as $team) {
            //echo "312\n";
            //var_dump($team);
            if ($team["id"] == $id) {
                $data = $team[$key];
                break;
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

    public function PlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->getTeamIdFromPlayer($player)) {
            $this->delPlayerFromTeam($player);
        }
    }

    //From API/////////////////////////////////////////////////////////////////////////////////////////////////////////
    /* Quotes from CreateColorArmor_v1.0.1 by vardo@お鳥さん
     * website : https://forum.pmmp.jp/threads/697/
     * Thanks to the auther.
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

    public function getColorByName(String $name)
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
}