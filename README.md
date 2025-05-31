---

# ⛏️ MineCreator 1.2.0

A user-friendly and performance-focused mine management plugin for PocketMine-MP servers.
Create, edit, and reset mines with region selection, percentage-based block filling, XP control, automatic resets, and a new Lucky Block feature — all through an intuitive FormAPI interface!
---
## Features

* 📐 **Region Selection**: Select two positions by breaking blocks to define the mine area.
* 📋 **GUI-Based Mine Setup**: Manage mines easily using interactive forms.
* 🧱 **Block Fill by Percentage**: Fill mines using custom block weights (e.g., `stone 50%, coal_ore 30%`).
* 🕒 **Auto-Reset Timer**: Automatically resets mines after a configurable delay.
* ⚠️ **Reset Warnings**: Sends a reset warning to players 5 seconds before a mine resets.
* 🔁 **Manual or Automatic Reset**: Reset mines manually or on a timer.
* 🧼 **Empty Resets**: Auto resets when a mine is empty.
* 💥 **Block-Based XP System**: Assign custom XP values per block using a simple command.
* 🚫 **XP Drop Prevention**: Cancels XP orb drops inside mines on blocks with custom XP values to avoid duplicates.
* 🍀 **Lucky Blocks**: Spawn special Lucky Blocks that trigger random drops, particle effects, and sounds when broken.
* 🎆 **Visual and Sound Effects**: Particle and sound effects during Lucky Block activation and mine resets.
* ⚙️ **Configurable Lucky Block Settings**: Define Lucky Block item, drops, particles, sounds, and cooldowns via `luckyblock.yml`.
---
## Commands

| Command            | Description                           | Permission                     |
| ------------------ | ------------------------------------- | ------------------------------ |
| `/mine`            | Opens the main mine command help      | `minecreator.command.mine`     |
| `/mine position`   | Select region by breaking 2 blocks    | `minecreator.command.mine`     |
| `/mine create`     | Create a new mine using a form        | `minecreator.command.mine`     |
| `/mine list`       | Shows a list of available mines       | `minecreator.command.mine`     |
| `/mine edit`       | Edit an existing mine                 | `minecreator.command.mine`     |
| `/mine delete`     | Delete a mine                         | `minecreator.command.mine`     |
| `/mine reset`      | Manually reset a mine                 | `minecreator.command.mine`     |
| `/mine reload`     | Reloads the plugin                    | `minecreator.command.reload`   |
| `/mine setblockxp` | Set XP for a specific block in a mine | `minecreator.command.mine`     |
| `/minewarn`        | Toggle reset warnings on/off          | `minecreator.command.minewarn` |
---
## How to Use

1. Place the plugin `.phar` or folder into your `plugins/` directory.
2. Start your server.
3. Use `/mine position` and break 2 blocks to define a region.
4. Use `/mine create` to open the setup form:

   * **Mine Name**
   * **Blocks & Percentages** (e.g., `stone,50,coal_ore,30,iron_ore,20`)
   * **Auto-Reset Time** (in seconds)
6. Assign XP to specific blocks:

   ```
   /mine setblockxp <mine> <block_id> <xp>
   ```

   Example: `/mine setblockxp mine1 iron_ore 10`
---
7. Use the Lucky Block item (configurable) to spawn Lucky Blocks inside mines and enjoy random drops with effects.

> You can cancel or redo the region selection if you make a mistake before creating the mine.

## Reset Warnings

Players in the same world as the mine will receive a warning 5 seconds before it resets.
Use `/minewarn on` or `/minewarn off` to enable or disable this feature.

## XP Drop Handling (Thanks to .n00bs. for the idea)

* XP orbs are disabled when breaking mine-assigned blocks inside mines to avoid XP theft.
* XP for blocks is custom-defined, allowing XP assignment even to blocks that normally don’t drop XP.

## Lucky Block Configuration (`luckyblock.yml`)

* Customize the Lucky Block item ID and meta.
* Define possible drops with custom chances and quantities.
* Configure particle and sound effects for Lucky Block activation.

