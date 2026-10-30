<?php declare(strict_types=1);

namespace Dravencms\Api\Http;

use Apitte\Core\ErrorHandler\PsrLogErrorHandler;
use Apitte\Core\Dispatcher\DispatchError;
use Apitte\Core\Exception\ApiException;
use Apitte\Core\Exception\Runtime\SnapshotException;
use Apitte\Core\Http\ApiResponse;
use GuzzleHttp\Psr7\Utils;

final class ErrorHandler extends PsrLogErrorHandler
{
    public function handle(DispatchError $dispatchError): ApiResponse
    {
        $response = parent::handle($dispatchError)->withHeader('Cache-Control', 'private, no-store');
        $error = $dispatchError->getError();
        while ($error instanceof SnapshotException) {
            $error = $error->getPrevious();
        }
        if ($response->getStatusCode() === 401) {
            $response = $response->withHeader('WWW-Authenticate', 'Bearer realm="DravenCMS API"');
        }
        if ($response->getStatusCode() === 405) {
            $response = $response->withHeader('Allow', 'GET, HEAD');
        }
        if ($error instanceof ApiException && $response->getStatusCode() === 429) {
            $response = $response->withHeader('Retry-After', (string) ($error->getContext()['retry_after'] ?? 60));
        }
        return $dispatchError->getRequest()->getMethod() === 'HEAD'
            ? $response->withBody(Utils::streamFor('')) : $response;
    }
}
