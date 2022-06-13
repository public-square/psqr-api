<?php

namespace PublicSquare\Command;

use Elastica\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class SpecifyFeedNameCommand extends Command
{
    protected static $defaultName        = 'feed:feedname';
    protected static $defaultDescription = 'Create a feed with a feedname.';

    public function __construct(private CacheItemPoolInterface $cache, private KernelInterface $kernel, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('feedname', InputArgument::REQUIRED, 'String to use for Feed Name.')
            ->addArgument('dids', InputArgument::REQUIRED, 'Absolute Path to Text File of DIDs')
            ->addArgument('size', InputArgument::OPTIONAL, 'Set the size limit for this named feed')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // get desired feedname
        $feedname = $input->getArgument('feedname');

        // verify that feedname is alphanumeric and max 32 characters
        if (preg_match('/^([a-z0-9]+-)*[a-z0-9]+$/i', $feedname) === false || \strlen($feedname) > 32) {
            $this->logger->error('Feedname must be Alphanumeric and 32 Characters Max');

            return Command::FAILURE;
        }

        $this->logger->notice('Creating Feed: ' . strtoupper($feedname));

        // get file of dids
        $didsFile = $input->getArgument('dids');

        if (file_exists($didsFile) === false) {
            $this->logger->error('DIDs File Does Not Exist');

            return Command::FAILURE;
        }

        $didsFileContents = json_decode(file_get_contents($didsFile), true);

        // create a search query from these DIDs
        $query = [
            'query' => [
                'bool' => [
                    'should' => [],
                ],
            ],
        ];

        // append our search terms
        foreach ($didsFileContents['dids'] as $did) {
            $query['query']['bool']['should'][] = [
                'term' => [
                    'identity' => [
                        'value' => $did,
                    ],
                ],
            ];
        }

        // get size from CLI
        $size = $input->getArgument('size');

        // sort via reverse chronological order (pubDate descending)
        $query['sort'] = ['publishDate' => 'desc'];
        $query['size'] = $size !== null ? (int) $size : 500;
        $query['from'] = 0;

        $this->logger->notice('Limiting to ' . $query['size'] . ' total results.');

        // get the sha1 of the json stringified query
        $shaQuery = sha1(json_encode($query));

        // load feed configuration file
        $feedConfig = '/../../config/packages/' . $_ENV['APP_ENV'] . '/feed_config.json';

        $config = json_decode(file_get_contents(__DIR__ . $feedConfig), true);

        // if ttl is not set throw an error
        if (!isset($config['feed_configuration']['ttl'])) {
            throw new \Exception(json_encode([
                'success' => false,
                'error'   => 'Improperly configured Feed Configuration File.',
            ]), 400);
        }

        // create or update the redis key for the sha1 + query pair with expiration and string
        $itemSQPair = $this->cache->getItem($shaQuery);

        // data for Sha1 - Query Pair for Redis
        $dataSQ = [
            'es_query'   => $query,
            'expiration' => $config['feed_configuration']['ttl'],
        ];

        $itemSQPair->set($dataSQ);
        $this->cache->save($itemSQPair);

        // create or update the feedname + sha1 keypair
        $itemFSPair = $this->cache->getItem($feedname);

        // data for FeedName + Sha1 Pair for Redis
        $dataFS = [
            'hash'       => $shaQuery,
            'expiration' => $config['feed_configuration']['ttl'],
        ];

        $itemFSPair->set($dataFS);
        $this->cache->save($itemFSPair);

        // ES Configuration
        $esConfig = [
            'host'      => $_ENV['ELASTICSEARCH_URL'],
            'port'      => $_ENV['ELASTICSEARCH_PORT'],
            'username'  => $_ENV['ELASTICSEARCH_USERNAME'],
            'password'  => $_ENV['ELASTICSEARCH_PASSWORD'],
            'transport' => 'https',
        ];

        // configure ES Client and search for our documents based on identity
        $client = new Client($esConfig);

        $index = $client->getIndex($_ENV['FEED_INDEX']);

        $path = $index->getName() . '/_search';

        $response      = $client->request($path, 'GET', $query);
        $responseArray = $response->getData();

        if ($responseArray['hits']['total']['value'] === 0) {
            $this->logger->error('No documents returned from the ES Index. Exiting Creation Process.');

            return Command::FAILURE;
        }

        $this->logger->notice('Found ' . $responseArray['hits']['total']['value'] . ' total documents');

        // create file information
        $path = $this->kernel->getProjectDir() . '/' . $_ENV['FEED_OUTPUT_DIR'] . '/';

        // get hits
        $feed = $responseArray['hits']['hits'];

        $dataHolder = [];

        // append hits to array
        if (!empty($feed)) {
            foreach ($feed as $item) {
                $dataHolder[] = $item['_source'];
            }
        }

        $this->logger->notice('Creating File at: ' . $path . $feedname . '/latest.jsonl');

        if (!is_dir($path . $feedname)) {
            // make sure the directory path exists
            mkdir($path . $feedname, 0777, true);
        }

        $files = [
            $path . $feedname . '/latest.jsonl',
        ];

        $fs = new Filesystem();

        foreach ($files as $file) {
            // remove file to make sure we are getting new data
            $fs->remove($file);

            foreach ($dataHolder as $dataObj) {
                $fs->appendToFile($file, json_encode($dataObj) . PHP_EOL);
            }
        }

        $this->logger->notice('Process Completed. ' . strtoupper($feedname) . ' has been created.');

        return Command::SUCCESS;
    }
}