---

## File Structure

* `mines.json`: Stores all mines and their settings.

  ```json
  {
    "mine_name": {
      "world": "world",
      "pos1": [100, 60, 100],
      "pos2": [110, 65, 110],
      "blocks": {
        "stone": 50,
        "coal_ore": 30,
        "iron_ore": 20
      },
      "blockXp": {
        "iron_ore": 10,
        "coal_ore": 5
      },
      "autoResetTime": 600
    }
  }
  ```

* `luckyblock.yml`: stores all luckyblock configurations.

```yaml
# Enable or disable the lucky blocks feature globally.
# (Do not disable if you use lucky blocks in any mines, or they will become air.)
lucky_blocks_enabled: true

# List of active lucky block types on the server.
# Only these block types trigger lucky block effects when broken.
lucky_block_types:
  - gold_lucky_block
  - diamond_lucky_block

# --------------------------------------#
# Configuration for diamond_lucky_block #
# --------------------------------------#
diamond_lucky_block:
  # Block ID for this lucky block
  block_id: "minecraft:diamond_block"

  # Number of items to drop (random between these values)
  min_drop_count: 2
  max_drop_count: 5

  # Number of commands to execute (random between these values)
  min_cmd_count: 1
  max_cmd_count: 5

  # Weighted item drop list using array format (supports multiple entries)
  drop_list:
    - iron_ingot: 25
    - gold_ingot: 25
    - diamond: 40
    - emerald: 10

  # List of commands to run when the lucky block is broken.
  # {player} will be replaced with the breaking player’s name.
  commands:
    - "give {player} netherite_sword": 25
    - "give {player} diamond 1": 25
    - "give {player} dirt 1": 40
    - "xp 1000 {player}": 10

  # Effects to play on lucky block activation
  effects:
    particles: true
    sound: true
```

* `messages.yml`: stores all the messages.

