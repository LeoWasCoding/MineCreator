<?php

namespace MineCreator;

// ── Core ──
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

// ── Blocks & Items ──
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Air;
use pocketmine\item\StringToItemParser;

// ── Events ──
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerChatEvent;

// ── World & Math ──
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\color\Color;

// ── Scheduler ──
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;

// ── Particles & Sounds ──
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\sound\Sound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\world\sound\ExplodeSound;

// ── External Libraries ──
use MineCreator\libs\jojoe77777\FormAPI\SimpleForm;
use MineCreator\libs\jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {

    /** @var array<string,bool> */
    private array $selectionMode = [];
    /** @var array<string,Vector3> */
    private array $firstPosition = [];
    /** @var array<string,Vector3> */
    private array $secondPosition = [];
    
    private Config $mines;
    /** @var array<string,bool> */
    public array $pendingEmptyResets = [];
    private bool $warnEnabled = true;
    
    /** @phpstan-ignore-next-line */
    /** @var TaskHandler[] */
    private array $scheduledTasks = [];

    private Config $luckyBlocksConfig;

    private Config $messages;

    private LuckyBlockManager $luckyBlockManager;

    public function isWarnEnabled(): bool {
        return $this->warnEnabled;
    }

    public function getLuckyBlockManager(): LuckyBlockManager {
        return $this->luckyBlockManager;
    }

    public function getMessage(string $key): string {
        return $this->messages->get($key, "");
    }    

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveResource("luckyblock.yml");
        $this->saveResource("messages.yml");
        $this->luckyBlocksConfig = new Config($this->getDataFolder() . "luckyblock.yml", Config::YAML);
        $this->luckyBlockManager = new LuckyBlockManager($this->getDataFolder());
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->mines = new Config($this->getDataFolder() . "mines.json", Config::JSON, []);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->messages->get("use_in_game"));
            return true;
        }
    
        $name = $cmd->getName(); 
        $sub  = isset($args[0]) ? strtolower($args[0]) : "";
    
        if ($name === "minewarn") {
            if (!$sender->hasPermission("minecreator.command.minewarn")) {
                $sender->sendMessage($this->messages->get("no_permission_minewarn"));
                return true;
            }
            if (count($args) !== 1 || !in_array($sub, ["on", "off"], true)) {
                $sender->sendMessage($this->messages->get("usage_minewarn"));
                return true;
            }
            $this->warnEnabled = ($sub === "on");
            if ($this->warnEnabled) {
                $sender->sendMessage($this->messages->get("minewarn_enabled"));
            } else {
                $sender->sendMessage($this->messages->get("minewarn_disabled"));
            }
            return true;
        }

        if ($name === "mine") {
            if ($sub === "" || $sub === "help") {
                $sender->sendMessage($this->messages->get("mine_help_header"));
                $sender->sendMessage($this->messages->get("mine_help_item1"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_list"));
                $sender->sendMessage($this->messages->get("mine_help_list_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_pos"));
                $sender->sendMessage($this->messages->get("mine_help_pos_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_create"));
                $sender->sendMessage($this->messages->get("mine_help_create_desc"));
                foreach ($this->messages->get("mine_help_create_bullets") as $bullet) {
                    $sender->sendMessage($bullet);
                }
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_edit"));
                $sender->sendMessage($this->messages->get("mine_help_edit_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_reset"));
                $sender->sendMessage($this->messages->get("mine_help_reset_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_delete"));
                $sender->sendMessage($this->messages->get("mine_help_delete_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_setblockxp"));
                $sender->sendMessage($this->messages->get("mine_help_setblockxp_desc"));
                $sender->sendMessage("");

                // NEW: blockdrop command help
                $sender->sendMessage($this->messages->get("mine_help_blockdrop"));
                $sender->sendMessage($this->messages->get("mine_help_blockdrop_desc"));
                $sender->sendMessage("");
                
                // NEW: silentreset command help ;>
                $sender->sendMessage($this->messages->get("mine_help_silentreset"));
                $sender->sendMessage($this->messages->get("mine_help_silentreset_desc"));
                $sender->sendMessage("");
                
                // NEW: minewarn command help
                $sender->sendMessage($this->messages->get("mine_help_minewarn"));
                $sender->sendMessage($this->messages->get("mine_help_minewarn_desc"));
                $sender->sendMessage("");

                $sender->sendMessage($this->messages->get("mine_help_reload"));
                $sender->sendMessage($this->messages->get("mine_help_reload_desc"));
                $sender->sendMessage($this->messages->get("mine_help_footer"));
                return true;
            }
    
            if ($sub === 'setblockxp') {
                if (count($args) !== 4) {
                    $sender->sendMessage($this->messages->get("usage_setblockxp"));
                    return true;
                }
                [$_, $mineName, $blockInput, $xpRaw] = $args;
                $mineName = strtolower($mineName);
                // parse block name via parser
                $item = StringToItemParser::getInstance()->parse(strtolower($blockInput));
                if ($item === null || $item->isNull()) {
                    $sender->sendMessage(str_replace("{block}", $blockInput, $this->messages->get("unknown_block")));
                    return true;
                }
                // get alias key
                $aliases = StringToItemParser::getInstance()->lookupBlockAliases($item->getBlock());
                $alias   = strtolower(ltrim(array_shift($aliases), 'minecraft:'));
                $xp      = max(0, (int)$xpRaw);
                if (!$this->mines->exists($mineName)) {
                    $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
                    return true;
                }
                $data = $this->mines->get($mineName);
                $xpMap = $data['blockXp'] ?? [];
                $xpMap[$alias] = $xp;
                $data['blockXp'] = $xpMap;
                $this->mines->set($mineName, $data);
                $this->mines->save();
                $sender->sendMessage(
                    str_replace(
                        ["{xp}", "{alias}", "{mine}"],
                        [$xp, $alias, $mineName],
                        $this->messages->get("xp_set_success")
                    )
                );
                return true;
            }

            if ($sub === 'blockdrop') {
                if (count($args) !== 4) {
                    $sender->sendMessage($this->messages->get("usage_blockdrop"));
                    return true;
                }
                [$_, $mineName, $blockInput, $toggle] = $args;
                $mineName = strtolower($mineName);
                $item = StringToItemParser::getInstance()->parse(strtolower($blockInput));
                if ($item === null || $item->isNull()) {
                    $sender->sendMessage(str_replace("{block}", $blockInput, $this->messages->get("unknown_block")));
                    return true;
                }

                $toggle = strtolower($toggle);
                if (!in_array($toggle, ["true", "false"], true)) {
                    $sender->sendMessage($this->messages->get("usage_blockdrop"));
                    return true;
                }

                $block = $item->getBlock();
                $aliases = StringToItemParser::getInstance()->lookupBlockAliases($block);
                $alias = strtolower(ltrim(array_shift($aliases), 'minecraft:'));

                if (!$this->mines->exists($mineName)) {
                    $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
                    return true;
                }

                $data = $this->mines->get($mineName);
                $dropMap = $data["blockDrop"] ?? [];
                $dropMap[$alias] = $toggle === "true";
                $data["blockDrop"] = $dropMap;
                $this->mines->set($mineName, $data);
                $this->mines->save();

                $sender->sendMessage(
                    str_replace(
                        ["{block}", "{status}", "{mine}"],
                        [$alias, $toggle, $mineName],
                        $this->messages->get("block_drop_toggle_success")
                    )
                );
                return true;
            }

            switch ($sub) {
                case "position":
                    $name = $sender->getName();
                    $sender->sendMessage($this->messages->get("prompt_select_first"));
                    if (isset($this->selectionMode[$name]) || isset($this->firstPosition[$name]) || isset($this->secondPosition[$name])) {
                        unset($this->selectionMode[$name], $this->firstPosition[$name], $this->secondPosition[$name]);
                    }
                    $this->selectionMode[$sender->getName()] = true;
                    break;
    
                case "create":
                    if (!isset($this->firstPosition[$sender->getName()], $this->secondPosition[$sender->getName()])) {
                        $sender->sendMessage($this->messages->get("must_select_positions"));
                        return true;
                    }
                    $this->openCreateForm($sender);
                    break;
    
                case "edit":
                    if (isset($args[1])) {
                        $mineName = strtolower($args[1]);
                        if (!$this->mines->exists($mineName)) {
                            $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
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
                            $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
                            return true;
                        }
                        $this->resetMineByName($mineName);
                        if ($this->warnEnabled) {
                            $sender->sendMessage(
                                str_replace("{mine}", $mineName, $this->messages->get("mine_reset_complete"))
                            );
                        }
                    } else {
                        $this->openMineListForm($sender, "reset");
                    }
                    break;
    
                case "list":
                    $mineNames = array_keys($this->mines->getAll());
                    if (empty($mineNames)) {
                        $sender->sendMessage($this->messages->get("no_mines_exist"));
                    } else {
                        $sender->sendMessage($this->messages->get("available_mines"));
                        foreach ($mineNames as $mine) {
                            $sender->sendMessage(
                                str_replace("{mine}", $mine, $this->messages->get("mine_list_entry"))
                            );
                        }
                    }
                    break;
    
                case "delete":
                    if (isset($args[1])) {
                        $mineName = strtolower($args[1]);
                        if (!$this->mines->exists($mineName)) {
                            $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
                            return true;
                        }
                        $this->deleteMine($sender, $mineName);
                    } else {
                        $this->openMineListForm($sender, "delete");
                    }
                    break;
    
                case "reload":
                    if (!$sender->hasPermission("minecreator.command.reload")) {
                        $sender->sendMessage($this->messages->get("no_permission_reload"));
                        return true;
                    }
                    $pm = $this->getServer()->getPluginManager();
                    $pm->disablePlugin($this);
                    $pm->enablePlugin($this);
                    $sender->sendMessage($this->messages->get("reload_success"));
                    break;

                case "silentreset":
                    if (count($args) < 3) {
                        $sender->sendMessage("§cUsage: /mine silentreset <mine> <true|false>");
                        return true;
                    }

                    $mineName = $args[1];
                    $value = strtolower($args[2]) === "true";

                    if (!$this->mines->exists($mineName)) {
                        $sender->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
                        return true;
                    }

                    $this->setSilentReset($mineName, $value);
                    $status = $value ? "enabled" : "disabled";
                    $sender->sendMessage("§aSilent reset $status for mine '$mineName'.");
                    return true;
    
                default:
                    $sender->sendMessage($this->messages->get("generic_unknown"));
            }
    
            return true;
        }

        return false;
    }
    
    public function isSilentReset(string $mineName): bool {
        $data = $this->mines->get($mineName);
        return isset($data["silent_reset"]) && $data["silent_reset"] === true;
    }

    public function setSilentReset(string $mineName, bool $silent): void {
        $data = $this->mines->get($mineName);
        if (!is_array($data)) return;
        $data["silent_reset"] = $silent;
        $this->mines->set($mineName, $data);
        $this->mines->save();
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
                $player->sendMessage($this->messages->get("selection_cancelled"));
                $event->cancel(); 
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player     = $event->getPlayer();
        $playerName = $player->getName();

        // ── Region-selection logic ──
        if (isset($this->selectionMode[$playerName]) && !isset($this->firstPosition[$playerName])) {
            $this->firstPosition[$playerName] = $event->getBlock()->getPosition();
            $player->sendMessage(
                str_replace("{pos}", $event->getBlock()->getPosition(), $this->messages->get("first_position_set"))
            );
            $event->cancel();
            return;
        }

        $block = $event->getBlock();
        $pos   = $block->getPosition();
        $world = $pos->getWorld();
        $worldName = $world->getFolderName();

        foreach ($this->mines->getAll() as $mineName => $data) {
            if (!isset($data["world"], $data["pos1"], $data["pos2"])) continue;
            if ($worldName !== $data["world"]) continue;

            $p1 = new Vector3(...$data["pos1"]);
            $p2 = new Vector3(...$data["pos2"]);
            if (!$this->isInside($pos, $p1, $p2)) continue;

            // ── LUCKY BLOCK DETECTION ──
            if (($data["lucky_blocks_enabled"] ?? false) && isset($data["lucky_block_types"])) {
                foreach ($data["lucky_block_types"] as $luckyName) {
                    $luckyData = $this->luckyBlockManager->get($luckyName);
                    if (!is_array($luckyData) || !isset($luckyData["block_id"])) continue;

                    $luckyItem = StringToItemParser::getInstance()->parse($luckyData["block_id"]);
                    if ($luckyItem === null) continue;

                    if ($block->getTypeId() === $luckyItem->getBlock()->getTypeId()) {
                        // -- DEBUG: lucky block detected
                        $player->sendMessage($this->messages->get("luckyblock_found"));
                        $event->setDrops([]);

                        // Build flat chance map
                        $flat = [];
                        if (!empty($luckyData["drop_list"])) {
                            if (is_string(array_key_first($luckyData["drop_list"]))) {
                                foreach ($luckyData["drop_list"] as $itemKey => $chance) {
                                    $flat[$itemKey] = (int)$chance;
                                }
                            } else {
                                foreach ($luckyData["drop_list"] as $entry) {
                                    foreach ($entry as $itemKey => $chance) {
                                        $flat[$itemKey] = (int)$chance;
                                    }
                                }
                            }
                        }

                        $total = array_sum($flat);
                        if ($total < 1) {
                            $player->sendMessage($this->messages->get("luckyblock_zero_weight"));
                            return;
                        }

                        $count = mt_rand((int)$luckyData["min_drop_count"], (int)$luckyData["max_drop_count"]);
                        for ($i = 0; $i < $count; $i++) {
                            $r = mt_rand(1, $total);
                            $acc = 0;
                            $chosen = null;
                            foreach ($flat as $itemKey => $w) {
                                $acc += $w;
                                if ($r <= $acc) {
                                    $chosen = $itemKey;
                                    break;
                                }
                            }
                            if ($chosen === null) {
                                $player->sendMessage($this->messages->get("luckyblock_no_item"));
                                continue;
                            }
                            $dropItem = StringToItemParser::getInstance()->parse($chosen);
                            if ($dropItem !== null) {
                                $world->dropItem($pos, $dropItem);
                            } else {
                                $player->sendMessage(
                                    str_replace("{item}", $chosen, $this->messages->get("luckyblock_parse_fail"))
                                );
                            }
                        }

                        // ── COMMAND EXECUTION ──
                        if (
                            isset($luckyData["min_cmd_count"], $luckyData["max_cmd_count"]) &&
                            is_array($luckyData["commands"]) &&
                            !empty($luckyData["commands"])
                        ) {
                            // Normalize command chance list
                            $cmdMap = [];

                            if (is_string(array_key_first($luckyData["commands"]))) {
                                // Format: command => chance
                                foreach ($luckyData["commands"] as $cmd => $chance) {
                                    $cmdMap[$cmd] = (int)$chance;
                                }
                            } else {
                                // Format: list of arrays
                                foreach ($luckyData["commands"] as $entry) {
                                    if (is_array($entry)) {
                                        foreach ($entry as $cmd => $chance) {
                                            $cmdMap[$cmd] = (int)$chance;
                                        }
                                    }
                                }
                            }

                            $totalWeight = array_sum($cmdMap);
                            if ($totalWeight > 0) {
                                $cmdCount = mt_rand((int)$luckyData["min_cmd_count"], (int)$luckyData["max_cmd_count"]);
                                for ($i = 0; $i < $cmdCount; $i++) {
                                    $r = mt_rand(1, $totalWeight);
                                    $acc = 0;
                                    $selected = null;
                                    foreach ($cmdMap as $cmd => $weight) {
                                        $acc += $weight;
                                        if ($r <= $acc) {
                                            $selected = $cmd;
                                            break;
                                        }
                                    }

                                    if ($selected !== null) {
                                        $command = str_replace("{player}", $player->getName(), $selected);
                                        $console = new \pocketmine\console\ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage());
                                        $this->getServer()->dispatchCommand($console, $command);
                                    }
                                }
                            }
                        } elseif (!is_array($luckyData["commands"])) {
                            $player->sendMessage($this->messages->get("luckyblock_invalid_cmd"));
                        }

                        // effects
                        if (!empty($luckyData["effects"]["particles"])) {
                            $color = new Color(255, 255, 0); // Yellow color :P (for the luckyblock effect ofc)
                            for ($i = 0; $i < 12; $i++) {
                                $dx = mt_rand(-50, 50) / 100;
                                $dy = mt_rand(-50, 50) / 100;
                                $dz = mt_rand(-50, 50) / 100;
                                $spawnPos = $pos->add($dx, $dy + 0.5, $dz);
                                $world->addParticle($spawnPos, new DustParticle($color));
                            }
                        }
                        if (!empty($luckyData["effects"]["sound"])) {
                            $world->addSound($pos, new XpLevelUpSound(5));
                        }

                        return;
                    }
                }
            }

            // ── BLOCK DROP TOGGLE ──
            $dropMap = $data["blockDrop"] ?? [];
            $aliases = StringToItemParser::getInstance()->lookupBlockAliases($block);
            foreach ($aliases as $alias) {
                $key = strtolower(ltrim($alias, "minecraft:"));
                if (array_key_exists($key, $dropMap) && $dropMap[$key] === false) {
                    $event->setDrops([]);
                    break;
                }
            }

            // ── XP DROP HANDLING ──
            $xpMap   = $data["blockXp"] ?? [];
            $aliases = StringToItemParser::getInstance()->lookupBlockAliases($block);
            foreach ($aliases as $alias) {
                $key = strtolower(ltrim($alias, "minecraft:"));
                if (isset($xpMap[$key])) {
                    $player->getXpManager()->addXp((int)$xpMap[$key]);
                    $event->setXpDropAmount(0);
                    break;
                }
            }

            $this->getScheduler()->scheduleDelayedTask(
                new class($this, $mineName) extends Task {
                    public function __construct(private Main $plugin, private string $mineName) {}
                    public function onRun(): void {
                        if ($this->plugin->isRegionEmpty($this->mineName)
                            && empty($this->plugin->pendingEmptyResets[$this->mineName])) {
                            $this->plugin->pendingEmptyResets[$this->mineName] = true;

                            if ($this->plugin->isWarnEnabled() && !$this->plugin->isSilentReset($this->mineName)) {
                                $mineData = $this->plugin->getMineData($this->mineName);
                                if ($mineData !== null) {
                                    $world = $this->plugin->getServer()
                                        ->getWorldManager()
                                        ->getWorldByName($mineData["world"]);
                                    if ($world instanceof World) {
                                        foreach ($world->getPlayers() as $pl) {
                                            $pl->sendMessage(
                                                str_replace(
                                                    "{mine}",
                                                    $this->mineName,
                                                    $this->plugin->getMessage("mine_will_reset_in")

                                                )
                                            );
                                        }
                                    }
                                }
                            }

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

    private function getWeightedDrop(array $dropList): string {
        $flatList = [];
        foreach ($dropList as $entry) {
            foreach ($entry as $item => $chance) {
                $flatList[$item] = (int)$chance;
            }
        }

        $total = array_sum($flatList);
        if ($total < 1) {
            return key($flatList) ?? "";
        }

        $rand = mt_rand(1, $total);
        $accum = 0;
        foreach ($flatList as $item => $chance) {
            $accum += (int)$chance;
            if ($rand <= $accum) {
                return $item;
            }
        }

        return key($flatList) ?? "";
    }
 
    public function onPlayerInteract(BlockBreakEvent $event): void {
        $p    = $event->getPlayer();
        $name = $p->getName();

        if(isset($this->selectionMode[$name], $this->firstPosition[$name]) && !isset($this->secondPosition[$name])){
            $this->secondPosition[$name] = $event->getBlock()->getPosition();
            unset($this->selectionMode[$name]);
            $event->cancel();
            $p->sendMessage(
                str_replace("{pos}", $event->getBlock()->getPosition(), $this->messages->get("second_position_set"))
            );
            $p->sendMessage($this->messages->get("prompt_next_create"));
        }
    }

    private function openCreateForm(Player $player): void {
        $form = new CustomForm(function(Player $p, ?array $data) {
            if ($data === null) {
                return; 
            }
    
            [$rawName, $rawBlocks, $rawTime] = $data;
            $mineName  = strtolower(trim($rawName));
            $blocks    = $this->parseBlocksInput($rawBlocks);
            $resetTime = max(0, (int)$rawTime);
            $playerName = $p->getName();
    
            if ($mineName === "" || empty($blocks)) {
                $p->sendMessage($this->messages->get("invalid_name_or_blocklist"));
                return;
            }
            if ($this->mines->exists($mineName)) {
                $p->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_already_exists_on_create")));
                return;
            }
            if (!isset($this->firstPosition[$playerName], $this->secondPosition[$playerName])) {
                $p->sendMessage($this->messages->get("select_positions_first"));
                return;
            }
    
            $p1 = $this->firstPosition[$playerName];
            $p2 = $this->secondPosition[$playerName];
            $this->mines->set($mineName, [
                "world"         => $p->getWorld()->getFolderName(),
                "pos1"          => [$p1->getX(), $p1->getY(), $p1->getZ()],
                "pos2"          => [$p2->getX(), $p2->getY(), $p2->getZ()],
                "blocks"        => $blocks,
                "autoResetTime" => $resetTime,
                "blockXp"       => []
            ]);
            $this->mines->save();
    
            $this->fillArea($p->getWorld(), $p1, $p2, $blocks);
            if ($resetTime > 0) {
                $this->schedulePeriodicReset($mineName, $resetTime);
            }
    

            $p->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_created")));
            unset(
                $this->selectionMode[$playerName],
                $this->firstPosition[$playerName],
                $this->secondPosition[$playerName]
            );
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
        $time = (int)$data["autoResetTime"];

        // Default values for lucky blocks
        $luckyEnabled = $data["lucky_blocks_enabled"] ?? false;
        $allLuckyBlocks = $this->luckyBlockManager->getAllLuckyBlocks();
        $excludedKeys = ["lucky_blocks_enabled", "lucky_block_types"];
        $availableLuckyTypes = array_values(array_filter(array_keys($allLuckyBlocks), fn($key) => !in_array($key, $excludedKeys, true)));

        $selectedLucky = ($data["lucky_block_types"] ?? []);
        $selectedLuckyIndex = $availableLuckyTypes !== [] && isset($selectedLucky[0])
            ? array_search($selectedLucky[0], $availableLuckyTypes)
            : 0;

        $form = new CustomForm(function(Player $p, ?array $dataIn) use ($mineName, $data, $availableLuckyTypes) {
            if ($dataIn === null) return;

            [$newName, $rawBlocks, $rawTime, $luckyToggle, $luckyTypeIndex] = $dataIn;

            $newName   = strtolower(trim((string)$newName));
            $blocks    = $this->parseBlocksInput($rawBlocks);
            $resetTime = max(0, (int)$rawTime);

            if ($newName === "" || empty($blocks)) {
                $p->sendMessage($this->messages->get("invalid_name_or_blocklist"));
                return;
            }
            if (strtolower($newName) !== strtolower($mineName) && $this->mines->exists($newName)) {
                $p->sendMessage(
                    str_replace(
                        "{name}",
                        $newName,
                        $this->messages->get("mine_already_exists")
                    )
                );
                return;
            }
            $this->cancelScheduledReset($mineName);

            $newData = $data;
            $newData["blocks"] = $blocks;
            $newData["autoResetTime"] = $resetTime;
            $newData["lucky_blocks_enabled"] = $luckyToggle;
            $newData["lucky_block_types"] = [$availableLuckyTypes[$luckyTypeIndex]];

            if (strtolower($newName) !== strtolower($mineName)) {
                $this->mines->remove($mineName);
            }

            $this->mines->set($newName, $newData);
            $this->mines->save();
            $this->mines->reload();

            if ($resetTime > 0) {
                $this->schedulePeriodicReset($newName, $resetTime);
            }

            $this->resetMineByName($newName);

            $p->sendMessage(
                str_replace(
                    ["{old}", "{new}"],
                    [$mineName, $newName],
                    $this->messages->get("mine_rename_success")
                )
            );
        });

        $form->setTitle("Edit Mine: $mineName");
        $form->addInput("Mine Name", "e.g. stone_mine", $mineName);
        $form->addInput("Blocks", "e.g. stone,50,iron_ore,30", $blocksCsv);
        $form->addInput("Auto‐reset time (sec)", "e.g. 600", (string)$time);

        // Lucky Block Settings
        $form->addToggle("Enable Lucky Blocks", $luckyEnabled);
        $form->addStepSlider("Lucky Block Type", $availableLuckyTypes, $selectedLuckyIndex);

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
                $p->sendMessage(
                    str_replace("{mine}", $mine, $this->messages->get("mine_reset_success"))
                );
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
        if (!$this->mines->exists($mineName)) {
            $player->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_not_found")));
            return;
        }

        $data = $this->mines->get($mineName);
        if (is_array($data) && isset($data["world"], $data["pos1"], $data["pos2"])) {
            $worldName = $data["world"];
            $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
            if ($world instanceof World) {
                $p1 = new Vector3(...$data["pos1"]);
                $p2 = new Vector3(...$data["pos2"]);

                $this->clearArea($world, $p1, $p2);

                $whiteColor = new Color(255, 255, 255);
                for ($x = min($p1->getX(), $p2->getX()); $x <= max($p1->getX(), $p2->getX()); $x++) {
                    for ($y = min($p1->getY(), $p2->getY()); $y <= max($p1->getY(), $p2->getY()); $y++) {
                        for ($z = min($p1->getZ(), $p2->getZ()); $z <= max($p1->getZ(), $p2->getZ()); $z++) {
                            $pos = new Vector3($x + 0.5, $y + 0.5, $z + 0.5);
                            $world->addParticle($pos, new DustParticle($whiteColor));
                        }
                    }
                }

                $centerX = ($p1->getX() + $p2->getX()) / 2;
                $centerY = ($p1->getY() + $p2->getY()) / 2;
                $centerZ = ($p1->getZ() + $p2->getZ()) / 2;
                $centerPos = new Vector3($centerX, $centerY, $centerZ);
                $world->addSound($centerPos, new ExplodeSound(5));
            }
        }

        $this->cancelScheduledReset($mineName);

        $this->mines->remove($mineName);
        $this->mines->save();
        unset($this->pendingEmptyResets[$mineName]);

        $player->sendMessage(str_replace("{mine}", $mineName, $this->messages->get("mine_deleted_success")));
    }

    private function cancelScheduledReset(string $name): void {
        if (isset($this->scheduledTasks[$name])) {
            $this->scheduledTasks[$name]->cancel();
            unset($this->scheduledTasks[$name]);
        }
    }

    public function getMessages(): Config {
        return $this->messages;
    }
    
    private function schedulePeriodicReset(string $name, int $intervalSec): void {
        $handler = $this->getScheduler()->scheduleRepeatingTask(
            new class($this, $name) extends Task {
                public function __construct(private Main $plugin, private string $mineName) {}
    
                public function onRun(): void {
                    if ($this->plugin->isWarnEnabled()) {
                        $mineData = $this->plugin->getMineData($this->mineName);
                        if ($mineData !== null) {
                            $world = $this->plugin->getServer()
                                ->getWorldManager()
                                ->getWorldByName($mineData["world"]);
                            if ($world instanceof World) {
                                foreach ($world->getPlayers() as $player) {
                                    $player->sendMessage(
                                        str_replace(
                                            "{mine}",
                                            $this->mineName,
                                            $this->plugin->getMessages()->get("mine_reset_warning")
                                        )
                                    );
                                }
                            }
                        }
                    }
                    
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
    
        $this->scheduledTasks[$name] = $handler;
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

        // Teleport players out of the mine during reset
        foreach ($world->getPlayers() as $player) {
            $pos = $player->getPosition();
            if ($this->isInside($pos, $p1, $p2)) {
                $player->teleport(new Vector3($pos->getX(), $topY, $pos->getZ()));
                $player->sendMessage($this->messages->get("mine_reset_teleport"));

                if ($this->warnEnabled) {
                    $player->sendMessage(
                        str_replace(
                            "{mine}",
                            $mineName,
                            $this->messages->get("mine_reset_complete")
                        )
                    );
                }
            }
        }

        $this->clearArea($world, $p1, $p2);

        // Fill the mine area with blocks (including lucky blocks)
        $this->fillArea($world, $p1, $p2, $data["blocks"]);

        // Send reset notification if enabled
        if ($this->warnEnabled && !$this->isSilentReset($name)) {
            foreach ($world->getPlayers() as $player) {
                $player->sendMessage(
                    str_replace("{mine}", $name, $this->messages->get("mine_reset_complete"))
                );
            }
        }
    }

    private function fillArea(World $world, Vector3 $p1, Vector3 $p2, array $blocks): void {
        $minX = min($p1->getX(), $p2->getX());
        $maxX = max($p1->getX(), $p2->getX());
        $minY = min($p1->getY(), $p2->getY());
        $maxY = max($p1->getY(), $p2->getY());
        $minZ = min($p1->getZ(), $p2->getZ());
        $maxZ = max($p1->getZ(), $p2->getZ());

        $lucky = $this->getLuckyBlockManager();

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $name = $this->getRandomBlock($blocks);

                    if ($lucky->exists($name)) {
                        $typeData = $lucky->get($name);
                        $item = StringToItemParser::getInstance()->parse($typeData["block_id"]);
                    } else {
                        $item = StringToItemParser::getInstance()->parse($name);
                    }

                    if ($item === null) continue;

                    $block = $item->getBlock();
                    $world->setBlock(new Vector3($x, $y, $z), $block, false);
                }
            }
        }
    }

    private function clearArea(World $world, Vector3 $p1, Vector3 $p2): void {
        $minX = min($p1->getX(), $p2->getX());
        $maxX = max($p1->getX(), $p2->getX());
        $minY = min($p1->getY(), $p2->getY());
        $maxY = max($p1->getY(), $p2->getY());
        $minZ = min($p1->getZ(), $p2->getZ());
        $maxZ = max($p1->getZ(), $p2->getZ());

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $world->setBlock(new Vector3($x, $y, $z), VanillaBlocks::AIR());
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
