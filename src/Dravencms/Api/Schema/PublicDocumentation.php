<?php declare(strict_types=1);

namespace Dravencms\Api\Schema;

use Dravencms\Api\Controllers\OpenApiController;

/** Only the built-in specification is public; resource endpoints always require scopes. */
final class PublicDocumentation
{
    public static function matches(string $controller, string $method): bool
    {
        return $controller === OpenApiController::class && $method === 'specification';
    }
}
