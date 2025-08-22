<?php declare(strict_types=1);

namespace Sigi\TempTableBundle\Command;

use Sigi\TempTableBundle\Service\TempTableManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'temp-table:cleanup',
    description: 'Nettoie les tables temporaires expirées'
)]
class TempTableCleanupCommand extends Command
{
    private TempTableManager $tempTableManager;

    public function __construct(TempTableManager $tempTableManager)
    {
        $this->tempTableManager = $tempTableManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Nettoyage des tables temporaires expirées...');

        try {
            $cleanedCount = $this->tempTableManager->cleanupExpiredTables();
            $io->success(\sprintf('%d table(s) temporaire(s) nettoyée(s)', $cleanedCount));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
