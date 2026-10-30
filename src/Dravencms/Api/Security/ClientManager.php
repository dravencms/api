<?php declare(strict_types=1);

namespace Dravencms\Api\Security;

use Doctrine\ORM\EntityManagerInterface;
use Dravencms\Model\Api\Entities\ApiClient;
use Dravencms\Model\Api\Entities\ApiToken;

final class ClientManager
{
    public function __construct(private EntityManagerInterface $entityManager, private ScopeRegistry $scopes)
    {
    }

    public function saveClient(?ApiClient $client, string $name, array $scopes, bool $active, int $limit): ApiClient
    {
        $this->scopes->validate(array_diff($scopes, $client?->getScopes() ?? []));
        $client ??= new ApiClient($name, $scopes);
        $client->update($name, $scopes, $active, $limit);
        $this->entityManager->persist($client);
        $this->entityManager->flush();
        return $client;
    }

    public function issueToken(ApiClient $client, string $name, ?\DateTimeImmutable $expiresAt = null): string
    {
        if ($expiresAt !== null && $expiresAt <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Token expiry must be in the future.');
        }
        $secret = 'dcapi_' . bin2hex(random_bytes(32));
        $token = new ApiToken($client, $name, hash('sha256', $secret), $expiresAt);
        $this->entityManager->persist($token);
        $this->entityManager->flush();
        return $secret;
    }

    public function revokeToken(ApiToken $token): void
    {
        $token->revoke();
        $this->entityManager->flush();
    }
}
