# Stackposts MCP Server

A Model Context Protocol (MCP) server that enables AI assistants (Claude Desktop, Antigravity IDE, Cursor, Windsurf, etc.) to manage, schedule, and publish social media content directly through your **Stackposts** platform.

---

## 🛠 Available Tools

1. **`stackposts_ping`**:
   Verify connection to the Stackposts instance and test API key permissions.
2. **`stackposts_list_accounts`**:
   List all connected social media channels (Facebook Pages, Instagram, Twitter/X, LinkedIn, TikTok, etc.) with their IDs, usernames, and status.
3. **`stackposts_list_posts`**:
   Retrieve recent posts filtered by status (`all`, `draft`, `scheduled`, `published`, `failed`).
4. **`stackposts_get_post`**:
   Fetch detailed information and publishing logs for a single post by ID.
5. **`stackposts_create_post`**:
   Draft, schedule, or immediately publish content across multiple social channels with text captions, image/video URLs, timestamps, and timezones.

---

## ⚙️ Configuration

### Environment Variables

| Variable | Description | Default |
| :--- | :--- | :--- |
| `STACKPOSTS_API_URL` | Base URL of your Stackposts application | `http://127.0.0.1:8000` |
| `STACKPOSTS_API_KEY` | Automation API key created in Stackposts | *(Required)* |

To generate an API key in Stackposts:
1. Log into your Stackposts dashboard.
2. Navigate to **Developer & API** (or **Automation API**).
3. Generate a new API Key with permissions (`posts:write`, `accounts:read`, `posts:publish`).

---

## 🚀 Setup & Build

```bash
cd mcp-server
npm install
npm run build
```

---

## 🔌 Connecting to AI Clients

### 1. Claude Desktop (`claude_desktop_config.json`)
Add the following to your Claude Desktop configuration:

```json
{
  "mcpServers": {
    "stackposts": {
      "command": "node",
      "args": [
        "/Users/mdreal/Desktop/codecanyon-Rop7GoPA-stackposts-social-marketing-tool/stackpost/mcp-server/dist/index.js"
      ],
      "env": {
        "STACKPOSTS_API_URL": "http://127.0.0.1:8000",
        "STACKPOSTS_API_KEY": "spauto_your_api_key_here"
      }
    }
  }
}
```

### 2. Antigravity IDE / Cursor / Windsurf
In your IDE settings or MCP configuration file:

```json
{
  "mcpServers": {
    "stackposts": {
      "command": "node",
      "args": ["<PATH_TO_PROJECT>/mcp-server/dist/index.js"],
      "env": {
        "STACKPOSTS_API_URL": "http://127.0.0.1:8000",
        "STACKPOSTS_API_KEY": "spauto_your_api_key_here"
      }
    }
  }
}
```

