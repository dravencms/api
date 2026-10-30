<?php declare(strict_types=1);

namespace Dravencms\Api\Controllers;

use Apitte\Core\Annotation\Controller\{Method, Path, Response};
use Apitte\Core\UI\Controller\IController;
use Apitte\OpenApi\ISchemaBuilder;
use Dravencms\Api\Attributes\RequiresScopes;

#[Path('/api/v1')]
#[RequiresScopes('api.documentation:read')]
final class OpenApiController implements IController
{
    public function __construct(private ISchemaBuilder $builder)
    {
    }

    #[Path('/openapi')]
    #[Method(['GET', 'HEAD'])]
    #[Response(code: '200', description: 'OpenAPI specification')]
    public function specification(): array
    {
        return $this->builder->build()->toArray();
    }
}
