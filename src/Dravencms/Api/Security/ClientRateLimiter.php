<?php declare(strict_types=1);

namespace Dravencms\Api\Security;

use Apitte\Core\Exception\Api\ClientErrorException;
use Dravencms\Model\Api\Entities\ApiClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class ClientRateLimiter
{
    private CacheStorage $storage;
    private LockFactory $locks;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create API rate limiter directory.');
        }
        $this->storage = new CacheStorage(new FilesystemAdapter('dravencms_api', 0, $directory));
        $this->locks = new LockFactory(new FlockStore($directory));
    }

    public function consume(ApiClient $client): RateLimit
    {
        $factory = new RateLimiterFactory([
            'id' => 'client_' . $client->getId(),
            'policy' => 'fixed_window',
            'limit' => $client->getRequestsPerMinute(),
            'interval' => '1 minute',
        ], $this->storage, $this->locks);
        $limit = $factory->create()->consume();
        if (!$limit->isAccepted()) {
            throw new ClientErrorException('API request limit exceeded.', 429, context: [
                'retry_after' => max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }
        return $limit;
    }
}
