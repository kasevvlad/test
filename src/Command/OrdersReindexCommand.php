<?php

namespace App\Command;

use App\Repository\OrderRepository;
use App\Search\OrderSearchIndex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:orders:reindex', description: 'Create the Manticore orders table and reindex all orders from the database')]
class OrdersReindexCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderSearchIndex $searchIndex,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->searchIndex->ensureTableExists();

        $orders = $this->orderRepository->findAll();

        foreach ($orders as $order) {
            $this->searchIndex->index($order);
        }

        $io->success(sprintf('Indexed %d order(s).', count($orders)));

        return Command::SUCCESS;
    }
}
