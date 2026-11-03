<?php declare(strict_types=1);

namespace Dravencms\Api;

use Dravencms\Admin\Menu\{AdminMenuItem, AdminMenuProviderInterface};

final class AdminMenuProvider implements AdminMenuProviderInterface
{
    public function getItems(): iterable
    {
        yield new AdminMenuItem('api.clients', 'api.menu.clients', destination: ':Admin:Api:Client:default', icon: 'fa-key', order: 850, resource: 'api', privilege: 'clientsEdit', activeDestinations: [':Admin:Api:Token:*']);
    }
}
