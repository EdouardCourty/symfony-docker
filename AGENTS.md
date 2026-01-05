# Development Best Practices

This document outlines the development best practices and conventions for this Symfony project.

## Table of Contents

- [Docker-First Development](#docker-first-development)
- [Understanding the Makefile](#understanding-the-makefile)
- [Development Workflow](#development-workflow)
- [Coding Guidelines](#coding-guidelines)
- [EasyAdmin](#easyadmin)
- [Reusable Business Logic](#reusable-business-logic)
- [Testing Structure](#testing-structure)

## Docker-First Development

**⚠️ CRITICAL: All project binaries MUST be executed through Docker containers via the Makefile.**

Ensures consistent environment, correct PHP version/extensions, and isolated dependencies.

**Never run binaries directly:** `php bin/console`, `vendor/bin/phpunit`, `composer install`  
**Always use Makefile:** `make cc`, `make phpunit`, `make phpstan`, `make vendor-install`

## Understanding the Makefile

The Makefile wraps Docker Compose commands executed inside the `server` container. Run `make help` for all commands.

**Key commands:**
- Docker: `make up`, `make stop`, `make down`, `make bash`
- Setup: `make install`, `make vendor-install`, `make cc`, `make reload-database`, `make migrate`
- Quality: `make phpcs`, `make phpstan`, `make phpunit`

**Pass arguments:** `make phpunit tests/Unit/SomeTest.php`

## Development Workflow

**Quality Checklist (must pass before committing):**
```bash
make phpcs   # Fix code style (PSR-12)
make phpstan # Check type errors
make phpunit # Run tests (use `make reload-tests` if needed)
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
**Note:** `make phpcs` auto-organizes imports.

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
Fast sanity checks (health checks, critical endpoints, boot verification).

### 2. Unit Tests (`tests/Unit/`)
Test individual classes/methods in isolation using mocks. Focus on business logic.

### 3. Functional Tests (`tests/Functional/`)
Test complete workflows with real database and HTTP requests (API endpoints, form submissions).

## Summary

- ✅ **ALWAYS use Docker via Makefile** - Never run binaries directly
- ✅ **Run quality checks before commit** - `make phpcs`, `make phpstan`, `make phpunit`
- ✅ **Ask for clarification when unclear** - Better to ask than develop incorrectly
- ✅ **Use FQCN with `use` statements** - Never inline FQCN or omit imports
- ✅ **Use constants** - `Types::*`, `Request::METHOD_*`, `Response::HTTP_*` (no strings/magic numbers)
- ✅ **Place business logic in Action classes** - `src/Action` directory
- ✅ **Organize tests** - Smoke, Unit, Functional directories
