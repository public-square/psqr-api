<?php

namespace PublicSquare\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DIDGetter
{
    // using autowiring to inject the cache pool
    // NOTE: To save the keys appropriately build them below using the proper value: didCache = ID, feedCache = feed, queryCache = query
    public function __construct(private CacheItemPoolInterface $didCache, private HttpClientInterface $client)
    {
    }

    /**
     * gets the didDoc from the cache or online.
     *
     * @param string $did did string used to find the didDoc
     *
     * @return array the did obj or a failed http response
     */
    public function getDistributedIdentity(string $did): array
    {
        if (preg_match('#[!^]#', $did) === 1) {
            throw new Exception('DID cannot contain the characters ! or ^');
        }

        $rDid   = $this->convertDid($did);
        $didDoc = $this->didCache->getItem($rDid);

        // return doc if it exists in cache
        if ($didDoc->isHit()) {
            return json_decode($didDoc->get(), true);
        }

        // get new did from internet and cache
        return $this->getNewDid($rDid, $didDoc);
    }

    /**
     * convert standard did into format compatible with PSR-16
     * and remove # portion.
     *
     * @param string $did standard did
     *
     * @return string compatible did
     */
    public function convertDid(string $did): string
    {
        preg_match('/([^#]+)($|#)/', $did, $matches);
        $newDid = $matches[1];

        return str_replace([':', '/'], ['!', '^'], $newDid);
    }

    /**
     * retrieves didDoc string from online and stores
     * it if successful.
     *
     * @param string $rDid   redis compatible did string used to find the didDoc
     * @param object $didDoc redis cache object to place the new didDoc into
     *
     * @return array the did obj or a failed http response
     */
    public function getNewDid(string $rDid, object $didDoc): array
    {
        if (preg_match('#[:/]#', $rDid) === 1) {
            throw new Exception('Use convertDid to convert a standard did into a redis compatible did');
        }

        $url      = $this->parseDidUrl($rDid);
        $response = $this->client->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getContent());
        }

        $docJson = $response->getContent();

        // assign a value to the item and save it
        $didDoc->set($docJson);
        $this->didCache->save($didDoc);

        return json_decode($docJson, true);
    }

    /**
     * parses redis compatible did string to get
     * the url it is served from.
     *
     * @param string $rDid redis compatible did string used to find the didDoc
     *
     * @return string url serving the didDoc
     */
    public function parseDidUrl(string $rDid): string
    {
        if (preg_match('#[:/]#', $rDid) === 1) {
            throw new Exception('Use convertDid to convert a standard did into a redis compatible did');
        }

        $rDid = str_replace(['^'], ['/'], $rDid);

        preg_match_all('/[^!]+/', $rDid, $matches);

        if (\count($matches[0]) < 3) {
            throw new Exception('Unable to parse DID. Expected format: did:psqr:{hostname}/{path}');
        }

        $paths = \array_slice($matches[0], 2);

        if (str_contains($paths[0], '/') === false) {
            return 'https://' . implode('/', $paths) . '/.well-known/psqr';
        }

        // assemble url
        return 'https://' . implode('/', $paths);
    }
}
