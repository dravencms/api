<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

abstract class JsonData implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
