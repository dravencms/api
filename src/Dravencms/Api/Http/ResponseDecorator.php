<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

use Apitte\Core\Decorator\IResponseDecorator;
use Apitte\Core\Http\{ApiRequest, ApiResponse};
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\RateLimiter\RateLimit;

final class ResponseDecorator implements IResponseDecorator
{
    public function decorateResponse(ApiRequest $request, ApiResponse $response): ApiResponse
    {
        $response = $response->withHeader('Cache-Control', 'private, no-store');
        $limit = $request->getAttribute('dravencms.api.rateLimit');
        if ($limit instanceof RateLimit) {
            $response = $response->withHeader('X-RateLimit-Limit', (string) $limit->getLimit())
                ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
        }
        return $request->getMethod() === 'HEAD' ? $response->withBody(Utils::streamFor('')) : $response;
    }
}
