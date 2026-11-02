<?php declare(strict_types=1);

namespace Dravencms\AdminModule\ApiModule;

use Dravencms\AdminModule\SecuredPresenter;
use Dravencms\Api\Security\ClientManager;
use Dravencms\Components\BaseForm\BaseFormFactory;
use Dravencms\Components\BaseGrid\{BaseGridFactory, Grid};
use Dravencms\Locale\CurrentLocaleResolver;
use Dravencms\Model\Api\Entities\{ApiClient, ApiToken};
use Dravencms\User\Attributes\IsAllowed;
use Nette\Application\UI\Form;

final class TokenPresenter extends SecuredPresenter
{
    /** @inject */
    public ClientManager $clientManager;
    /** @inject */
    public BaseFormFactory $baseFormFactory;
    /** @inject */
    public BaseGridFactory $baseGridFactory;
    /** @inject */
    public CurrentLocaleResolver $currentLocaleResolver;

    private ?ApiClient $client = null;
    private ?ApiToken $token = null;
    private ?string $issuedToken = null;

    #[IsAllowed('api', 'clientsEdit')]
    public function actionDefault(int $clientId): void
    {
        $this->loadClient($clientId);
        $this->template->h1 = $this->translator->translate('api.admin.title.tokens', ['name' => $this->client->getName()]);
        $this->template->setFile(__DIR__ . '/templates/Token/default.latte');
    }

    #[IsAllowed('api', 'clientsEdit')]
    public function actionCreate(int $clientId): void
    {
        $this->loadClient($clientId);
        $this->template->h1 = $this->translator->translate('api.admin.title.newToken', ['name' => $this->client->getName()]);
        $this->template->setFile(__DIR__ . '/templates/Token/create.latte');
    }

    #[IsAllowed('api', 'clientsEdit')]
    public function actionDelete(int $clientId, int $id): void
    {
        $this->loadClient($clientId);
        $this->token = $this->entityManager->getRepository(ApiToken::class)->findOneBy([
            'id' => $id, 'client' => $this->client, 'revokedAt' => null,
        ]);
        if (!$this->token) {
            $this->error();
        }
        $this->template->h1 = $this->translator->translate('api.admin.title.revokeToken', ['name' => $this->token->getName()]);
        $this->template->setFile(__DIR__ . '/templates/Token/delete.latte');
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->client = $this->client;
        $this->template->token = $this->token;
        $this->template->issuedToken = $this->issuedToken;
    }

    private function loadClient(int $id): void
    {
        $this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
        $this->client = $this->entityManager->find(ApiClient::class, $id);
        if (!$this->client) {
            $this->error();
        }
    }

    protected function createComponentTokensGrid(string $name): Grid
    {
        if (!$this->client) {
            $this->error();
        }
        $grid = $this->baseGridFactory->create($this, $name);
        $grid->setDataSource($this->entityManager->createQueryBuilder()->select('t')->from(ApiToken::class, 't')
            ->where('t.client = :client')->setParameter('client', $this->client));
        $grid->setDefaultSort(['id' => 'DESC']);
        $grid->addColumnNumber('id', 'api.admin.field.id')->setSortable()->setFilterRange();
        $grid->addColumnText('name', 'api.admin.field.name')->setSortable()->setFilterText();
        foreach (['createdAt' => 'api.admin.field.created', 'expiresAt' => 'api.admin.field.expires', 'revokedAt' => 'api.admin.field.revoked'] as $field => $label) {
            $grid->addColumnDateTime($field, $label)->setFormat($this->currentLocaleResolver->getCurrentLocale()->getDateTimeFormat())
                ->setSortable()->setFilterDate();
        }
        $grid->addAction('delete', '', 'delete', ['clientId' => 'client.id', 'id' => 'id'])
            ->setIcon('trash')->setTitle('api.admin.action.revoke')->setClass('btn btn-sm btn-danger');
        $grid->allowRowsAction('delete', static fn(ApiToken $token): bool => $token->getRevokedAt() === null);
        return $grid;
    }

    protected function createComponentTokenForm(): Form
    {
        if (!$this->client) {
            $this->error();
        }
        $form = $this->baseFormFactory->create();
        $form->addText('name', 'api.admin.field.tokenName')->setRequired('api.admin.validation.tokenNameRequired')->addRule(Form::MaxLength, 'api.admin.validation.nameTooLong', 190);
        $form->addText('expires', 'api.admin.field.expiresUtc')->setNullable();
        $form->addSubmit('issue', 'api.admin.action.issue');
        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $expires = $values->expires !== null ? \DateTimeImmutable::createFromFormat('!Y-m-d', $values->expires, new \DateTimeZone('UTC')) : null;
            if ($values->expires !== null && (!$expires || $expires->format('Y-m-d') !== $values->expires)) {
                $form->addError('api.admin.validation.expiryFormat');
                return;
            }
            if ($expires !== null && $expires <= new \DateTimeImmutable()) {
                $form->addError('api.admin.validation.expiryFuture');
                return;
            }
            try {
                $this->issuedToken = $this->clientManager->issueToken($this->client, $values->name, $expires);
            } catch (\InvalidArgumentException $error) {
                $form->addError('api.admin.validation.tokenInvalid');
            }
        };
        return $form;
    }

    protected function createComponentRevokeForm(): Form
    {
        if (!$this->client || !$this->token) {
            $this->error();
        }
        $form = $this->baseFormFactory->create();
        $form->addSubmit('revoke', 'api.admin.action.revoke');
        $form->onSuccess[] = function (): void {
            $this->clientManager->revokeToken($this->token);
            $this->flashMessage($this->translator->translate('api.admin.token.revoked'), 'alert-success');
            $this->redirect('default', ['clientId' => $this->client->getId()]);
        };
        return $form;
    }
}
