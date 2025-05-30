---

# ⛏️ MineCreator 1.2.0

A user-friendly and performance-focused mine management plugin for PocketMine-MP servers.
Create, edit, and reset mines with region selection, percentage-based block filling, XP control, automatic resets, and a new Lucky Block feature — all through an intuitive FormAPI interface!

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

### Configuration (`luckyblock.yml`)

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

### Notes

* Lucky blocks only work if `lucky_blocks_enabled` is `true`.
* Only the block types listed under `lucky_block_types` will trigger lucky block effects.
* You can configure multiple lucky block types with their own drop lists, commands, and effects.
* Commands can include placeholders such as `{player}`, which will be replaced with the player’s name who broke the lucky block.
* Effects like particles and sounds can be toggled for each lucky block type.

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
* `luckyblock.yml`: Configuration file for Lucky Block features.

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
