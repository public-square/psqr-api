<?php

namespace PublicSquare\Command;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use PublicSquare\Service\DIDGetter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetDidCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    protected static $defaultName        = 'ps:getdid';
    protected static $defaultDescription = 'Gets a did either from the redis cache or online';

    public function __construct(private DIDGetter $didGetter)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('did', InputArgument::REQUIRED, 'did string used to find the didDoc')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // get did from command line
        $did = $input->getArgument('did');

        // get did doc from did or throw error
        try {
            $response = $this->didGetter->getDistributedIdentity($did);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->logger->info('Command Success');

        return Command::SUCCESS;
    }
}
