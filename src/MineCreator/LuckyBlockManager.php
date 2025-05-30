<?php

namespace MineCreator;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class LuckyBlockManager {
    private Config $config;
    private array $blocks = [];

    public function __construct(string $dataFolder) {
        $this->config = new Config($dataFolder . "luckyblock.yml", Config::YAML);
        $this->blocks = $this->config->getAll();
    }

    public function load(): void {
        $file = $this->dataFolder . "luckyblock.yml";
        if (!file_exists($file)) {
            file_put_contents($file, yaml_emit([
                "lucky_blocks" => []
            ]));
        }

        $config = new Config($file, Config::YAML);
        $data = $config->get("lucky_blocks", []);

        foreach ($data as $type => $blockData) {
            if (!isset($blockData["block_id"], $blockData["drop_list"], $blockData["min_drop_count"], $blockData["max_drop_count"], $blockData["effects"])) {
                continue;
            }

            $this->blocks[$type] = [
                "block_id" => $blockData["block_id"],
                "drop_list" => $blockData["drop_list"],
                "min_drop_count" => (int)$blockData["min_drop_count"],
                "max_drop_count" => (int)$blockData["max_drop_count"],
                "effects" => $blockData["effects"]
            ];
        }
    }

    public function getTypes(): array {
        return array_keys($this->blocks);
    }

    public function getAllLuckyBlocks(): array {
        return $this->config->getAll();
    }

    public function get(string $type): ?array {
        return $this->blocks[$type] ?? null;
    }

    public function exists(string $type): bool {
        return isset($this->blocks[$type]);
    }

    public function reload(): void {
        $this->blocks = [];
        $this->load();
    }
}
