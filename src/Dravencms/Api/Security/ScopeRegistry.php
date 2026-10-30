<?php declare(strict_types=1);

namespace Dravencms\Api\Security;

use Apitte\Core\Schema\Endpoint;
use Apitte\Core\Schema\Schema;
use Dravencms\Api\Attributes\RequiresScopes;
use InvalidArgumentException;

final class ScopeRegistry
{
    public function __construct(private Schema $schema)
    {
    }

    public function getRequired(Endpoint $endpoint): array
    {
        $scopes = $endpoint->getTag(RequiresScopes::TAG);
        if (!is_string($scopes) || $scopes === '') {
            throw new \LogicException('API endpoint has no required scopes.');
        }
        return explode(' ', $scopes);
    }

    public function getAll(): array
    {
        $scopes = [];
        foreach ($this->schema->getEndpoints() as $endpoint) {
            foreach ($this->getRequired($endpoint) as $scope) {
                $scopes[$scope] = $scope;
            }
        }
        ksort($scopes);
        return $scopes;
    }

    public function validate(array $scopes): void
    {
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !isset($this->getAll()[$scope])) {
                throw new InvalidArgumentException('Unknown API scope: ' . (is_scalar($scope) ? $scope : get_debug_type($scope)));
            }
        }
    }
}
