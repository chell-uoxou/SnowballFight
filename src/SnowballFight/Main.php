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
use pocketmine\math\Vector3;
use SnowballFight\TeamManager;

class Main extends PluginBase implements Listener
{
    private $teamManager;

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
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (isset($args[0])){
            switch (strtolower($args[0])) {
                case "start":
                    $this->start();
                    return true;
                    break;

                case "end":
                    $this->end();
                    return true;
                    break;

                case "time":
                    $this->start();
                    return true;
                    break;

                case "addpl":
                    $this->start();
                    return true;
                    break;

                case "join":
                    if ($sender instanceof Player){
                        if (!$this->getTeamIdFromPlayer($sender)){
                            $team = $this->add($sender);
                            if($team){
                                $teamName = $this->getTeamDisplayName($team);
                                $sender->sendMessage("§fあなたは{$teamName}チーム§fです！");
                            }
                        }else{
                            $teamName = $this->getTeamDisplayName($this->getTeamIdFromPlayer($sender));
                            $sender->sendMessage("§fあなたは既に{$teamName}チーム§fに所属しています！");
                        }
                    }else{
                        $sender->sendMessage("§cPlease run this command in-game.");
                    }
                    return true;
                    break;

                case "cancel":
                    $team = $this->getTeamIdFromPlayer($sender);
                    if ($team){
                        $this->delPlayerFromTeam($sender);
                        $teamName = $this->getTeamDisplayName($team);
                        $sender->sendMessage("{$teamName}チーム§fへの参加をキャンセルしました。");
                    }else{
                        $sender->sendMessage("あなたはどこのチームにも所属していません！");
                    }
                    return true;
                    break;
                default:
                    return false;
            }
        }else{
            return false;
        }
    }

    private function start()
    {
        $this->getServer()->broadcastMessage('開始！');
    }

    private function end(){
        $winnerTeam = "red";
        foreach ($this->participatingPlayers as $p) {
            $p->sendTitle($winnerTeam . "チームの勝利！", "0:0", $fadein = 20, $duration = 5, $fadeout = 20);
        }

        foreach ($this->teams as $team) {
            $key = $team["name"];
            $this->teams[$key]["players"] = array();
        }
        $this->organizeArrays();
    }

    private function add($player){
        $team = $this->chooseTeamToJoin();
        $this->addPlayerToTeam($player, $team);
        return $team;
    }


    private function organizeArrays()
    {
        $this->participatingPlayers = array();
        foreach ($this->teams as $team) {
            foreach ($team["players"] as $player)
            $this->participatingPlayers[] = $player;
        }
    }

    private function getTeamData($id, $column)
    {
        foreach ($this->teams as $team) {
            if ($team["id"] == $id) {
                $data = $team[$column];
                break;
            }
        }
        if (!isset($data)) {
            return false;
        } else {
            return $data;
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

    public function addPlayerToTeam($player, $teamId){
        $this->teams[$this->getTeamName($teamId)]["players"][] = $player;
        $this->organizeArrays();
    }

    public function delPlayerFromTeam($player){
        $teamId = $this->getTeamIdFromPlayer($player);
        $arrayNum = array_search($player,$this->teams[$this->getTeamName($teamId)]["players"]);
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

    public function chooseTeamToJoin()
    {
        $pc1 = $this->getTeamPlayersCount(1);
        $pc2 = $this->getTeamPlayersCount(2);

        switch (true) {
            case $pc1 == $pc2:
                return rand(1, 2);
                break;

            case $pc1 > $pc2:
                return 2;
                break;

            case $pc2 < $pc1:
                return 1;
                break;

            default:
                return false;
        }

    }

    public function PlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $this->delPlayerFromTeam($player);
    }
}