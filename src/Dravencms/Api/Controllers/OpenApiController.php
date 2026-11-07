<?php declare(strict_types=1);

namespace Dravencms\Api\Controllers;

use Apitte\Core\Annotation\Controller\{Method, Path, Response};
use Apitte\Core\UI\Controller\IController;
use Apitte\OpenApi\ISchemaBuilder;

#[Path('/api/v1')]
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
