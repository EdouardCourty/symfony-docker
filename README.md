# Symfony Docker Template

A production-ready Symfony development template with Docker, featuring a clean separation between application code and development tools.

## 🎯 Overview

This template provides:
- **Clean architecture** - Application code (`app/`) separated from tooling (`tools/`)
- **Docker-first development** - All commands run in containers for consistency
- **Castor task runner** - Modern PHP task runner replacing Makefiles
- **QA tools pre-configured** - PHPStan, PHP CS Fixer, PHPUnit ready to use
- **AI-friendly documentation** - See `AGENTS.md` for coding agent guidelines

## 📁 Project Structure

```
symfony-docker/
├── app/                    # 🎯 Symfony application
│   ├── src/               # Your application code
│   ├── tests/             # Tests (Smoke, Unit, Functional)
│   ├── config/            # Symfony configuration
│   └── composer.json      # Application dependencies
│
├── tools/                  # 🔧 Development tools (not deployed)
│   ├── castor/            # Castor commands & task definitions
│   ├── phpstan.neon       # PHPStan configuration
│   ├── .php-cs-fixer.php  # PHP CS Fixer configuration
│   └── composer.json      # QA tools dependencies
│
├── infrastructure/dev/     # 🐳 Docker configuration
│   ├── network.yml        # Docker network definition
│   ├── services/          # Service definitions (database, server, proxy)
│   └── configurations/    # Service configs (nginx, php, php-fpm)
│
├── castor.php             # Castor entry point
├── AGENTS.md              # 🤖 Guidelines for AI coding agents
└── README.md              # 👋 You are here
```

**Key principles:**
- `app/` contains **only** production code
- `tools/` contains development/QA tools (excluded from deployment)
- QA configurations in `tools/` analyze `app/` code
- All Docker configs in `infrastructure/`

## 🚀 Quick Start

### Prerequisites

This project uses [Castor](https://github.com/jolicode/castor) as task runner.

**Installation:** https://castor.jolicode.com/installation/

### 1. Initialize the project

```bash
# Generate Docker configurations
castor project:init

# Install dependencies and setup everything
castor install
```

### 2. Start developing

```bash
# Start all containers
castor docker:start

# Open a shell in the container
castor bash

# Clear cache
castor cc

# Run quality checks
castor app:qa
```

### 3. Access the application

- **Web:** http://localhost:8811
- **Database:** localhost:5422 (credentials in `infrastructure/dev/services/database/.env`)

## 🔧 Common Commands

### Docker Management

```bash
castor docker:start              # Start all services
castor docker:start database     # Start specific service
castor docker:stop               # Stop all services
castor docker:down -v            # Stop and remove everything (including volumes)
castor docker:ps                 # List running containers
castor bash                      # Open shell in app container
castor bash -p tools             # Open shell in tools directory
```

### Development

```bash
castor cc                        # Clear Symfony cache
castor app:phpcs                 # Fix code style
castor app:phpstan               # Run static analysis
castor app:phpunit               # Run all tests
castor app:phpunit tests/Unit/   # Run specific test folder
castor app:qa                    # Run all QA checks
```

### Database

```bash
castor database:reload           # Rebuild database with fixtures
castor database:reload-tests     # Rebuild test database
castor database:migrate          # Run migrations
castor database:make-migration   # Create new migration
```

### Project Management

```bash
castor install               # Install all dependencies (app + tools)
castor install app           # Install only app dependencies
castor install tools         # Install only tools dependencies
castor project:init          # (Re)initialize Docker configs
```

### Worktrees

Each worktree gets its own branch, Docker Compose project, network and ports, so several stacks run side by side.

```bash
castor worktree:create <name> --path=../<name> [--base=main] [--start]  # New worktree + isolated stack
castor worktree:list                                                    # Registered worktrees, ports, status
castor worktree:destroy <name> [--delete-branch] [--force]              # Remove stack (containers, volumes, network, images) + worktree
```

`<name>`: lowercase letters, digits, `-`, `_`. State lives in `.git/castor-worktrees.json`. `destroy` is stateless: the Docker stack is removed by Compose project name, even if the directory or registry entry is gone.

## 📚 Tech Stack

### Application
- **PHP:** 8.5 (FPM Alpine)
- **Symfony:** 8.1
- **EasyAdmin:** 5.x
- **PostgreSQL:** 18 Alpine
- **Nginx:** Alpine

### Development Tools
- **Castor:** PHP task runner
- **PHPStan:** Static analysis (level 8)
- **PHP CS Fixer:** Code style fixer (PSR-12)
- **PHPUnit:** Testing framework
- **Docker Compose:** Container orchestration

## 📖 Documentation

- **`AGENTS.md`** - Best practices and guidelines for AI coding agents
- **`app/`** - Standard Symfony documentation applies
- **`tools/castor/`** - See Castor command definitions for available tasks

## 🎨 Customization

### Change Project Ports

Edit values during `castor project:init` or manually in:
- `infrastructure/dev/services/proxy/proxy.yml.dist` (Nginx port)
- `infrastructure/dev/services/database/database.yml.dist` (PostgreSQL port)

Then regenerate: `castor project:init`

### Add New Services

1. Create `infrastructure/dev/services/{service}/{service}.yml.dist`
2. Add service to `tools/castor/Enum/Service.php`
3. Update `tools/castor/Commands/Project.php` template list
4. Regenerate: `castor project:init`

### Add New Castor Commands

Create functions in `tools/castor/Commands/` with `#[AsTask]` attribute:

```php
#[AsTask(description: 'Your command description', namespace: 'app')]
function my_command(): void
{
    // Your code here
}
```

### Docker Volume Mapping

In the container:
- `app/` (local) → `/var/www/project` (container)
- `tools/` (local) → `/var/www/tools` (container)

This is why QA tool configs reference `/var/www/project` paths.

## 🤖 Working with AI Agents

This project includes `AGENTS.md` with detailed guidelines for AI coding agents.

## 📝 License

&copy; Edouard Courty - 2023-2026
