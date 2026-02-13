# AI Mate Quick Setup

This project includes **Symfony AI Mate** - an MCP (Model Context Protocol) server that allows AI assistants to interact with your Symfony application during development.

## Quick Start

### 1. Setup MCP Configuration

```bash
# Automatically creates mcp.json and .mcp.json with your project's absolute path
castor mate:setup
```

This command:
- Copies `mcp.json.example` to `mcp.json`
- Replaces `{{ absolute_path }}` with your actual project path
- Creates `.mcp.json` symlink for auto-discovery

### 2. Ensure Services Are Running

```bash
castor docker:start
```

### 3. Test the Connection

```bash
# Test directly from terminal
castor mate:call php-version '{}'

# Or test from your AI assistant with prompts like:
# "List available Symfony services"
# "Show the latest profiler profile"
# "Search logs for errors"
```

## Available Capabilities

Once configured, your AI assistant can:

- 📊 **Access Symfony Profiler** - View HTTP requests, database queries, events, exceptions
- 📝 **Search Application Logs** - Find errors, warnings, and debug info
- 🔧 **Inspect Services** - List and explore Symfony service container
- 💻 **Get Environment Info** - PHP version, extensions, OS details

## Commands

```bash
castor mate:serve         # Start MCP server
castor mate:tools         # List available tools
castor mate:capabilities  # Show all capabilities
castor mate:call <tool> <json-params>  # Call a specific tool
```

## File Locations

- `mcp.json` - MCP client configuration (at project root, gitignored)
- `mcp.json.example` - Example configuration template
- `app/mate/` - AI Mate configuration directory

## Requirements

- **Castor** must be installed on your host machine
- **Docker** services must be running
- AI assistant with MCP support (Copilot, Claude, Cursor, etc.)

---

⚠️ **Development Only** - AI Mate is a development tool and should NOT be used in production.
