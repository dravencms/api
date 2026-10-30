<?php declare(strict_types=1);

namespace Dravencms\Api\Security;

use Apitte\Core\Exception\Api\ClientErrorException;
use Doctrine\ORM\EntityManagerInterface;
use Dravencms\Model\Api\Entities\ApiToken;

final class TokenAuthenticator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function authenticate(string $authorization): ApiToken
    {
        if (!preg_match('/^Bearer (dcapi_[a-f0-9]{64})$/iD', $authorization, $match)) {
            throw new ClientErrorException('A valid Bearer token is required.', 401);
        }
        $token = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['digest' => hash('sha256', $match[1])]);
        if (!$token instanceof ApiToken || !$token->isUsable(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))) {
            throw new ClientErrorException('Invalid, expired or revoked API token.', 401);
        }
        return $token;
    }
}
