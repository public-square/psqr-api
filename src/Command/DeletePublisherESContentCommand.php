<?php

namespace PublicSquare\Command;

use Elastica\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeletePublisherESContentCommand extends Command
{
    protected static $defaultName        = 'es:delete-publisher-content';
    protected static $defaultDescription = 'Delete the ES Content of a Publisher via DID.';

    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('publisher', InputArgument::REQUIRED, 'Publisher DID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $publisher = $input->getArgument('publisher');

        // open file of acceptable DID Subdomains
        $didConfig = '/../../config/packages/' . $_ENV['APP_ENV'] . '/did_config.json';

        $config = json_decode(file_get_contents(__DIR__ . $didConfig), true);

        // did:web:website.com:u:name-here
        // this splits the did by colon into $matches array
        preg_match_all('/[^:]+/', $publisher, $matches);

        // get the first 3 elements of the did: did, web, website.com
        $paths = \array_slice($matches[0], 0, 3);

        // combines did,web,website.com into did:web:website.com
        $domain = implode(':', $paths);

        if (!isset($config['accepted_domains'][$domain])) {
            $this->logger->error('DID Domain Not Found in list of acceptable DID Domains.');

            return Command::FAILURE;
        }

        // create a delete by query search from this DID
        $query = [
            'query' => [
                'match' => [
                    'identity' => $publisher,
                ],
            ],
        ];

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
