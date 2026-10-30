<?php declare(strict_types=1);

namespace Dravencms\Model\Api\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'apiToken')]
class ApiToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ApiClient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ApiClient $client;

    #[ORM\Column(length: 64, unique: true)]
    private string $digest;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(ApiClient $client, string $name, string $digest, ?\DateTimeImmutable $expiresAt)
    {
        if (trim($name) === '' || mb_strlen($name) > 190 || !preg_match('/^[a-f0-9]{64}$/D', $digest)) {
            throw new \InvalidArgumentException('Invalid token name or digest.');
        }
        $this->client = $client;
        $this->name = trim($name);
        $this->digest = $digest;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return $this->client->getIsActive() && $this->revokedAt === null
            && ($this->expiresAt === null || $this->expiresAt > $now);
    }

    public function revoke(): void { $this->revokedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
    public function getId(): ?int { return $this->id; }
    public function getClient(): ApiClient { return $this->client; }
    public function getName(): string { return $this->name; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
}
