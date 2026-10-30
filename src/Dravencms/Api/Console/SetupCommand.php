<?php declare(strict_types=1);

namespace Dravencms\Api\Console;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Dravencms\Model\Api\Entities\{ApiClient, ApiToken};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

final class SetupCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct('api:setup');
    }

    protected function configure(): void
    {
        $this->setDescription('Print SQL for missing API tables, or create them with --apply')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Create missing API tables without changing other tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->entityManager->getConnection()->createSchemaManager();
        $tool = new SchemaTool($this->entityManager);
        foreach ([ApiClient::class, ApiToken::class] as $class) {
            $metadata = $this->entityManager->getClassMetadata($class);
            if ($schema->tablesExist([$metadata->getTableName()])) {
                continue;
            }
            foreach ($tool->getCreateSchemaSql([$metadata]) as $sql) {
                if ($input->getOption('apply')) {
                    $this->entityManager->getConnection()->executeStatement($sql);
                } else {
                    $output->writeln($sql . ';', OutputInterface::OUTPUT_RAW);
                }
            }
        }
        return Command::SUCCESS;
    }
}
