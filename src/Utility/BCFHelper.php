<?php

declare(strict_types=1);

namespace PublicSquare\Utility;

use Elastica\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper Functions involving Caching and Querying ES Clients.
 */
class BCFHelper
{
    protected Client $esClient;
    private CacheItemPoolInterface $cache;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setEsClient()
    {
        $esConfig = [
            'host'      => $_ENV['ELASTICSEARCH_URL'],
            'port'      => $_ENV['ELASTICSEARCH_PORT'],
            'username'  => $_ENV['ELASTICSEARCH_USERNAME'],
            'password'  => $_ENV['ELASTICSEARCH_PASSWORD'],
            'transport' => 'https',
        ];

        $this->esClient = new Client($esConfig);
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setCache(CacheItemPoolInterface $feedCache)
    {
        $this->cache = $feedCache;
    }

    /**
     * Builds, Sorts, Caches and Fetches (BCF) a Query based on the list of Distributed Identites (DIDs) supplied.
     *
     *
     * @throws \Exception if the feed configuration file is malformed
     */
    public function BCFQueryResults(Request $request, array $dids): array | \Exception
    {
        // BCF = Build, Cache, Fetch
        // create query template
        $query = [
            'query' => [
                'bool' => [
                    'should' => [],
                ],
            ],
        ];

        sort($dids);

        // append our search terms
        foreach ($dids as $did) {
            $query['query']['bool']['should'][] = [
                'term' => [
                    'identity' => [
                        'value' => $did,
                    ],
                ],
            ];
        }

        // sort via reverse chronological order
        $query['sort'] = ['publishDate' => 'desc'];
        $query['size'] = 100;
        $query['from'] = 0;

        // get the sha1 of the json stringified query
        $shaQuery = sha1(json_encode($query));

        // load feed configuration file
        $feedConfig = '/../../config/packages/' . $_ENV['APP_ENV'] . '/feed_config.json';

        $config = json_decode(file_get_contents(__DIR__ . $feedConfig), true);

        // if ttl is not set throw an error
        if (!isset($config['feed_configuration']['ttl'])) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Improperly configured Feed Configuration File.',
            ]), 400);
        }

        // create or update a redis key with expiration and string
        $item = $this->cache->getItem($shaQuery);

        $data = [
            'es_query'   => $query,
            'expiration' => $config['feed_configuration']['ttl'],
        ];

        $item->set($data);
        $this->cache->save($item);

        // get correct ES Index
        $index = $this->esClient->getIndex($_ENV['FEED_INDEX']);

        // append search path to index name
        $path = $index->getName() . '/_search';

        $response      = $this->esClient->request($path, 'GET', $query);
        $responseArray = $response->getData();

        // return hits or nothing
        return [
            $responseArray['hits']['total']['value'] === 0 ? '' : $responseArray['hits']['hits'],
            $shaQuery,
        ];
    }
}
