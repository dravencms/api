<?php declare(strict_types=1);

namespace Dravencms\Api;

use Apitte\Presenter\ApiRoute;
use Dravencms\Application\IRouterFactory;
use Nette\Application\Routers\RouteList;

final class RouteFactory implements IRouterFactory
{
    public function createRouter(): RouteList
    {
        $router = new RouteList();
        $router->addRoute('api/docs/assets/<asset>', 'ApiDocs:Documentation:asset');
        $router->addRoute('api/docs', 'ApiDocs:Documentation:default');
        $router->add(new ApiRoute('api'));
        return $router;
    }
}
