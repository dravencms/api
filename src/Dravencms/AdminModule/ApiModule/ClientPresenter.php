<?php declare(strict_types=1);

namespace Dravencms\AdminModule\ApiModule;

use Dravencms\AdminModule\SecuredPresenter;
use Dravencms\Api\Security\{ClientManager, ScopeRegistry};
use Dravencms\Components\BaseForm\BaseFormFactory;
use Dravencms\Components\BaseGrid\{BaseGridFactory, Grid};
use Dravencms\Locale\CurrentLocaleResolver;
use Dravencms\Model\Api\Entities\ApiClient;
use Dravencms\User\Attributes\IsAllowed;
use Nette\Application\UI\Form;

#[\Dravencms\User\Attributes\IsAllowed('api', 'clientsEdit')]
final class ClientPresenter extends SecuredPresenter
{
    /** @inject */
    public ClientManager $clientManager;
    /** @inject */
    public ScopeRegistry $scopeRegistry;
    /** @inject */
    public BaseFormFactory $baseFormFactory;
    /** @inject */
    public BaseGridFactory $baseGridFactory;
    /** @inject */
    public CurrentLocaleResolver $currentLocaleResolver;

    private ?ApiClient $client = null;

    #[IsAllowed('api', 'clientsEdit')]
    public function actionDefault(): void
    {
        $this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
        $this->template->h1 = $this->translator->translate('api.admin.title.clients');
        $this->template->setFile(__DIR__ . '/templates/default.latte');
    }

    #[IsAllowed('api', 'clientsEdit')]
    public function actionEdit(?int $id = null): void
    {
        $this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
        if ($id !== null) {
            $this->client = $this->entityManager->find(ApiClient::class, $id);
            if (!$this->client) {
                $this->error();
            }
        }
        $this->template->h1 = $this->translator->translate($this->client ? 'api.admin.title.editClient' : 'api.admin.title.newClient', ['name' => $this->client?->getName()]);
        $this->template->setFile(__DIR__ . '/templates/edit.latte');
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->client = $this->client;
    }

    protected function createComponentClientsGrid(string $name): Grid
    {
        $grid = $this->baseGridFactory->create($this, $name);
        $grid->setDataSource($this->entityManager->createQueryBuilder()->select('c')->from(ApiClient::class, 'c'));
        $grid->setDefaultSort(['id' => 'DESC']);
        $grid->addColumnNumber('id', 'api.admin.field.id')->setSortable()->setFilterRange();
        $grid->addColumnText('name', 'api.admin.field.name')->setSortable()->setFilterText();
        $grid->addColumnBoolean('isActive', 'api.admin.field.active')
            ->setFilterSelect(['' => 'api.admin.action.all', 1 => 'api.admin.action.yes', 0 => 'api.admin.action.no'])->setTranslateOptions();
        $grid->addColumnNumber('requestsPerMinute', 'api.admin.field.limit')->setSortable()->setAlign('center')->setFilterRange();
        $grid->addColumnText('scopes', 'api.admin.field.scopes')->setRenderer(static fn(ApiClient $client): string => implode(', ', $client->getScopes()));
        $grid->addColumnDateTime('createdAt', 'api.admin.field.created')->setFormat($this->currentLocaleResolver->getCurrentLocale()->getDateTimeFormat())
            ->setSortable()->setFilterDate();
        $grid->addAction('edit', '', 'edit')->setIcon('pencil')->setTitle('api.admin.action.edit')
            ->setClass('btn btn-sm btn-primary');
        $grid->addAction('tokens', '', 'Token:default', ['clientId' => 'id'])->setIcon('key')->setTitle('api.admin.action.tokens')
            ->setClass('btn btn-sm btn-primary');
        return $grid;
    }

    protected function createComponentClientForm(): Form
    {
        $form = $this->baseFormFactory->create();
        $form->addText('name', 'api.admin.field.name')->setRequired('api.admin.validation.nameRequired')->addRule(Form::MaxLength, 'api.admin.validation.nameTooLong', 190);
        $available = $this->scopeRegistry->getAll();
        foreach ($this->client?->getScopes() ?? [] as $scope) {
            $available[$scope] ??= $this->translator->translate('api.admin.scope.unavailable', ['scope' => $scope]);
        }
        $form->addCheckboxList('scopes', 'api.admin.field.scopes', $available);
        $form->addCheckbox('is_active', 'api.admin.field.active');
        $form->addText('limit', 'api.admin.field.limit')->setHtmlType('number')
            ->setRequired('api.admin.validation.limitRequired')
            ->addRule(Form::Integer, 'api.admin.validation.limitInteger')
            ->addRule(Form::Range, 'api.admin.validation.limitRange', [1, 100000]);
        $form->addSubmit('save', 'api.admin.action.save');
        $form->setDefaults([
            'name' => $this->client?->getName(),
            'scopes' => $this->client?->getScopes() ?? [],
            'is_active' => $this->client?->getIsActive() ?? true,
            'limit' => $this->client?->getRequestsPerMinute() ?? 60,
        ]);
        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $existing = $this->entityManager->getRepository(ApiClient::class)->findOneBy(['name' => trim($values->name)]);
            if ($existing && $existing !== $this->client) {
                $form['name']->addError('api.admin.validation.clientTaken');
                return;
            }
            try {
                $client = $this->clientManager->saveClient($this->client, $values->name, $values->scopes, $values->is_active, $values->limit);
            } catch (\InvalidArgumentException $error) {
                $form->addError('api.admin.validation.clientInvalid');
                return;
            }
            $this->redirect('edit', ['id' => $client->getId()]);
        };
        return $form;
    }

}
