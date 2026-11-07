<?php declare(strict_types=1);

namespace Dravencms\Api\Schema;

use Apitte\Core\Schema\Endpoint;
use Apitte\OpenApi\SchemaDefinition\CoreDefinition;
use Dravencms\Api\Attributes\RequiresScopes;

final class OpenApiDefinition extends CoreDefinition
{
    protected function createOperation(Endpoint $endpoint): array
    {
        $operation = parent::createOperation($endpoint);
        $handler = $endpoint->getHandler();
        if (PublicDocumentation::matches($handler->getClass(), $handler->getMethod())) {
            $operation['security'] = [];
            unset($operation['tags']);
            return $operation;
        }
        $operation['security'] = [['BearerAuth' => []]];
        $operation['description'] = trim(($operation['description'] ?? '') . "\n\nRequired scopes: " . $endpoint->getTag(RequiresScopes::TAG));
        foreach ([400 => 'Invalid query parameters', 401 => 'Invalid or missing bearer token',
            403 => 'Insufficient scope', 404 => 'Not found', 429 => 'Rate limit exceeded', 500 => 'Internal error'] as $code => $message) {
            $operation['responses'][$code] ??= ['description' => $message];
        }
        unset($operation['tags']);
        return $operation;
    }
}
