<?php declare(strict_types=1);

namespace Dravencms\Api\DI;

use Apitte\Core\DI\ApiExtension as ApitteExtension;
use Apitte\Presenter\ApiPresenter;
use Dravencms\AdminModule\ApiModule\ClientPresenter;
use Dravencms\AdminModule\ApiModule\TokenPresenter;
use Dravencms\Api\Admin\InstallCommand;
use Dravencms\Api\Console\ManageCommand;
use Dravencms\Api\Console\SetupCommand;
use Dravencms\Api\Controllers\OpenApiController;
use Dravencms\Api\DocumentationPresenter;
use Dravencms\Api\Http\ErrorHandler;
use Dravencms\Api\Http\ResponseDecorator;
use Dravencms\Api\Schema\OpenApiDefinition;
use Dravencms\Api\Security\{AccessDecorator, ClientManager, ClientRateLimiter, ScopeRegistry, TokenAuthenticator};
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class ApiExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'rateLimitDirectory' => Expect::string()->default($this->getContainerBuilder()->parameters['tempDir'] . '/api-rate-limits'),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        foreach ([ScopeRegistry::class, TokenAuthenticator::class, ClientManager::class, OpenApiController::class, ApiPresenter::class, DocumentationPresenter::class] as $class) {
            $builder->addDefinition($this->prefix(str_replace('\\', '.', $class)))->setFactory($class);
        }
        $builder->addDefinition($this->prefix('rateLimiter'))
            ->setFactory(ClientRateLimiter::class, [$this->getConfig()->rateLimitDirectory]);
        $builder->addDefinition($this->prefix('accessDecorator'))->setFactory(AccessDecorator::class)
            ->addTag(ApitteExtension::CORE_DECORATOR_TAG, ['priority' => -1000]);
        $builder->addDefinition($this->prefix('responseDecorator'))->setFactory(ResponseDecorator::class)
            ->addTag(ApitteExtension::CORE_DECORATOR_TAG, ['priority' => 1000]);
        foreach (['scopes', 'client:list', 'client:create', 'client:disable', 'client:enable', 'token:list', 'token:create', 'token:revoke'] as $operation) {
            $builder->addDefinition($this->prefix('command.' . str_replace(':', '.', $operation)))
                ->setFactory(ManageCommand::class, [$operation])->setAutowired(false)
                ->addTag('console.command', 'api:' . $operation);
        }
        $builder->addDefinition($this->prefix('command.setup'))->setFactory(SetupCommand::class)->setAutowired(false)
            ->addTag('console.command', 'api:setup');
        if ($this->compiler->getExtensions(\Dravencms\Admin\DI\AdminExtension::class) !== []) {
            $builder->addDefinition($this->prefix('admin.clientPresenter'))->setFactory(ClientPresenter::class);
            $builder->addDefinition($this->prefix('admin.tokenPresenter'))->setFactory(TokenPresenter::class);
            $builder->addDefinition($this->prefix('admin.installCommand'))->setFactory(InstallCommand::class)->setAutowired(false)
                ->addTag('console.command', 'api:admin:install');
        }
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        $builder->getDefinition('apitte.core.errorHandler')->setFactory(ErrorHandler::class);
        $builder->getDefinition('apitte.openapi.coreDefinition')->setFactory(OpenApiDefinition::class);
    }
}
