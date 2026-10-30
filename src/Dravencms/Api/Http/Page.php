<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

final class Page extends JsonData
{
    public function __construct(
        public int $limit,
        public int $offset,
        public ?int $next_offset
    ) {
    }
}
