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
        $this->template->h1 = 'API clients';
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
        $this->template->h1 = $this->client ? 'API client: ' . $this->client->getName() : 'New API client';
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
        $grid->addColumnNumber('id', 'ID')->setSortable()->setFilterRange();
        $grid->addColumnText('name', 'Name')->setSortable()->setFilterText();
        $grid->addColumnBoolean('isActive', 'Active')->setFilterSelect(['' => 'All', 1 => 'Yes', 0 => 'No']);
        $grid->addColumnNumber('requestsPerMinute', 'Requests per minute')->setSortable()->setAlign('center')->setFilterRange();
        $grid->addColumnText('scopes', 'Scopes')->setRenderer(static fn(ApiClient $client): string => implode(', ', $client->getScopes()));
        $grid->addColumnDateTime('createdAt', 'Created')->setFormat($this->currentLocaleResolver->getCurrentLocale()->getDateTimeFormat())
            ->setSortable()->setFilterDate();
        $grid->addAction('edit', '', 'edit')->setIcon('pencil')->setTitle('Edit')
            ->setClass('btn btn-xs btn-primary');
        $grid->addAction('tokens', '', 'Token:default', ['clientId' => 'id'])->setIcon('key')->setTitle('Tokens')
            ->setClass('btn btn-xs btn-primary');
        return $grid;
    }

    protected function createComponentClientForm(): Form
    {
        $form = $this->baseFormFactory->create();
        $form->addText('name', 'Name')->setRequired()->addRule(Form::MaxLength, null, 190);
        $available = $this->scopeRegistry->getAll();
        foreach ($this->client?->getScopes() ?? [] as $scope) {
            $available[$scope] ??= $scope . ' (unavailable)';
        }
        $form->addCheckboxList('scopes', 'Scopes', $available);
        $form->addCheckbox('is_active', 'Active');
        $form->addInteger('limit', 'Requests per minute')->setRequired()->addRule(Form::Range, null, [1, 100000]);
        $form->addSubmit('save', 'Save');
        $form->setDefaults([
            'name' => $this->client?->getName(),
            'scopes' => $this->client?->getScopes() ?? [],
            'is_active' => $this->client?->getIsActive() ?? true,
            'limit' => $this->client?->getRequestsPerMinute() ?? 60,
        ]);
        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $existing = $this->entityManager->getRepository(ApiClient::class)->findOneBy(['name' => trim($values->name)]);
            if ($existing && $existing !== $this->client) {
                $form['name']->addError('A client with this name already exists.');
                return;
            }
            try {
                $client = $this->clientManager->saveClient($this->client, $values->name, $values->scopes, $values->is_active, $values->limit);
            } catch (\InvalidArgumentException $error) {
                $form->addError($error->getMessage());
                return;
            }
            $this->redirect('edit', ['id' => $client->getId()]);
        };
        return $form;
    }

}