```yaml
# ┌─────────────────────────────────────────────────────────────────────────────────┐
# │                               Placeholders                                      │
# ├─────────────────────────────────────────────────────────────────────────────────┤
# │   {mine}     – replaced with the mine’s name (e.g., "stone_mine")               │
# │   {block}    – replaced with a block’s alias or ID (e.g., "stone")              │
# │   {alias}    – same as {block}, normalized alias for consistency                │
# │   {xp}       – replaced with an XP value (integer)                              │
# │   {item}     – replaced with an item ID (e.g., "diamond_sword")                 │
# │   {pos}      – replaced with a Vector3 position string (e.g., "Vector3(x,y,z)") │
# │   {new}      – replaced with a new mine name when renaming                      │
# │   {old}      – replaced with the old mine name when renaming                    │
# │                                                                                 │
# │   additional:                                                                   │
# │       {status} {name}                                                           │
# │                                                                                 │
# │   - Do not add additional placeholders inside a msg which does not use it.      │
# └─────────────────────────────────────────────────────────────────────────────────┘

# When you try to use a command on console, it will send this.
use_in_game: "Use in-game!"

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                               /minewarn                                    │
# └────────────────────────────────────────────────────────────────────────────┘
no_permission_minewarn: "§7[§l§dMine§r§7] §c>> §cYou don't have permission to toggle mine warnings."
usage_minewarn:         "§7[§l§dMine§r§7] §c>> §eUsage: §b/minewarn <on|off>"
minewarn_enabled:       "§7[§l§dMine§r§7] §c>> §aMine reset warnings §benabled§a."
minewarn_disabled:      "§7[§l§dMine§r§7] §c>> §cMine reset warnings §bdisabled§c."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                             /mine Help Menu                                │
# └────────────────────────────────────────────────────────────────────────────┘
mine_help_header:        "§6§l===== §eMineCreator Help §6§l====="
mine_help_item1:         "§e/mine help §7– Show this help menu."
mine_help_list:          "§e/mine list §7– List all existing mines."
mine_help_list_desc:     "   §8Shows you every mine by name．"
mine_help_pos:           "§e/mine position §7– Start defining a new mine region."
mine_help_pos_desc:      "   §8Break one block for the §bfirst corner§8, then another for the §bsecond corner§8．"
mine_help_create:        "§e/mine create §7– Open the Create Mine form."
mine_help_create_desc:   "   §8After selecting positions， this lets you:"
mine_help_create_bullets:
  - "   §8 • Name your mine (e.g. §bstone_mine§8)"
  - "   §8 • Choose blocks & percentages (e.g. §cstone,50,iron_ore,30§8)"
  - "   §8 • Set auto-reset interval in seconds (e.g. §b600§8)"
mine_help_edit:          "§e/mine edit <name> §7– Edit an existing mine via form."
mine_help_edit_desc:     "   §8Change its name, blocks, or auto-reset time．"
mine_help_reset:         "§e/mine reset <name> §7– Immediately reset a mine."
mine_help_reset_desc:    "   §8Teleports anyone inside up above, refills the region．"
mine_help_delete:        "§e/mine delete <name> §7– Permanently delete a mine."
mine_help_delete_desc:   "   §8Removes it from config and cancels auto-resets．"
mine_help_setblockxp:    "§e/mine setblockxp <mine> <block> <xp> §7– Set XP drop for a specific block in a mine."
mine_help_setblockxp_desc: "   §8Updates the mine’s config and applies immediately."
mine_help_blockdrop: "§e/mine blockdrop <mine> <block> <true|false> §7– Enable or disable drops for specific blocks in a mine"
mine_help_blockdrop_desc: "    §8Toggle whether breaking a specific block in the mine drops items or not．"
mine_help_minewarn: "§e/mine minewarn <mine> <true|false> §7– Enable or disable warning messages before a mine reset"
mine_help_minewarn_desc: "   §8Control if players receive warnings when the mine is about to reset．"
mine_help_reload:        "§e/mine reload §7– Reload this plugin."
mine_help_reload_desc:   "   §8Disables and re-enables MineCreator， reloading all settings."
mine_help_footer:        "§6§l==============================="

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                       /mine setblockxp & blockdrops                        │
# └────────────────────────────────────────────────────────────────────────────┘
usage_setblockxp:        "§7[§l§dMine§r§7] §c>> §eUsage: §b/mine setblockxp <mine> <block> <xp>"
unknown_block:           "§7[§l§dMine§r§7] §c>> §cUnknown block: {block}"
mine_not_found:          "§7[§l§dMine§r§7] §c>> §cMine '{mine}' not found!"
xp_set_success:          "§7[§l§dMine§r§7] §c>> §aSet XP of {xp} for block '{alias}' in mine '{mine}'."

usage_blockdrop: "§cUsage: /mine blockdrop <mine> <block> <true/false>"
block_drop_toggle_success: "§aBlock drop for '{block}' in mine '{mine}' set to '{status}'."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                             /mine position                                 │
# └────────────────────────────────────────────────────────────────────────────┘
prompt_select_first:     "§7[§l§dMine§r§7] §c>> §aBreak one block for §bFirst§a pos, then break another for §bSecond§a pos."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                            /mine create logic                              │
# └────────────────────────────────────────────────────────────────────────────┘
need_two_positions:      "§7[§l§dMine§r§7] §c>> §cSelect two positions first with §e/mine position§c!"
mine_already_exists_on_create: "§7[§l§dMine§r§7] §c>> §cA mine named '{mine}' already exists. Please choose another."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                            Selection Control                               │
# └────────────────────────────────────────────────────────────────────────────┘
selection_cancelled:     "§7[§l§dMine§r§7] §c>> §6Selection cancelled. You can start again."
first_position_set:      "§7[§l§dMine§r§7] §c>> §aFirst position set at {pos}"
second_position_set:     "§7[§l§dMine§r§7] §c>> §aSecond position set at {pos}"
prompt_next_create:      "§7[§l§dMine§r§7] §c>> §bType §l§cCancel §r§bTo Start Over Or §l§e/mine create §r§bTo Create The Mine"

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                              Lucky Blocks                                  │
# └────────────────────────────────────────────────────────────────────────────┘
luckyblock_found:        "§a[LuckyBlock] §6You mined a lucky block!"
luckyblock_zero_weight:  "§c[LuckyBlock] total weight is zero, no drops will occur. Report to an admin!"
luckyblock_no_item:      "§c[LuckyBlock] no item selected (unexpected). Report to an admin!"
luckyblock_parse_fail:   "§c[LuckyBlock] failed to parse item '{item}'. Report to an admin!"
luckyblock_invalid_cmd:  "§c[LuckyBlock] Invalid 'commands' format in luckyblock.yml. Must be an array. Report to an admin!"

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                          Mine Reset & Warnings                             │
# └────────────────────────────────────────────────────────────────────────────┘
mine_will_reset_in:      "§7[§l§dMine§r§7] §c>> §6Mine '{mine}' will reset in 5 seconds!"
mine_reset_warning:      "§7[§l§dMine§r§7] §c>> §6Mine '{mine}' will reset in 5 seconds!"  # alias for consistency
mine_reset_teleport:     "§7[§l§dMine§r§7] §c>> §eYou have been teleported above the mine due to a reset."
mine_reset_complete:     "§7[§l§dMine§r§7] §c>> §aMine '{mine}' has been reset!"
mine_reset_success:      "§7[§l§dMine§r§7] §c>> §aMine '{mine}' reset."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                          /mine list & delete                               │
# └────────────────────────────────────────────────────────────────────────────┘
no_mines_exist:         "§7[§l§dMine§r§7] §c>> §eNo mines have been created yet."
available_mines:        "§7[§l§dMine§r§7] §c>> §aAvailable Mines:"
mine_list_entry:        "§7[§l§dMine§r§7] §c>> §b- {mine}"
mine_deleted_success:   "§7[§l§dMine§r§7] §c>> §aMine '{mine}' deleted successfully."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                            /mine reload                                    │
# └────────────────────────────────────────────────────────────────────────────┘
no_permission_reload:   "§7[§l§dMine§r§7] §c>> §cYou don't have permission to reload."
reload_success:         "§7[§l§dMine§r§7] §c>> §aMineCreator has been reloaded!"

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                           Mine updates & errors                           │
# └────────────────────────────────────────────────────────────────────────────┘
mine_updated_success:   "§7[§l§dMine§r§7] §c>> §aMine '{old}' updated to '{new}'!"
mine_already_exists:    "§7[§l§dMine§r§7] §c>> §cA mine with the name '{name}' already exists!"
invalid_name_or_blocklist: "§7[§l§dMine§r§7] §c>> §cInvalid name or block list!"
mine_created:           "§7[§l§dMine§r§7] §c>> §aMine '{mine}' created!"
must_select_positions:  "§7[§l§dMine§r§7] §c>> §cYou must select two positions first with /mine position!"

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                           Fallback / Unknown                               │
# └────────────────────────────────────────────────────────────────────────────┘
generic_unknown:        "§7[§l§dMine§r§7] §c>> §cUnknown subcommand. Type §e/mine help §cfor a list of commands."

# ┌────────────────────────────────────────────────────────────────────────────┐
# │                      Additional Optional Messages                           │
# └────────────────────────────────────────────────────────────────────────────┘
xp_gained:              "§7[§l§dMine§r§7] §c>> §aYou gained {xp} XP from mining '{block}'"
mine_rename_success:    "§7[§l§dMine§r§7] §c>> §aMine '{old}' renamed to '{new}'!"
```

## Admin Permissions

* `minecreator.command.mine` – Full access to mine commands and forms
* `minecreator.command.reload` – Access to reload the plugin
* `minecreator.command.minewarn` – Toggle reset warnings

---

# Download

[Download Latest Stable Release](https://poggit.pmmp.io/r/255559/MineCreator_dev-53.phar)

# Support

Found a bug or have a feature request? Open an issue or pull request.

---
