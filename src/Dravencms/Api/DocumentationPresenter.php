<?php declare(strict_types=1);

namespace Dravencms\Api;

use Contributte\OpenApi\Tracy\SwaggerPanel;
use Nette\Application\{BadRequestException, IPresenter, Request, Response};
use Nette\Application\Responses\{CallbackResponse, FileResponse, TextResponse};
use Nette\Http\{IRequest, IResponse};

final class DocumentationPresenter implements IPresenter
{
    public function __construct(private IRequest $httpRequest, private IResponse $httpResponse)
    {
    }

    public function run(Request $request): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            $this->httpResponse->setHeader('Allow', 'GET, HEAD');
            throw new BadRequestException('Method not allowed.', 405);
        }
        $this->httpResponse->setHeader('X-Content-Type-Options', 'nosniff');
        $this->httpResponse->setHeader('X-Robots-Tag', 'noindex, nofollow');
        $action = $request->getParameter('action', 'default');
        if ($action === 'asset') {
            return $this->asset($request->getParameter('asset'));
        }
        if ($action !== 'default') {
            throw new BadRequestException();
        }
        $this->httpResponse->setContentType('text/html', 'utf-8');
        $this->httpResponse->setHeader('Cache-Control', 'private, no-store');
        $this->httpResponse->setHeader('Referrer-Policy', 'no-referrer');
        $basePath = $this->httpRequest->getUrl()->getBasePath();
        $assetBase = $basePath . 'api/docs/assets/';
        $specificationUrl = $basePath . 'api/v1/openapi';
        return new CallbackResponse(static function () use ($request, $assetBase, $specificationUrl): void {
            if (!$request->isMethod('HEAD')) {
                require __DIR__ . '/Documentation/default.phtml';
            }
        });
    }

    private function asset(mixed $name): Response
    {
        $bundled = dirname((new \ReflectionClass(SwaggerPanel::class))->getFileName()) . '/templates/assets/';
        $assets = [
            'swagger-ui-css' => [$bundled . 'swagger-ui.css', 'text/css'],
            'swagger-ui-bundle-js' => [$bundled . 'swagger-ui-bundle.js', 'application/javascript'],
            'swagger-init-js' => [__DIR__ . '/Documentation/swagger-init.js', 'application/javascript'],
            'documentation-css' => [__DIR__ . '/Documentation/documentation.css', 'text/css'],
        ];
        if (!is_string($name) || !isset($assets[$name])) {
            throw new BadRequestException();
        }
        $this->httpResponse->setHeader('Cache-Control', 'public, max-age=3600');
        if ($name === 'swagger-ui-css') {
            $this->httpResponse->setContentType('text/css', 'utf-8');
            // Contributte scopes its bundled stylesheet to the Tracy panel host.
            return new TextResponse(str_replace('#tracy-debug ', '', file_get_contents($assets[$name][0])));
        }
        return new FileResponse($assets[$name][0], $name, $assets[$name][1], forceDownload: false);
    }
}
