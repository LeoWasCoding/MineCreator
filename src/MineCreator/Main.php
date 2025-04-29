<?php

namespace MineCreator;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\item\StringToItemParser;
use pocketmine\block\Air;
use pocketmine\scheduler\Task;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {

    /** @var array<string,bool> */
    private array $selectionMode = [];
    /** @var array<string,Vector3> */
    private array $firstPosition = [];
    /** @var array<string,Vector3> */
    private array $secondPosition = [];
    
    private ?\jojoe77777\FormAPI\FormAPI $formapi = null;
    private Config $mines;
    /** @var array<string,bool> */
    public array $pendingEmptyResets = []; // made public

    /** @var bool Whether to broadcast the 5s reset warning */
    private bool $warnEnabled = true;
    /** Public accessor for anonymous Tasks */
    public function isWarnEnabled(): bool {
        return $this->warnEnabled;
    }
    


    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    
        // Load FormAPI
        $this->formapi = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        if ($this->formapi === null) {
            $this->getLogger()->error("FormAPI (jojoe77777) not found — disabling.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    
        // Load mines.json
        $this->mines = new Config($this->getDataFolder() . "mines.json", Config::JSON, []);
    
        // Schedule periodic auto-resets for existing mines
        foreach ($this->mines->getAll() as $name => $data) {
            $interval = (int)($data["autoResetTime"] ?? 0);
            if ($interval > 0) {
                $this->schedulePeriodicReset($name, $interval);
            }
        }
    }
    

    public function onDisable(): void {
        $this->mines->save();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        // Only players in-game
        if (!$sender instanceof Player) {
            $sender->sendMessage("Use in-game!");
            return true;
        }
    
        $name = $cmd->getName(); // e.g. "mine" or "minewarn"
        $sub  = isset($args[0]) ? strtolower($args[0]) : "";
    
        // === /minewarn <on|off> ===
        if ($name === "minewarn") {
            if (!$sender->hasPermission("minecreator.command.minewarn")) {
                $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cYou don't have permission to toggle mine warnings.");
                return true;
            }
            if (count($args) !== 1 || !in_array($sub, ["on", "off"], true)) {
                $sender->sendMessage("§7[§l§dMine§r§7] §c>> §eUsage: §b/minewarn <on|off>");
                return true;
            }
            $this->warnEnabled = ($sub === "on");
            $sender->sendMessage(
                $this->warnEnabled
                    ? "§7[§l§dMine§r§7] §c>> §aMine reset warnings §benabled§a."
                    : "§7[§l§dMine§r§7] §c>> §cMine reset warnings §bdisabled§c."
            );
            return true;
        }
    
        // === /mine <subcommand> ===
        if ($name === "mine") {
            // Help menu when no args or '/mine help'
            if ($sub === "" || $sub === "help") {
                $sender->sendMessage("§6§l===== §eMineCreator Help §6§l=====");
                $sender->sendMessage("§e/mine help §7– Show this help menu.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine list §7– List all existing mines.");
                $sender->sendMessage("   §8Shows you every mine by name.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine position §7– Start defining a new mine region.");
                $sender->sendMessage("   §8Break one block for the §bfirst corner§8, then another for the §bsecond corner§8.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine create §7– Open the Create Mine form.");
                $sender->sendMessage("   §8After selecting positions, this lets you:");
                $sender->sendMessage("   §8 • Name your mine (e.g. §bstone_mine§8)");
                $sender->sendMessage("   §8 • Choose blocks & percentages (e.g. §cstone,50,iron_ore,30§8)");
                $sender->sendMessage("   §8 • Set auto-reset interval in seconds (e.g. §b600§8)");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine edit <name> §7– Edit an existing mine via form.");
                $sender->sendMessage("   §8Change its name, blocks, or auto-reset time.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine reset <name> §7– Immediately reset a mine.");
                $sender->sendMessage("   §8Teleports anyone inside up above, refills the region.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine delete <name> §7– Permanently delete a mine.");
                $sender->sendMessage("   §8Removes it from config and cancels auto-resets.");
                $sender->sendMessage("");
    
                $sender->sendMessage("§e/mine reload §7– Reload this plugin.");
                $sender->sendMessage("   §8Disables and re-enables MineCreator, reloading all settings.");
                $sender->sendMessage("§6§l===============================");
                return true;
            }
    
            // Proceed with other /mine subcommands
            switch ($sub) {
                case "position":
                    $this->selectionMode[$sender->getName()] = true;
                    $sender->sendMessage("§7[§l§dMine§r§7] §c>> §aBreak one block for §bFirst§a pos, then break another for §bSecond§a pos.");
                    break;
    
                case "create":
                    if (!isset($this->firstPosition[$sender->getName()], $this->secondPosition[$sender->getName()])) {
                        $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cSelect two positions first with §e/mine position§c!");
                        return true;
                    }
                    $this->openCreateForm($sender);
                    break;
    
                case "edit":
                    if (isset($args[1])) {
                        $mineName = strtolower($args[1]);
                        if (!$this->mines->exists($mineName)) {
                            $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cMine '$mineName' not found!");
                            return true;
                        }
                        $this->openEditForm($sender, $mineName);
                    } else {
                        $this->openMineListForm($sender, "edit");
                    }
                    break;
    
                case "reset":
                    if (isset($args[1])) {
                        $mineName = strtolower($args[1]);
                        if (!$this->mines->exists($mineName)) {
                            $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cMine '$mineName' not found!");
                            return true;
                        }
                        $this->resetMineByName($mineName);
                        if ($this->warnEnabled) {
                            $sender->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '$mineName' has been reset.");
                        }
                    } else {
                        $this->openMineListForm($sender, "reset");
                    }
                    break;
    
                case "list":
                    $mineNames = array_keys($this->mines->getAll());
                    if (empty($mineNames)) {
                        $sender->sendMessage("§7[§l§dMine§r§7] §c>> §eNo mines have been created yet.");
                    } else {
                        $sender->sendMessage("§7[§l§dMine§r§7] §c>> §aAvailable Mines:");
                        foreach ($mineNames as $mine) {
                            $sender->sendMessage("§7[§l§dMine§r§7] §c>> §b- $mine");
                        }
                    }
                    break;
    
                case "delete":
                    if (isset($args[1])) {
                        $mineName = strtolower($args[1]);
                        if (!$this->mines->exists($mineName)) {
                            $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cMine '$mineName' not found!");
                            return true;
                        }
                        $this->deleteMine($sender, $mineName);
                    } else {
                        $this->openMineListForm($sender, "delete");
                    }
                    break;
    
                case "reload":
                    if (!$sender->hasPermission("minecreator.command.reload")) {
                        $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cYou don't have permission to reload.");
                        return true;
                    }
                    $pm = $this->getServer()->getPluginManager();
                    $pm->disablePlugin($this);
                    $pm->enablePlugin($this);
                    $sender->sendMessage("§7[§l§dMine§r§7] §c>> §aMineCreator has been reloaded!");
                    break;
    
                default:
                    $sender->sendMessage("§7[§l§dMine§r§7] §c>> §cUnknown subcommand. Type §e/mine help §cfor a list of commands.");
            }
    
            return true;
        }
    
        // If for some reason neither /mine nor /minewarn
        return false;
    }
    
    
    
    

    public function getMineData(string $name): ?array {
        $data = $this->mines->get($name);
        return is_array($data) ? $data : null;
    }
    
    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $message = strtolower(trim($event->getMessage()));
    
        if ($message === "cancel") {
            if (isset($this->selectionMode[$name]) || isset($this->firstPosition[$name]) || isset($this->secondPosition[$name])) {
                unset($this->selectionMode[$name], $this->firstPosition[$name], $this->secondPosition[$name]);
                $player->sendMessage("§7[§l§dMine§r§7] §c>> §6Selection cancelled. You can start again.");
                $event->cancel(); // prevent "cancel" from appearing in chat
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $p    = $event->getPlayer();
        $name = $p->getName();
    
        // First position selection
        if (isset($this->selectionMode[$name]) && !isset($this->firstPosition[$name])) {
            $this->firstPosition[$name] = $event->getBlock()->getPosition();
            $p->sendMessage("§7[§l§dMine§r§7] §c>> §aFirst position set at " . $event->getBlock()->getPosition());
            $event->cancel();
            return;
        }
    
        // Empty-region detection
        $pos       = $event->getBlock()->getPosition();
        $worldName = $pos->getWorld()->getFolderName();
        foreach ($this->mines->getAll() as $mineName => $data) {
            if ($worldName !== $data["world"]) continue;
            $p1 = new Vector3(...$data["pos1"]);
            $p2 = new Vector3(...$data["pos2"]);
            if ($this->isInside($pos, $p1, $p2)) {
                $this->getScheduler()->scheduleDelayedTask(
                    new class($this, $mineName) extends Task {
                        public function __construct(private Main $plugin, private string $mineName) {}
                        public function onRun(): void {
                            // Only trigger once per empty region
                            if ($this->plugin->isRegionEmpty($this->mineName) && empty($this->plugin->pendingEmptyResets[$this->mineName])) {
                                $this->plugin->pendingEmptyResets[$this->mineName] = true;
    
                                // --- START: broadcast 5s warning if enabled ---
                                if ($this->plugin->isWarnEnabled()) {
                                    $mineData = $this->plugin->getMineData($this->mineName);
                                    if ($mineData !== null) {
                                        $world = $this->plugin->getServer()
                                            ->getWorldManager()
                                            ->getWorldByName($mineData["world"]);
                                        if ($world instanceof World) {
                                            foreach ($world->getPlayers() as $player) {
                                                $player->sendMessage("§7[§l§dMine§r§7] §c>> §6Mine '{$this->mineName}' will reset in 5 seconds!");
                                            }
                                        }
                                    }
                                }
                                // --- END: broadcast 5s warning ---
    
                                // schedule actual reset in 5 seconds
                                $this->plugin->getScheduler()->scheduleDelayedTask(
                                    new class($this->plugin, $this->mineName) extends Task {
                                        public function __construct(private Main $plugin, private string $mineName) {}
                                        public function onRun(): void {
                                            $this->plugin->resetMineByName($this->mineName);
                                            $this->plugin->pendingEmptyResets[$this->mineName] = false;
                                        }
                                    },
                                    5 * 20
                                );
                            }
                        }
                    },
                    1
                );
                break;
            }
        }
    }
    
    
    

    public function onPlayerInteract(BlockBreakEvent $event): void {
        $p    = $event->getPlayer();
        $name = $p->getName();

        // Second position selection
        if(isset($this->selectionMode[$name], $this->firstPosition[$name]) && !isset($this->secondPosition[$name])){
            $this->secondPosition[$name] = $event->getBlock()->getPosition();
            unset($this->selectionMode[$name]);
            $p->sendMessage("§7[§l§dMine§r§7] §c>> §aSecond position set at " . $event->getBlock()->getPosition());
            $p->sendMessage("§7[§l§dMine§r§7] §c>> §bType §l§cCancel §r§bTo Start Over Or §l§e/mine create §r§bTo Create The Mine");
            $event->cancel();
        }
    }

    private function openCreateForm(Player $player): void {
        $form = new CustomForm(function(Player $p, ?array $data){
            if($data === null) return;
            [$rawName, $rawBlocks, $rawTime] = $data;
    
            $name      = strtolower(trim($rawName));  // Ensure the name is lowercase
            $blocks    = $this->parseBlocksInput($rawBlocks);
            $resetTime = max(0, (int)$rawTime);
    
            if($name === "" || empty($blocks)){
                $p->sendMessage("§7[§l§dMine§r§7] §c>> §cInvalid name or block list!");
                return;
            }
    
            // Check if a mine with the same name already exists
            if($this->mines->exists($name)){
                $p->sendMessage("§7[§l§dMine§r§7] §c>> §cA mine with the name '$name' already exists. Please choose a different name.");
                return;
            }
    
            $p1 = $this->firstPosition[$p->getName()];
            $p2 = $this->secondPosition[$p->getName()];
            $this->mines->set($name, [
                "world"         => $p->getWorld()->getFolderName(),
                "pos1"          => [$p1->getX(), $p1->getY(), $p1->getZ()],
                "pos2"          => [$p2->getX(), $p2->getY(), $p2->getZ()],
                "blocks"        => $blocks,
                "autoResetTime" => $resetTime
            ]);
            $this->mines->save();
    
            $this->fillArea($p->getWorld(), $p1, $p2, $blocks);
            if($resetTime > 0){
                $this->schedulePeriodicReset($name, $resetTime);
            }
    
            $p->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '$name' created!");
            if (isset($this->selectionMode[$name]) || isset($this->firstPosition[$name]) || isset($this->secondPosition[$name])) {
                unset($this->selectionMode[$name], $this->firstPosition[$name], $this->secondPosition[$name]);
            }
        });
    
        $form->setTitle("Create Mine");
        $form->addInput("Mine Name", "e.g. stone_mine");
        $form->addInput("Blocks", "stone,50,iron_ore,30");
        $form->addInput("Auto-reset time (sec)", "600");
        $player->sendForm($form);
    }
    

    private function openEditForm(Player $player, string $mineName): void {
        $data      = $this->mines->get($mineName);
        $blocksCsv = implode(",", array_map(
            fn($b, $pct) => "{$b},{$pct}%",
            array_keys($data["blocks"]), array_values($data["blocks"])
        ));
        $time = (int) $data["autoResetTime"];
    
        $form = new CustomForm(function(Player $p, ?array $dataIn) use ($mineName, $data) {
            if ($dataIn === null) return;
    
            // Expecting 3 inputs now: name, blocks, time
            [$newName, $rawBlocks, $rawTime] = $dataIn;
    
            $newName = trim((string)$newName);
            $blocks = $this->parseBlocksInput($rawBlocks);
            $resetTime = max(0, (int)$rawTime);
    
            if ($newName === "" || empty($blocks)) {
                $p->sendMessage("§7[§l§dMine§r§7] §c>> §cInvalid name or block list!");
                return;
            }
    
            // Check for duplicate name if changed
            if (strtolower($newName) !== strtolower($mineName) && $this->mines->exists($newName)) {
                $p->sendMessage("§7[§l§dMine§r§7] §c>> §cA mine with the name '$newName' already exists!");
                return;
            }
    
            // Update mine data
            $newData = $data;
            $newData["blocks"] = $blocks;
            $newData["autoResetTime"] = $resetTime;
    
            if (strtolower($newName) !== strtolower($mineName)) {
                $this->mines->remove($mineName);
            }
            $this->mines->set($newName, $newData);
            $this->mines->save();
    
            $p->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '$mineName' updated to '$newName'!");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            $this->getServer()->getPluginManager()->enablePlugin($this);
        });
    
        $form->setTitle("Edit Mine: $mineName");
        $form->addInput("Mine Name", "e.g. stone_mine", $mineName);   // New field: editable name
        $form->addInput("Blocks", "e.g. stone,50,iron_ore,30", $blocksCsv);
        $form->addInput("Auto-reset time (sec)", "e.g. 600", (string)$time);
        $player->sendForm($form);
    }
    

    private function openMineListForm(Player $player, string $mode): void {
        $form = new SimpleForm(function(Player $p, ?int $idx) use($mode){
            if($idx === null) return;
            $names = array_keys($this->mines->getAll());
            if(!isset($names[$idx])) return;
            $mine = $names[$idx];
    
            if($mode === "edit"){
                $this->openEditForm($p, $mine);
            } elseif($mode === "reset"){
                $this->resetMineByName($mine);
                $p->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '$mine' reset.");
            } elseif($mode === "delete"){
                $this->deleteMine($p, $mine);
            }
        });
    
    
        $form->setTitle("Select Mine to " . ucfirst($mode));
        foreach(array_keys($this->mines->getAll()) as $mine){
            $form->addButton($mine);
        }
        $player->sendForm($form);
    }
    
    private function deleteMine(Player $player, string $mineName): void {
        if(!$this->mines->exists($mineName)){
            $player->sendMessage("§7[§l§dMine§r§7] §c>> §cMine '$mineName' does not exist.");
            return;
        }
    
        $this->mines->remove($mineName);
        $this->mines->save();
        unset($this->pendingEmptyResets[$mineName]);
        $player->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '$mineName' deleted successfully.");
        $this->getServer()->getPluginManager()->disablePlugin($this);
        $this->getServer()->getPluginManager()->enablePlugin($this);
    }
    

    private function schedulePeriodicReset(string $name, int $intervalSec): void {
        $this->getScheduler()->scheduleRepeatingTask(
            new class($this, $name, $intervalSec) extends Task {
                public function __construct(private Main $plugin, private string $mineName, private int $intervalSec) {}
    
                public function onRun(): void {
                    // Send 5-second warning
                    if ($this->plugin->isWarnEnabled()) {
                        $mineData = $this->plugin->getMineData($this->mineName);
                        if ($mineData !== null) {
                            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($mineData["world"]);
                            if ($world instanceof World) {
                                foreach ($world->getPlayers() as $player) {
                                    $player->sendMessage("§7[§l§dMine§r§7] §c>> §6Mine '{$this->mineName}' will reset in 5 seconds!");
                                }
                            }
                        }
                    }
    
                    // Schedule actual reset after 5 seconds
                    $this->plugin->getScheduler()->scheduleDelayedTask(
                        new class($this->plugin, $this->mineName) extends Task {
                            public function __construct(private Main $plugin, private string $mineName) {}
    
                            public function onRun(): void {
                                $this->plugin->resetMineByName($this->mineName);
                            }
                        },
                        5 * 20
                    );
                }
            },
            $intervalSec * 20
        );
    }
    
    

    public function resetMineByName(string $name): void {
        $data = $this->mines->get($name);
        if (!is_array($data)) {
            $this->getLogger()->warning("Mine '$name' not found in config.");
            return;
        }
    
        $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
        if (!$world instanceof World) return;
    
        $p1   = new Vector3(...$data["pos1"]);
        $p2   = new Vector3(...$data["pos2"]);
        $topY = max($p1->getY(), $p2->getY()) + 1;
    
        // Teleport any players inside up above, and notify them individually
        foreach ($world->getPlayers() as $player) {
            $pos = $player->getPosition();
            if ($this->isInside($pos, $p1, $p2)) {
                $player->teleport(new Vector3($pos->getX(), $topY, $pos->getZ()));
                $player->sendMessage("§7[§l§dMine§r§7] §c>> §eYou have been teleported above the mine due to a reset.");
            }
        }
    
        // Broadcast the reset confirmation only if warnings are enabled
        if ($this->warnEnabled) {
            foreach ($world->getPlayers() as $player) {
                $player->sendMessage("§7[§l§dMine§r§7] §c>> §aMine '{$name}' has been reset!");
            }
        }
    
        $this->fillArea($world, $p1, $p2, $data["blocks"]);
    }
    
    
    
    

    private function fillArea(World $world, Vector3 $p1, Vector3 $p2, array $blocks): void {
        $minX = min($p1->getX(), $p2->getX());
        $maxX = max($p1->getX(), $p2->getX());
        $minY = min($p1->getY(), $p2->getY());
        $maxY = max($p1->getY(), $p2->getY());
        $minZ = min($p1->getZ(), $p2->getZ());
        $maxZ = max($p1->getZ(), $p2->getZ());

        for($x = $minX; $x <= $maxX; $x++){
            for($y = $minY; $y <= $maxY; $y++){
                for($z = $minZ; $z <= $maxZ; $z++){
                    $name = $this->getRandomBlock($blocks);
                    $item = StringToItemParser::getInstance()->parse($name);
                    if($item === null) continue;
                    $block = $item->getBlock();
                    $world->setBlock(new Vector3($x, $y, $z), $block, false, false);
                }
            }
        }
    }

    private function parseBlocksInput(string $input): array {
        $parts = array_map('trim', explode(",", $input));
        $out   = [];
        for($i = 0; $i + 1 < count($parts); $i += 2){
            $b = strtolower($parts[$i]);
            $p = (int)rtrim($parts[$i+1], "%");
            if($b !== "" && $p > 0) $out[$b] = $p;
        }
        return $out;
    }

    private function getRandomBlock(array $blocks): string {
        $r   = mt_rand(1, array_sum($blocks));
        $acc = 0;
        foreach($blocks as $name => $pct){
            $acc += $pct;
            if($r <= $acc) return $name;
        }
        return array_key_first($blocks);
    }

    private function isInside(Vector3 $pos, Vector3 $p1, Vector3 $p2): bool {
        return
            $pos->getX() >= min($p1->getX(), $p2->getX()) &&
            $pos->getX() <= max($p1->getX(), $p2->getX()) &&
            $pos->getY() >= min($p1->getY(), $p2->getY()) &&
            $pos->getY() <= max($p1->getY(), $p2->getY()) &&
            $pos->getZ() >= min($p1->getZ(), $p2->getZ()) &&
            $pos->getZ() <= max($p1->getZ(), $p2->getZ());
    }

    public function isRegionEmpty(string $name): bool {
        $data  = $this->mines->get($name);
        $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
        if(!$world instanceof World) return false;
        $p1 = new Vector3(...$data["pos1"]);
        $p2 = new Vector3(...$data["pos2"]);

        for($x = min($p1->getX(), $p2->getX()); $x <= max($p1->getX(), $p2->getX()); $x++){
            for($y = min($p1->getY(), $p2->getY()); $y <= max($p1->getY(), $p2->getY()); $y++){
                for($z = min($p1->getZ(), $p2->getZ()); $z <= max($p1->getZ(), $p2->getZ()); $z++){
                    if(!$world->getBlockAt($x, $y, $z) instanceof Air){
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
