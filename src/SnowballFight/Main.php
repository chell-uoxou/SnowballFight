<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2017/05/24
 * Time: 午後 4:03
 */
namespace SnowballFight;


use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\Item;

use pocketmine\level\Position;

use pocketmine\math\Vector3;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;

use pocketmine\entity\Snowball;

class Main extends PluginBase implements Listener
{

    private $config;
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

    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info($this->getFullName() . " by chell_uoxou Loaded!");
        $this->getLogger()->info("§c二次配布は厳禁です！");
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
        }
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML,
            array(
                'Prifks' => "SBF",
                'Time' => 60,
                'MinNumOfPeople' => 2,
                'MaxNumOfPeople' => 10,
                'StartPoint_1' => array(
                    'world' => null,
                    'x' => null,
                    'y' => null,
                    'z' => null
                ),
                'StartPoint_2' => array(
                    'world' => null,
                    'x' => null,
                    'y' => null,
                    'z' => null
                ),
                'AllowStatusCommand' => false
            ));
        $getPrifks = $this->config->get("Prifks");
        $this->prifks = "§a[§d{$getPrifks}§a]§f";
        $this->organizeArrays();
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (isset($args[0])) {
            switch (strtolower($args[0])) {
                case "start":
                    if (!$sender->isOp()) {
                        $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                    } else {
                        if ($this->getConfig()->get("MinNumOfPeople") <= count($this->participatingPlayers)) {
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
                    if (!$sender->isOp()) {
                        $sender->sendMessage($this->prifks . "§cYou don't have permission to use this command.");
                    } else {
                        $this->end();
                        return true;
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
                        if ($team) {
                            $this->delPlayerFromTeam($sender);
                            $teamName = $this->getTeamDisplayName($team);
                            $sender->sendMessage($this->prifks . "{$teamName}チーム§fへの参加をキャンセルしました。");
                        } else {
                            $sender->sendMessage($this->prifks . "§cあなたはどこのチームにも所属していません！");
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
        $player->teleport($pos);
    }

///  Game  /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function gameStart()
    {
        foreach ($this->participatingPlayers as $p) {
            $this->joinGame($p);
        }
        foreach ($this->teams as $team) {
            $teamId = $team["id"];
            $this->setTeamData($teamId, "isPlaying", true);
        }
        $this->organizeArrays();
    }

    private function joinGame($p)
    {
        $teamId = $this->getTeamIdFromPlayer($p);
        $teamDisplayName = $this->getTeamDisplayName($teamId);
        $playerName = $p->getName();
        $this->teleportToEachSpawn($p);
        if ($this->isPlaying($teamId)){
            $this->sendMessageInGame($this->prifks . $playerName . "さんが{$teamName}としてゲームに参加しました！");
        }
        if ($p->getGamemode() != 1) {
            $p->setGamemode(0);
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

    public function end($type = NULL)
    {
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
                if ($point1 = "" and $point2 = "") {
                    $point1 = 0;
                    $point2 = 0;
                }
                $gameResult = "§a引き分け";
                break;
        }
        $winLoseRatio = $this->getTeamColor(1) . "$point1 §f: " . $this->getTeamColor(2) . "$point2 §f";

        foreach ($this->participatingPlayers as $p) {
            if (!isset($type)) {
                $p->sendTitle($gameResult . "！", $winLoseRatio, $fadein = 10, $duration = 3, $fadeout = 60);
                $this->getServer()->broadcastMessage($this->prifks . "§aゲーム終了、{$winLoseRatio}で{$gameResult}です。");
            } else {
                switch ($type) {
                    case "too little":
                        $p->sendTitle("§c対戦相手がいません！", $winLoseRatio . "で" . $gameResult . "§fです。", $fadein = 10, $duration = 3, $fadeout = 60);
                        $this->getServer()->broadcastMessage($this->prifks . "§6ゲームの最小参加人数を下回ったためゲームを終了しました。");
                        $this->getServer()->broadcastMessage($this->prifks . "終了段階の試合結果は、{$winLoseRatio}で{$gameResult}です。");
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
            }
        }

        $this->initTeamData();
        //echo "262\n";
        //var_dump($this->teams);
        $this->organizeArrays();
    }

    public function EntityDamage(EntityDamageEvent $event)
    {
        $cause = $event->getCause();
        if (method_exists($event, "getDamager")) {
            $damager = $event->getDamager();
            $damaged = $event->getEntity();
            if ($cause == EntityDamageEvent::CAUSE_PROJECTILE and substr($event->getChild(), 0, 4) == "Snow") {
                $damagerDisplayName = $damager->getNameTag();

                if (!$this->isOnlinePlayer($damagerDisplayName)) {
                    $damagerTTLonger = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $damagerDisplayName)) + 5;
                    $damagerName = mb_substr($damagerDisplayName, $damagerTTLonger);
                } else {
                    $damagerName = $damagerDisplayName;
                }
                $damagedPlayerDisplayName = $damaged->getNameTag();
                if (!$this->isOnlinePlayer($damagedPlayerDisplayName)) {
                    $damagedPlayerTTLonger = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $damagedPlayerDisplayName)) + 5;
                    $damagedPlayerName = mb_substr($damagedPlayerDisplayName, $damagedPlayerTTLonger);
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

    public function sendMessageInGame($message)
    {
        foreach ($this->participatingPlayers as $player) {
            $player->sendMessage($message);
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
        if ($this->isPlaying(1) and count($this->participatingPlayers) <= 1) {
            $this->end("too little");
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

    private function getTeamColor($id)
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
            return false;
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

    public function PlayerJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if (!$player->isOp()) {
            $player->getInventory()->clearAll();
            $player->setGamemode(2);
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