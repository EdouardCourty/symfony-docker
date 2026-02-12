# Development Best Practices

This document outlines the development best practices and conventions for this Symfony project.

**⚠️ Keep this document concise and precise. No fluff.**

## Table of Contents

- [Project Structure](#project-structure)
- [Docker-First Development](#docker-first-development)
- [Castor Task Runner](#castor-task-runner)
- [Development Workflow](#development-workflow)
- [Coding Guidelines](#coding-guidelines)
- [EasyAdmin](#easyadmin)
- [Reusable Business Logic](#reusable-business-logic)
- [Testing Structure](#testing-structure)

## Project Structure

```
symfony-docker/
├── app/                    # Symfony application
│   ├── src/               # Application code
│   ├── tests/             # Tests (Smoke, Unit, Functional)
│   ├── config/            # Symfony configuration
│   └── composer.json      # App dependencies
├── tools/                  # Development tools
│   ├── castor/            # Castor commands & builders
│   │   ├── Commands/      # Task definitions (App.php, Docker.php, Project.php)
│   │   ├── Builder/       # DockerCommandBuilder
│   │   └── Enum/          # Service, ProjectFolder enums
│   ├── phpstan.neon       # PHPStan config (analyzes app/)
│   ├── .php-cs-fixer.php  # PHP CS Fixer config (analyzes app/)
│   └── composer.json      # Tools dependencies
└── infrastructure/dev/
    ├── network.yml         # Docker network definition
    ├── services/           # Service definitions
    │   ├── database/       # PostgreSQL service + .env
    │   ├── server/         # PHP-FPM service
    │   └── proxy/          # Nginx service
    └── configurations/     # Service configurations
        ├── nginx/          # Nginx config
        ├── php/            # PHP Dockerfile + config
        └── php-fpm/        # PHP-FPM config
```

**Key principles:**
- ✅ `app/` contains ONLY Symfony code
- ✅ `tools/` contains QA tools and Castor commands
- ✅ QA configs in `tools/` analyze `app/` code (keeps app/ clean)

## Docker-First Development

**⚠️ CRITICAL: All project binaries MUST be executed through Docker containers via Castor.**

Ensures consistent environment, correct PHP version/extensions, and isolated dependencies.

**Never run binaries directly:** `php bin/console`, `vendor/bin/phpunit`, `composer install`  
**Always use Castor:** `castor cc`, `castor app:phpunit`, `castor app:phpstan`, `castor install`

## Castor Task Runner

Castor replaces the Makefile. All commands are defined in `tools/castor/Commands/`.

### Key Commands

**Project setup:**
```bash
castor project:init          # Initialize project (generate Docker configs)
castor install [app|tools|all]  # Install dependencies & setup
```

**Docker management:**
```bash
castor docker:start [services]  # Start all or specific services
castor docker:stop [services]   # Stop all or specific services
castor docker:down [-v]         # Stop & remove (--volumes to delete data)
castor bash [-p app|tools]      # Open shell in container
castor docker:ps                # List running containers
```

**Development:**
```bash
castor cc                    # Clear Symfony cache
castor app:phpcs            # Fix code style (PSR-12)
castor app:phpstan          # Static analysis
castor app:phpunit [path]   # Run tests (optional: specific file or --filter)
castor app:qa               # Run all QA checks (phpcs + phpstan + phpunit)
```

**Database:**
```bash
castor database:reload           # Drop, create, migrate, load fixtures
castor database:reload-tests     # Same for test DB
castor database:migrate          # Run migrations
castor database:make-migration   # Create new migration
```

### Command Structure

Commands are organized by namespace in `tools/castor/Commands/`:

- **`App.php`** - Application commands (`app:*`, `cache:*`, quality tools)
- **`Docker.php`** - Docker management (`docker:*`, `bash`)
- **`Project.php`** - Project initialization (`project:init`)

**Example command:**
```php
#[AsTask(description: 'Clear Symfony cache', namespace: 'cache', aliases: ['cc'])]
function clear(): void
{
    (new DockerCommandBuilder())
        ->withAllServices()
        ->service('server')
        ->exec('php bin/console cache:clear');
}
```

### Configurations

- **PHPStan:** `tools/phpstan.neon` - Analyzes `/var/www/project` (app/ mounted in container)
- **PHP CS Fixer:** `tools/.php-cs-fixer.php` - Analyzes `/var/www/project` (app/ mounted in container)
- **Docker services:** `infrastructure/dev/services/{service}/{service}.yml`
- **Service enum:** `tools/castor/Enum/Service.php` - Knows all service paths

**Note:** In the container, `app/` is mounted as `/var/www/project` and `tools/` as `/var/www/tools`.

## Development Workflow

**Quality Checklist (must pass before committing):**
```bash
castor app:phpcs   # Fix code style (PSR-12)
castor app:phpstan # Check type errors
castor app:phpunit # Run tests (use `castor database:reload-tests` if needed)
```

**Or run all at once:**
```bash
castor app:qa      # Runs phpcs, phpstan, phpunit
```

**Steps:** Develop → Write tests → Run quality checks

**When in doubt, ASK!** Better to clarify requirements than develop incorrectly.

## Coding Guidelines

### Fully Qualified Class Names (FQCN)

**⚠️ CRITICAL: ALWAYS use FQCN with proper `use` statements. Never use inline `\Full\Class\Name` in code.**

```php
<?php

declare(strict_types=1);

namespace App\Controller;

// ✅ Import at top, use short name
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController
{
    public function list(UserRepository $repo): Response
    {
        return new JsonResponse($repo->findAll(), Response::HTTP_OK);
    }
}
```

**Organize `use` statements:** Symfony → Doctrine → Third-party → App namespace (separated by blank lines).  
**Note:** `castor app:phpcs` auto-organizes imports.

### Doctrine Type Constants

**Use `Types` constants, not string literals:**

```php
use Doctrine\DBAL\Types\Types;

#[ORM\Column(type: Types::STRING, length: 255)]  // ✅
#[ORM\Column(type: 'string', length: 255)]       // ❌
```

**Common types:** `STRING`, `TEXT`, `INTEGER`, `BIGINT`, `BOOLEAN`, `JSON`, `DATETIME_IMMUTABLE`, `GUID`

### HTTP Methods and Status Codes

**Use constants, not strings or magic numbers:**

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Route(path: '/api/users', methods: Request::METHOD_POST)]  // ✅
return new JsonResponse($data, Response::HTTP_CREATED);      // ✅

#[Route(path: '/api/users', methods: 'POST')]                // ❌
return new JsonResponse($data, 201);                         // ❌
```

**Common methods:** `METHOD_GET`, `METHOD_POST`, `METHOD_PUT`, `METHOD_DELETE`, `METHOD_PATCH`  
**Common status codes:** `HTTP_OK` (200), `HTTP_CREATED` (201), `HTTP_NO_CONTENT` (204), `HTTP_BAD_REQUEST` (400), `HTTP_UNAUTHORIZED` (401), `HTTP_FORBIDDEN` (403), `HTTP_NOT_FOUND` (404), `HTTP_UNPROCESSABLE_ENTITY` (422)

## EasyAdmin

Admin interface at `/admin` (requires `ROLE_ADMIN`).

**Structure:**
- `DashboardController` - Entry point with menu configuration
- `src/Controller/Admin/CRUD/*CrudController.php` - Entity CRUD controllers extending `AbstractCrudController`

**CRUD Controller essentials:**
```php
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string {
        return User::class;
    }
    
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['username'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }
    
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('username');
        yield TextField::new('password')->onlyOnForms();
    }
}
```

**Custom persistence:** Override `persistEntity()` / `updateEntity()` for logic like password hashing.

**Custom actions:** Use `#[AdminRoute]` (not `#[Route]` or deprecated `#[AdminAction]`):
```php
#[AdminRoute(path: '/{entityId}/approve', name: 'approve')]
public function approve(AdminContext $context): Response { /* ... */ }
```
Path and name are relative to dashboard/CRUD routes.

## Reusable Business Logic

**Place complex business operations in Action classes in `src/Action/` directory.**

Actions should be:
- Single-purpose with clear input/output
- Easily testable
- Reusable across controllers, commands, event listeners

**Structure:**
```
src/Action/
  User/CreateUserAction.php
  Order/ProcessOrderAction.php
```

**Example:**
```php
namespace App\Action\User;

final class CreateUserAction
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function execute(string $username, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
}
```

**Usage in controller:**
```php
#[Route(path: '/api/users', methods: Request::METHOD_POST)]
public function create(Request $request, CreateUserAction $action): Response
{
    $data = json_decode($request->getContent(), true);
    $user = $action->execute($data['username'], $data['password']);
    
    return new JsonResponse(['id' => $user->getId()], Response::HTTP_CREATED);
}
```

## Testing Structure

**Three test categories:**

### 1. Smoke Tests (`tests/Smoke/`)
Fast sanity checks - verify pages/endpoints load without errors (HTTP 200/201/etc). NO business logic testing.

### 2. Unit Tests (`tests/Unit/`)
Test individual classes/methods in isolation using mocks. Focus on business logic.

### 3. Functional Tests (`tests/Functional/`)
Test complete workflows with real database and HTTP requests (API endpoints, form submissions).

**⚠️ NO TEST REDUNDANCY:** Test each logic ONCE at appropriate level. Smoke = loading only. Unit = isolated logic. Functional = complete workflows.

## Summary

- ✅ **ALWAYS use Docker via Castor** - Never run binaries directly
- ✅ **Run quality checks before commit** - `castor app:qa` or individual commands
- ✅ **Ask for clarification when unclear** - Better to ask than develop incorrectly
- ✅ **Use FQCN with `use` statements** - Never inline FQCN or omit imports
- ✅ **Use constants** - `Types::*`, `Request::METHOD_*`, `Response::HTTP_*` (no strings/magic numbers)
- ✅ **Place business logic in Action classes** - `app/src/Action` directory
- ✅ **Organize tests** - Smoke, Unit, Functional directories (NO redundancy)
