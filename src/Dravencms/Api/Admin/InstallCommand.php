<?php declare(strict_types=1);

namespace Dravencms\Api\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Dravencms\Model\User\Entities\{AclOperation, AclResource, Group};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

final class InstallCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct('api:admin:install');
    }

    protected function configure(): void
    {
        $this->setDescription('Add API management permission to an existing installation')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Administrator group to grant API management to', 'Administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = $this->entityManager;
        $group = $manager->getRepository(Group::class)->findOneBy(['name' => $input->getOption('group')]);
        if (!$group instanceof Group) {
            throw new \InvalidArgumentException('Administrator group not found; specify --group.');
        }
        $manager->wrapInTransaction(function () use ($manager, $group): void {
            $resource = $manager->getRepository(AclResource::class)->findOneBy(['name' => 'api']);
            if (!$resource) {
                $resource = new AclResource('api', 'API access management');
                $manager->persist($resource);
                $manager->flush();
            }
            $operation = $manager->getRepository(AclOperation::class)->findOneBy(['aclResource' => $resource, 'name' => 'clientsEdit']);
            if (!$operation) {
                $operation = new AclOperation($resource, 'clientsEdit', 'Manage API clients, scopes and tokens');
                $manager->persist($operation);
            }
            $operation->addGroup($group);
        });
        $output->writeln('API management permission installed.');
        return Command::SUCCESS;
    }
}
