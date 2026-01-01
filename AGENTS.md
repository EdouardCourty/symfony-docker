# Development Best Practices

This document outlines the development best practices and conventions for this Symfony project.

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

#[ORM\Column(type: Types::BOOLEAN)]
private bool $enabled;

#[ORM\Column(type: Types::JSON)]
private array $data;

#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
private \DateTimeImmutable $createdAt;

#[ORM\Column(type: Types::DATETIME_MUTABLE)]
private \DateTime $updatedAt;

#[ORM\Column(type: Types::TEXT)]
private string $description;

#[ORM\Column(type: Types::GUID)]
private string $uuid;
```

### ❌ Bad Practice

```php
#[ORM\Column(type: 'string', length: 255)]
private string $name;

#[ORM\Column(type: 'integer')]
private int $age;

#[ORM\Column(type: 'boolean')]
private bool $enabled;

#[ORM\Column(type: 'json')]
private array $data;
```

### Available Types

- `Types::STRING` - VARCHAR
- `Types::INTEGER` - INT
- `Types::SMALLINT` - SMALLINT
- `Types::BIGINT` - BIGINT
- `Types::BOOLEAN` - BOOLEAN
- `Types::DECIMAL` - DECIMAL
- `Types::FLOAT` - FLOAT
- `Types::TEXT` - TEXT
- `Types::JSON` - JSON
- `Types::DATETIME_MUTABLE` - DATETIME (mutable DateTime)
- `Types::DATETIME_IMMUTABLE` - DATETIME (immutable DateTimeImmutable)
- `Types::DATE_MUTABLE` - DATE (mutable DateTime)
- `Types::DATE_IMMUTABLE` - DATE (immutable DateTimeImmutable)
- `Types::TIME_MUTABLE` - TIME (mutable DateTime)
- `Types::TIME_IMMUTABLE` - TIME (immutable DateTimeImmutable)
- `Types::GUID` - GUID/UUID
- `Types::BINARY` - BLOB
- `Types::BLOB` - BLOB
- `Types::SIMPLE_ARRAY` - Simple array
- `Types::ARRAY` - PHP array (serialized)

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
return new JsonResponse(null, Response::HTTP_NO_CONTENT);            // 204

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
    public function testHealthCheckEndpoint(): void
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

- ✅ Always use `Types::*` constants for Doctrine column types
- ✅ Always use `Request::METHOD_*` constants for HTTP methods
- ✅ Always use `Response::HTTP_*` constants for HTTP status codes
- ✅ Place reusable business logic in Action classes in `src/Action`
- ✅ Organize tests into Smoke, Unit, and Functional directories
- ✅ Keep code maintainable, testable, and following Symfony best practices
