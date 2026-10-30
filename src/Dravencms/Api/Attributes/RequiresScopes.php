<?php declare(strict_types=1);

namespace Dravencms\Api\Attributes;

use Apitte\Core\Annotation\Controller\Tag;
use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RequiresScopes extends Tag
{
    public const TAG = 'dravencms.api.scopes';

    public function __construct(string ...$scopes)
    {
        if ($scopes === []) {
            throw new InvalidArgumentException('An API endpoint must require at least one scope.');
        }
        foreach ($scopes as $scope) {
            if (!preg_match('/^[a-z][a-z0-9_.-]*:read$/D', $scope)) {
                throw new InvalidArgumentException('Invalid read scope: ' . $scope);
            }
        }
        parent::__construct(self::TAG, implode(' ', array_unique($scopes)));
    }
}
