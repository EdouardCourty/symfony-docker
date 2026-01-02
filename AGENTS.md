# Development Best Practices

This document outlines the development best practices and conventions for this Symfony project.

## Table of Contents

- [Docker-First Development](#docker-first-development)
- [Understanding the Makefile](#understanding-the-makefile)
- [Development Workflow](#development-workflow)
- [EasyAdmin](#easyadmin)
- [Doctrine Type Constants](#doctrine-type-constants)
- [HTTP Methods and Status Codes](#http-methods-and-status-codes)
- [Reusable Business Logic](#reusable-business-logic)
- [Testing Structure](#testing-structure)

## Docker-First Development

**⚠️ CRITICAL: All project binaries MUST be executed through Docker containers via the Makefile.**

### Why Docker Only?

This project uses Docker to ensure:
- Consistent development environment across all machines
- Correct PHP version and extensions
- Proper database configuration
- Isolated dependencies

### ❌ Never Do This

```bash
# DON'T run binaries directly on your host machine
php bin/console cache:clear
vendor/bin/phpunit
vendor/bin/phpstan
composer install
```

### ✅ Always Do This

```bash
# Use the Makefile to execute commands in Docker containers
make cc                    # Clear cache
make phpunit              # Run tests
make phpstan              # Run static analysis
make vendor-install       # Install dependencies
```

## Understanding the Makefile

The Makefile is your primary interface for all project operations. It wraps Docker commands and ensures everything runs in the correct containerized environment.

### Key Concepts

- All commands use Docker Compose
- Commands are executed inside the `server` container via `docker compose exec server`
- The Makefile provides convenient shortcuts for common tasks

### Available Commands

Run `make help` to see all available commands. Key categories:

#### 🐳 Docker Management
- `make up` - Start containers
- `make stop` - Stop containers
- `make down` - Stop and remove containers & volumes
- `make build` - Build Docker images
- `make restart` - Restart containers
- `make bash` - Access container shell

#### 🌐 Project Setup
- `make install` - Complete project setup from scratch
- `make vendor-install` - Install PHP dependencies
- `make cc` - Clear Symfony cache
- `make reload-database` - Reset database with fixtures
- `make reload-tests` - Reset test database
- `make migrate` - Run migrations
- `make make-mig` - Create new migration

#### ⛩️ Code Quality & Testing
- `make phpcs` - Fix code style (PSR-12)
- `make phpunit` - Run PHPUnit tests
- `make phpstan` - Run static analysis

### Passing Arguments

Some commands accept arguments:

```bash
# Run specific test file
make phpunit tests/Unit/SomeTest.php
```

## Development Workflow

When developing a new feature, **ALWAYS** follow this quality checklist:

### ✅ Quality Checklist

Before considering a feature complete, run these three commands:

```bash
make phpcs   # Fix code style issues
make phpstan # Check for type errors and bugs
make phpunit # Ensure all tests pass (you can reload the database with `make reload-tests` if needed))
```

**All three must pass successfully.** This ensures:
- Code follows PSR-12 standards
- No type errors or potential bugs
- All existing functionality still works
- New code is properly tested

### Development Steps

1**Develop your feature**
2**Write tests** (Unit, Functional, or both)
3**Run quality checks**:
   ```bash
   make phpcs
   make phpstan
   make phpunit
   ```

## When in Doubt, Ask!

**⚠️ IMPORTANT: If anything is unclear about the requirements, implementation approach, or expected behavior, ALWAYS ask for clarification before proceeding.**

Better to ask questions than to develop something that doesn't meet the actual requirements. This saves time and ensures accuracy.

### Questions to Ask

- "Should this endpoint return a 200 or 201 status code?"
- "Should this validation be in the controller or in an Action class?"
- "What should happen if the user is not authenticated?"
- "Should this be a unit test or a functional test?"
- "Is this the correct HTTP method for this operation?"
- "Are there any edge cases I should consider?"

## EasyAdmin

This project uses **EasyAdmin 4** for the administration interface. The admin panel is accessible at `/admin` and is protected by `ROLE_ADMIN`.

### Structure

```
src/Controller/Admin/
├── DashboardController.php      # Main dashboard
└── CRUD/
    └── UserCrudController.php   # CRUD controllers for entities
```

### Dashboard Controller

The `DashboardController` is the entry point for the admin interface:

```php
#[Route('/admin', name: 'admin')]
public function index(): Response
{
    return $this->render('admin/dashboard.html.twig', [
        'stats' => [
            'users' => $this->userRepository->count([]),
        ],
    ]);
}

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield MenuItem::section('Users');
    yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class)
        ->setController('App\\Controller\\Admin\\CRUD\\UserCrudController');
}
```

### CRUD Controllers

CRUD controllers extend `AbstractCrudController` and handle entity management:

```php
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setSearchFields(['username'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('username', 'Username');
        yield TextField::new('password', 'Password')
            ->onlyOnForms()
            ->setRequired($pageName === Crud::PAGE_NEW);
        // ...
    }
}
```

### Customizing Entity Persistence

Override `persistEntity()` and `updateEntity()` for custom logic (e.g., password hashing):

```php
public function __construct(
    private readonly UserPasswordHasherInterface $passwordHasher,
) {
}

public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
{
    if (!$entityInstance instanceof User) {
        return;
    }

    $plainPassword = $entityInstance->getPassword();
    if ($plainPassword) {
        $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
        $entityInstance->setPassword($hashedPassword);
    }

    parent::persistEntity($entityManager, $entityInstance);
}
```

### Custom Actions

**IMPORTANT**: Custom controller actions MUST use the `#[AdminRoute]` attribute to work properly with EasyAdmin:

```php
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute(path: '/custom-action', name: 'custom_action')]
public function customAction(AdminUrlGenerator $adminUrlGenerator): Response
{
    // Your custom logic here
    return $this->redirectToRoute('admin');
}
```

**Key points about `#[AdminRoute]`**:

- **No need for `#[Route]` attribute**: `#[AdminRoute]` replaces Symfony's `#[Route]` entirely for EasyAdmin controllers
- **Path is relative**: The `path` is appended to the dashboard and CRUD paths (e.g., `/admin/product/custom-action`)
- **Name is relative**: The `name` is appended to the dashboard and CRUD route names (e.g., `admin_product_custom_action`)
- **`#[AdminAction]` is deprecated**: Since EasyAdmin 4.25.0, use `#[AdminRoute]` instead (AdminAction will be removed in 5.0.0)

**Example with entity-specific action**:

```php
#[AdminRoute(path: '/{entityId}/approve', name: 'approve')]
public function approve(AdminContext $context): Response
{
    $user = $context->getEntity()->getInstance();
    // Approve user logic...
    
    return $this->redirect($context->getReferrer());
}
```

Without `#[AdminRoute]`, the action won't be recognized as part of the admin interface and won't have proper routing.

### Key Features

- **Field Types**: `TextField`, `BooleanField`, `DateTimeField`, `ArrayField`, `ChoiceField`, `AssociationField`, etc.
- **Field Display Control**: `onlyOnForms()`, `onlyOnIndex()`, `onlyOnDetail()`, `hideOnForm()`, `hideOnIndex()`
- **Actions**: Configure available actions with `configureActions()` - add, remove, or customize CRUD actions
- **Search**: Define searchable fields with `setSearchFields()`
- **Sorting**: Set default sort order with `setDefaultSort()`

## Doctrine Type Constants

Always use Doctrine's `Types` class constants for column type declarations instead of string literals.

### ✅ Good Practice

```php
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Column(type: Types::STRING, length: 255)]
private string $name;

#[ORM\Column(type: Types::INTEGER)]
private int $age;
```

### ❌ Bad Practice

```php
#[ORM\Column(type: 'string', length: 255)]
private string $name;

#[ORM\Column(type: 'json')]
private array $data;
```

### Common Types

- `Types::STRING`, `Types::TEXT` - Text data
- `Types::INTEGER`, `Types::BIGINT` - Numbers
- `Types::BOOLEAN` - Boolean values
- `Types::JSON` - JSON data
- `Types::DATETIME_IMMUTABLE`, `Types::DATETIME_MUTABLE` - Date and time
- `Types::GUID` - GUID identifiers

See the [Doctrine DBAL Types Class](vendor/doctrine/dbal/src/Types/Types.php) for the complete list.

## HTTP Methods and Status Codes

Always use Symfony constants for HTTP methods and status codes instead of string literals or magic numbers.

### HTTP Methods

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/users', methods: Request::METHOD_GET)]
#[Route(path: '/api/users', methods: Request::METHOD_POST)]
#[Route(path: '/api/users/{id}', methods: Request::METHOD_PUT)]
#[Route(path: '/api/users/{id}', methods: Request::METHOD_DELETE)]
#[Route(path: '/api/users/{id}', methods: Request::METHOD_PATCH)]

// Multiple methods
#[Route(path: '/api/users', methods: [Request::METHOD_GET, Request::METHOD_POST])]
```

Available method constants in `Request`:
- `Request::METHOD_GET`
- `Request::METHOD_POST`
- `Request::METHOD_PUT`
- `Request::METHOD_DELETE`
- `Request::METHOD_PATCH`
- `Request::METHOD_HEAD`
- `Request::METHOD_OPTIONS`

### HTTP Status Codes

```php
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

// Success responses
return new JsonResponse($data, Response::HTTP_OK);                    // 200
return new JsonResponse($data, Response::HTTP_CREATED);               // 201
return new JsonResponse(null, Response::HTTP_NO_CONTENT);             // 204

// Client error responses
return new JsonResponse($error, Response::HTTP_BAD_REQUEST);          // 400
return new JsonResponse($error, Response::HTTP_UNAUTHORIZED);         // 401
return new JsonResponse($error, Response::HTTP_FORBIDDEN);            // 403
return new JsonResponse($error, Response::HTTP_NOT_FOUND);            // 404
return new JsonResponse($error, Response::HTTP_CONFLICT);             // 409
return new JsonResponse($error, Response::HTTP_UNPROCESSABLE_ENTITY); // 422

// Server error responses
return new JsonResponse($error, Response::HTTP_INTERNAL_SERVER_ERROR); // 500
return new JsonResponse($error, Response::HTTP_SERVICE_UNAVAILABLE);   // 503
```

## Reusable Business Logic

Business logic should be reusable and properly organized. Place complex business operations in dedicated Action classes within the `src/Action` directory.

### Action Pattern

Actions are single-purpose classes that encapsulate a specific business operation. They should:

1. Be focused on a single responsibility
2. Have clear input and output
3. Be easily testable
4. Be reusable across different contexts (controllers, commands, event listeners)

### Example Structure

```
src/
  Action/
    User/
      CreateUserAction.php
      UpdateUserAction.php
      DeleteUserAction.php
    Order/
      ProcessOrderAction.php
      CancelOrderAction.php
```

### Example Action Class

```php
<?php

declare(strict_types=1);

namespace App\Action\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserAction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function execute(string $username, string $plainPassword, array $roles = []): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setRoles($roles);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }
}
```

### Using Actions in Controllers

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Action\User\CreateUserAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route(path: '/api/users', methods: Request::METHOD_POST)]
    public function create(Request $request, CreateUserAction $action): Response
    {
        $data = json_decode($request->getContent(), true);
        
        $user = $action->execute(
            $data['username'],
            $data['password'],
            $data['roles'] ?? []
        );
        
        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
        ], Response::HTTP_CREATED);
    }
}
```

## Testing Structure

Tests are organized into three main categories:

### 1. Smoke Tests (`tests/Smoke/`)

Quick sanity checks to ensure the application is running and basic functionality works.

- Test basic health checks
- Test that critical endpoints are accessible
- Test that the application boots correctly
- Should be fast and run first in CI/CD

```php
<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckTest extends WebTestCase
{
    public function testHealthCheckEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthcheck');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
```

### 2. Unit Tests (`tests/Unit/`)

Test individual classes and methods in isolation.

- Test single units of code (classes, methods)
- Use mocks and stubs for dependencies
- Should be fast and independent
- Focus on business logic

```php
<?php

namespace App\Tests\Unit\Action\User;

use App\Action\User\CreateUserAction;
use PHPUnit\Framework\TestCase;

class CreateUserActionTest extends TestCase
{
    public function testExecuteCreatesUser(): void
    {
        // Unit test implementation
    }
}
```

### 3. Functional Tests (`tests/Functional/`)

Test the application's features from an end-user perspective.

- Test complete workflows
- Test with real database (use test environment)
- Test API endpoints with real HTTP requests
- Test form submissions

```php
<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends WebTestCase
{
    public function testCreateUser(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'testuser',
            'password' => 'password123',
        ]));
        
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
```

## Summary

- ✅ **ALWAYS use Docker containers via Makefile** - Never run binaries directly
- ✅ **Run quality checks before committing** - `make phpcs`, `make phpstan`, `make phpunit`
- ✅ **Ask for clarification when unclear** - Better to ask than develop incorrectly
- ✅ Always use `Types::*` constants for Doctrine column types
- ✅ Always use `Request::METHOD_*` constants for HTTP methods
- ✅ Always use `Response::HTTP_*` constants for HTTP status codes
- ✅ Place reusable business logic in Action classes in `src/Action`
- ✅ Organize tests into Smoke, Unit, and Functional directories
- ✅ Keep code maintainable, testable, and following Symfony best practices
