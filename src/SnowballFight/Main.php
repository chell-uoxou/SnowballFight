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
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use SnowballFight\TeamManager;

class Main extends PluginBase implements Listener
{

    private $config;
    private $configPath;
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
                        $this->gameStart();
                    }
                    return true;
                    break;

                case "end":
                    if (!$sender->isOp()) {
                        $sender->sendMessage("§cYou don't have permission to use this command.");
                    } else {
                        $this->end();
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
                                    $sender->sendMessage("§fあなたは{$teamName}チーム§fです！\nゲーム開始までしばらくお待ちください！");
                                }
                            }
                        } else {
                            $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                            $sender->sendMessage("§fあなたは既に{$teamName}チーム§fに所属しています！\nゲーム開始までしばらくお待ちください！");
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
                            $sender->sendMessage("あなたはどこのチームにも所属していません！");
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
            $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($p));
            $this->teleportToEachSpawn($p);
            $p->sendTitle("ゲームスタート！", "§fあなたは{$teamName}チーム§fです！", $fadein = 0, $duration = 2, $fadeout = 20);
        }
        foreach ($this->teams as $team) {
            $teamId = $team["id"];
            $this->setTeamData($teamId, "isPlaying", true);
        }
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
}