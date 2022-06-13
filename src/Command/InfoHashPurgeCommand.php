<?php

namespace PublicSquare\Command;

use Elastica\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InfoHashPurgeCommand extends Command
{
    protected static $defaultName        = 'es:purge-infohash';
    protected static $defaultDescription = 'Purge articles from ElasticSearch based on Infohash Value.';

    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('hashes', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'List of Hashes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hashes = $input->getArgument('hashes');

        // create a search query from these hashes
        $query = [
            'query' => [
                'bool' => [
                    'should' => [],
                ],
            ],
        ];

        // append our search terms
        foreach ($hashes as $hash) {
            $query['query']['bool']['should'][] = [
                'term' => [
                    'infoHash' => [
                        'value' => str_replace(["\n", "\r"], '', $hash),
                    ],
                ],
            ];
        }

        // ES Configuration
        $esConfig = [
            'host'      => $_ENV['ELASTICSEARCH_URL'],
            'port'      => $_ENV['ELASTICSEARCH_PORT'],
            'username'  => $_ENV['ELASTICSEARCH_USERNAME'],
            'password'  => $_ENV['ELASTICSEARCH_PASSWORD'],
            'transport' => 'https',
        ];

        // configure ES Client to search and delete on the Feed Index
        $feedClient   = new Client($esConfig);
        $feedIndex    = $feedClient->getIndex($_ENV['FEED_INDEX']);
        $feedPath     = $feedIndex->getName() . '/_delete_by_query';
        $feedResponse = $feedClient->request($feedPath, 'POST', $query);

        // configure ES Client to search and delete on the Search Index
        $searchClient   = new Client($esConfig);
        $searchIndex    = $searchClient->getIndex($_ENV['CONTENT_INDEX']);
        $searchPath     = $searchIndex->getName() . '/_delete_by_query';
        $searchResponse = $searchClient->request($searchPath, 'POST', $query);

        $this->logger->info('Deleted ' . $searchResponse->getData()['deleted'] . ' Doc(s) from the "' . $_ENV['CONTENT_INDEX'] . '" ES Index and had ' . \count($searchResponse->getData()['failures']) . ' Failures.');
        $this->logger->info('Deleted ' . $feedResponse->getData()['deleted'] . ' Doc(s) from the "' . $_ENV['FEED_INDEX'] . '" ES Index and had ' . \count($feedResponse->getData()['failures']) . ' Failures.');

        return Command::SUCCESS;
    }
}
