# Nelmio API Doc Integration

This project now uses Nelmio API Doc Bundle for dynamic OpenAPI documentation generation.

## Accessing the Documentation

The API documentation is available at: `http://localhost:8080/api/doc`

## Features

- **Dynamic generation**: Documentation is automatically generated from PHP attributes in controllers
- **Interactive UI**: Swagger UI interface for testing endpoints
- **OpenAPI 3.0**: Industry-standard API specification
- **Auto-discovery**: Controllers with `#[OA\...]` attributes are automatically included

## Configuration

- Bundle configuration: `config/packages/nelmio_api_doc.yaml`
- Routes configuration: `config/routes/nelmio_api_doc.yaml`
- Controllers with attributes:
  - `src/Presentation/Api/TaskApiController.php`
  - `src/Presentation/Api/UserApiController.php`
  - `src/Presentation/Api/AuthApiController.php`

## Adding Documentation to New Endpoints

Use OpenAPI attributes on your controller methods:

```php
use OpenApi\Attributes as OA;

#[Route('/api/example', name: 'api_example_')]
#[OA\Tag(name: 'Example')]
class ExampleController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(
        path: '/api/example',
        summary: 'Get examples',
        tags: ['Example']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of examples'
    )]
    public function list(): JsonResponse
    {
        // ...
    }
}
```

## Installation Note

The `nelmio/api-doc-bundle` dependency has been added to `composer.json`. Run `composer install` to install it.
