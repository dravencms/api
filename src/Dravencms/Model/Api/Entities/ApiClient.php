<?php declare(strict_types=1);

namespace Dravencms\Model\Api\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'apiClient')]
class ApiClient
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 190, unique: true)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $requestsPerMinute = 60;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, array $scopes)
    {
        $this->update($name, $scopes, true, 60);
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function update(string $name, array $scopes, bool $isActive, int $requestsPerMinute): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 190 || $requestsPerMinute < 1 || $requestsPerMinute > 100000) {
            throw new \InvalidArgumentException('Invalid client name or request limit (1-100000).');
        }
        $this->name = $name;
        $this->scopes = array_values(array_unique($scopes));
        $this->isActive = $isActive;
        $this->requestsPerMinute = $requestsPerMinute;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getScopes(): array { return $this->scopes; }
    public function getIsActive(): bool { return $this->isActive; }
    public function getRequestsPerMinute(): int { return $this->requestsPerMinute; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
