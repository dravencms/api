<?php declare(strict_types=1);

namespace Dravencms\Api;

use Dravencms\User\Acl\{AclPermission, AclProviderInterface};

final class AclProvider implements AclProviderInterface
{
    public function getPermissions(): iterable
    {
        yield new AclPermission('api', 'clientsEdit', 'api.acl.permissions.clientsEdit.label', resourceLabel: 'api.acl.resource', description: 'api.acl.permissions.clientsEdit.description');
    }
}
