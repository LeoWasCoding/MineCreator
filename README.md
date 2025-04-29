# ⛏️ MineCreator – PocketMine-MP Mines Plugin

MineCreator is a powerful and user-friendly PocketMine-MP plugin that allows server owners to create and manage custom mines with automatic reset functionality. Built with performance and ease-of-use in mind, AutoMine features intuitive forms, percentage-based block filling, and automatic region resets with warning messages.

---

## 🧩 Features

- 🟢 Interactive **FormAPI-based GUI** for all mine operations  
- 📦 **Region Selection System** using two block break positions  
- 🕒 **Auto-Reset Scheduler** with configurable delay  
- 🧱 **Block Fill Logic** using percentage weights (e.g., stone 50%, coal 30%)  
- 📢 **Pre-Reset Warnings** to alert players  
- 🔁 **Manual or Automatic Reset** options  
- 📁 **Persistent Configuration** using JSON  
- 🧼 resets a mine if it is empty

---

## 🧪 Requirements

- **FormAPI:** Required dependency  

---

## 📥 Installation

Coming Soon.....

---

## 🚀 Commands & Usage

### `/mine`
- ➕ `create` Creating new mines
- ✏️ `edit` Editing existing mines
- ❌ `delete` Deleting mines
- 🔁 `reset` Manually resetting mines

### Creating a Mine
1. Use `/mine` and select **position**.
2. You'll be asked to break two blocks to define a region.
3. After selecting region, You have the option to cancel the selected region to create a new one if you made a mistake/etc..
4. Use `/mine create` to open the form
5. Fill out the form:
   - Mine name
   - Blocks & percentages (e.g., `stone,50,coal_ore,30,iron_ore,20`)
   - Auto-reset time (in seconds)

### Reset Warnings
Players in the world where the mine is in will receive a warning 5 seconds before reset.
You can toggle it with /minewarn on/off

---

## 📁 Configuration Structure

Stored in `plugin_data/AutoMine/mines.json`:

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
