<?php declare(strict_types=1);

namespace Dravencms\Api\Security;

use Apitte\Core\Decorator\IRequestDecorator;
use Apitte\Core\Exception\Api\ClientErrorException;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\Http\RequestAttributes;
use Apitte\Core\Schema\Endpoint;

final class AccessDecorator implements IRequestDecorator
{
    public function __construct(
        private TokenAuthenticator $authenticator,
        private ScopeRegistry $scopes,
        private ClientRateLimiter $limiter
    ) {
    }

    public function decorateRequest(ApiRequest $request, ApiResponse $response): ApiRequest
    {
        $endpoint = $request->getAttribute(RequestAttributes::ATTR_ENDPOINT);
        if (!$endpoint instanceof Endpoint) {
            throw new \LogicException('API endpoint was not resolved.');
        }
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            throw new ClientErrorException('This API is read-only.', 405);
        }
        $token = $this->authenticator->authenticate($request->getHeaderLine('Authorization'));
        $client = $token->getClient();
        $limit = $this->limiter->consume($client);
        if (array_diff($this->scopes->getRequired($endpoint), $client->getScopes()) !== []) {
            throw new ClientErrorException('API client lacks the required scope.', 403);
        }
        return $request->withAttribute('dravencms.api.client', $client)
            ->withAttribute('dravencms.api.rateLimit', $limit);
    }

}
