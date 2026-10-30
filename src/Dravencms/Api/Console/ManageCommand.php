<?php declare(strict_types=1);

namespace Dravencms\Api\Console;

use Doctrine\ORM\EntityManagerInterface;
use Dravencms\Api\Security\{ClientManager, ScopeRegistry};
use Dravencms\Model\Api\Entities\{ApiClient, ApiToken};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ManageCommand extends Command
{
    public function __construct(
        private string $operation,
        private EntityManagerInterface $entityManager,
        private ClientManager $manager,
        private ScopeRegistry $scopes
    ) {
        parent::__construct('api:' . $operation);
    }

    protected function configure(): void
    {
        $this->setDescription('Manage API ' . $this->operation);
        if ($this->operation === 'client:create') {
            $this->addArgument('name', InputArgument::REQUIRED)
                ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)
                ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Requests per minute', '60');
        } elseif ($this->operation === 'client:disable' || $this->operation === 'client:enable') {
            $this->addArgument('client-id', InputArgument::REQUIRED);
        } elseif ($this->operation === 'token:list') {
            $this->addArgument('client-id', InputArgument::OPTIONAL);
        } elseif ($this->operation === 'token:create') {
            $this->addArgument('client-id', InputArgument::REQUIRED)
                ->addArgument('name', InputArgument::REQUIRED)
                ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiry in RFC 3339 format, including timezone');
        } elseif ($this->operation === 'token:revoke') {
            $this->addArgument('token-id', InputArgument::REQUIRED);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->operation === 'scopes') {
            $io->listing(array_values($this->scopes->getAll()));
        } elseif ($this->operation === 'client:list') {
            $rows = [];
            foreach ($this->entityManager->getRepository(ApiClient::class)->findBy([], ['id' => 'ASC']) as $client) {
                $rows[] = [$client->getId(), $client->getName(), $client->getIsActive() ? 'yes' : 'no', implode(', ', $client->getScopes())];
            }
            $io->table(['ID', 'Name', 'Active', 'Scopes'], $rows);
        } elseif ($this->operation === 'token:list') {
            $id = $input->getArgument('client-id');
            $criteria = $id !== null ? ['client' => $this->id($id)] : [];
            $rows = [];
            foreach ($this->entityManager->getRepository(ApiToken::class)->findBy($criteria, ['id' => 'ASC']) as $token) {
                $rows[] = [$token->getId(), $token->getClient()->getId(), $token->getName(),
                    $token->getExpiresAt()?->format(DATE_ATOM) ?? '-', $token->getRevokedAt()?->format(DATE_ATOM) ?? '-'];
            }
            $io->table(['ID', 'Client ID', 'Name', 'Expires', 'Revoked'], $rows);
        } elseif ($this->operation === 'client:create') {
            $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
            if ($limit === false) {
                throw new \InvalidArgumentException('limit must be an integer.');
            }
            $client = $this->manager->saveClient(null, $input->getArgument('name'), $input->getOption('scope'), true, $limit);
            $io->success('Created API client ' . $client->getId());
        } elseif ($this->operation === 'token:revoke') {
            $token = $this->entityManager->find(ApiToken::class, $this->id($input->getArgument('token-id')));
            if (!$token instanceof ApiToken) {
                throw new \InvalidArgumentException('Token not found.');
            }
            $this->manager->revokeToken($token);
            $io->success('Token revoked.');
        } else {
            $client = $this->entityManager->find(ApiClient::class, $this->id($input->getArgument('client-id')));
            if (!$client instanceof ApiClient) {
                throw new \InvalidArgumentException('Client not found.');
            }
            if ($this->operation === 'token:create') {
                $expires = $input->getOption('expires');
                $expiresAt = $expires !== null ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $expires) : null;
                if ($expires !== null && (!$expiresAt || $expiresAt->format(DATE_ATOM) !== $expires)) {
                    throw new \InvalidArgumentException('expires must be RFC 3339, e.g. 2027-01-01T00:00:00+00:00.');
                }
                $secret = $this->manager->issueToken($client, $input->getArgument('name'), $expiresAt);
                $io->writeln($secret, OutputInterface::OUTPUT_RAW);
            } else {
                $this->manager->saveClient($client, $client->getName(), $client->getScopes(), $this->operation === 'client:enable', $client->getRequestsPerMinute());
                $io->success('Client updated.');
            }
        }
        return Command::SUCCESS;
    }

    private function id(string $id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new \InvalidArgumentException('ID must be a positive integer.');
        }
        return $value;
    }
}
