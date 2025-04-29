# ⛏️ MineCreator

A user-friendly and performance-focused mine management plugin for PocketMine-MP servers.  
Create, edit, and reset mines with region selection, percentage-based block filling, and automatic resets – all through an intuitive FormAPI interface!

## Features
- 📐 **Region Selection**: Select two positions by breaking blocks to define the mine area.
- 📋 **GUI-Based Mine Setup**: Manage mines easily using interactive forms.
- 🧱 **Block Fill by Percentage**: Fill mines using custom block weights (e.g., `stone 50%, coal_ore 30%`).
- 🕒 **Auto-Reset Timer**: Automatically resets mines after a configurable delay.
- ⚠️ **Reset Warnings**: Sends a reset warning to players 5 seconds before a mine resets.
- 🔁 **Manual or Automatic Reset**: Reset mines manually or on a timer.
- 🧼 **Empty Resets**: auto resets when a mine is empty.
- 💾 **Persistent JSON Storage**: All mine data saved in a JSON file for easy backup and editing.

## Commands
| Command          | Description                          | Permission                   |
|------------------|--------------------------------------|------------------------------|
| `/mine`          | Opens the main mine command help     | `minecreator.command.mine`   |
| `/mine position` | Select region by breaking 2 blocks   | `minecreator.command.mine`   |
| `/mine create`   | Create a new mine using a form       | `minecreator.command.mine`   |
| `/mine list`   | Shows a list of available mines       | `minecreator.command.mine`   |
| `/mine edit`     | Edit an existing mine                | `minecreator.command.mine`   |
| `/mine delete`   | Delete a mine                        | `minecreator.command.mine`   |
| `/mine reset`    | Manually reset a mine                | `minecreator.command.mine`   |
| `/mine reload`    | Reloads the plugin                | `minecreator.command.reload`   |
| `/minewarn`      | Toggle reset warnings on/off         | `minecreator.command.minewarn`   |


## How to Use
1. Install **FormAPI** by [jojoe77777](https://github.com/jojoe77777/FormAPI) *(Required)*.
2. Place the plugin `.phar` or folder into your `plugins/` directory.
3. Start your server.
4. Use `/mine position` and break 2 blocks to define a region.
5. Use `/mine create` to open the setup form:
   - **Mine Name**
   - **Blocks & Percentages** (e.g., `stone,50,coal_ore,30,iron_ore,20`)
   - **Auto-Reset Time** (in seconds)

You can cancel or redo the region selection if you make a mistake before creating the mine.

## Reset Warnings
Players in the same world as the mine will receive a warning 5 seconds before it resets.  
Use `/minewarn on` or `/minewarn off` to enable or disable this feature.

## File Structure
- `mines.json`: Stores all mines and their settings.
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
      "autoResetTime": 600
    }
  }
  ```
## Note
- All mines reset after the plugin reloads to ensure everything works well!

## Dependencies
- [FormAPI](https://github.com/jojoe77777/FormAPI) by jojoe77777

## Admin Permissions
Be sure to give the appropriate permission to your admin:
- `minecreator.command.mine` – Full access to mine commands and forms
- `minecreator.command.reload` - access to reload the plugin

---

# Download
[Download Latest Stable Release](https://poggit.pmmp.io/r/255507/MineCreator_dev-9.phar)

# Support
Found a bug or have a feature request? Open an issue or pull request

---
