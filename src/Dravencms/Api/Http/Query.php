<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

use Apitte\Core\Exception\Api\ClientErrorException;

final class Query
{
    public function __construct(private array $parameters, array $allowed)
    {
        if (array_diff(array_keys($parameters), $allowed) !== []) {
            throw new ClientErrorException('Unknown query parameter. Allowed: ' . implode(', ', $allowed));
        }
        foreach ($parameters as $value) {
            if (!is_scalar($value)) {
                throw new ClientErrorException('Query parameters must be scalar values.');
            }
        }
    }

    public function integer(string $name, int $default, int $min, int $max): int
    {
        if (!array_key_exists($name, $this->parameters)) {
            return $default;
        }
        $raw = (string) $this->parameters[$name];
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new ClientErrorException(sprintf('%s must be an integer from %d to %d.', $name, $min, $max));
        }
        return $value;
    }

    public function boolean(string $name): ?bool
    {
        if (!array_key_exists($name, $this->parameters)) {
            return null;
        }
        return match ((string) $this->parameters[$name]) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new ClientErrorException($name . ' must be true or false.'),
        };
    }

    public function text(string $name): ?string
    {
        return isset($this->parameters[$name]) ? (string) $this->parameters[$name] : null;
    }

    public function date(string $name): ?\DateTimeImmutable
    {
        $value = $this->text($name);
        if ($value === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ClientErrorException($name . ' must be a date in YYYY-MM-DD format.');
        }
        return $date;
    }
}
