<?php declare(strict_types=1);

namespace Dravencms\Api\Schema;

use Apitte\Core\Exception\Logical\InvalidSchemaException;
use Apitte\Core\Schema\SchemaBuilder;
use Apitte\Core\Schema\Validation\IValidation;
use Dravencms\Api\Attributes\RequiresScopes;

final class ReadOnlyValidation implements IValidation
{
    public function validate(SchemaBuilder $builder): void
    {
        foreach ($builder->getControllers() as $controller) {
            foreach ($controller->getMethods() as $method) {
                if ($method->getPath() === '') {
                    continue;
                }
                $name = $controller->getClass() . '::' . $method->getName();
                $tags = array_merge($controller->getTags(), $method->getTags());
                $scopes = $tags[RequiresScopes::TAG] ?? '';
                $public = PublicDocumentation::matches($controller->getClass(), $method->getName());
                if (!$public && (!is_string($scopes) || $scopes === '')) {
                    throw new InvalidSchemaException($name . ' must declare RequiresScopes.');
                }
                if (!$public) {
                    new RequiresScopes(...explode(' ', $scopes));
                }
                if ($method->getHttpMethods() === [] || array_diff($method->getHttpMethods(), ['GET', 'HEAD']) !== []) {
                    throw new InvalidSchemaException($name . ' must only allow GET or HEAD.');
                }
            }
        }
    }
}
