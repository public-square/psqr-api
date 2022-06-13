<?php

namespace PublicSquare\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class SearchCache
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    /**
     * Return file look up for Search Results.
     *
     * @param string $hash sha-1 hash of query string
     *
     * @return array of json results from file content or false if file is not found
     */
    public function getSavedFileSearchResults(string $hash): array | bool
    {
        $filename = $this->kernel->getProjectDir() . '/' . $_ENV['SEARCH_OUTPUT_DIR'] . '/' . $hash . '.json';

        if (file_exists($filename) === true) {
            return json_decode(file_get_contents($filename), true);
        }

        return false;
    }

    /**
     * Save new file of search results with hashed name.
     *
     * @param string $hash           sha-1 hash of query string
     * @param array  $resultsToCache ES Doc Results to Cache
     *
     * @return array of results
     */
    public function saveNewSearchResults(string $hash, array $resultsToCache): array
    {
        $filename = $this->kernel->getProjectDir() . '/' . $_ENV['SEARCH_OUTPUT_DIR'] . '/' . $hash . '.json';

        $filesystem = new Filesystem();
        $filesystem->dumpFile($filename, json_encode($resultsToCache));

        return $resultsToCache;
    }
}
