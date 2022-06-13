<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use FOS\ElasticaBundle\Finder\TransformedFinder;
use LZCompressor\LZString;
use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Service\SearchCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api', requirements: ['_format' => 'json'])]
class SearchController extends BaseController
{
    public function __construct(private TransformedFinder $finderSearch)
    {
    }

    /**
     * @SWG\Post(
     *      path="/api/search",
     *      tags={"Search"},
     *      summary="Search the Index for the desired query.",
     *      description="<div>
     Searches the Search Index for a Query Term and returns results. Chunks those results into Pages.
     <br/>
     The results are limited to 1000 max, and cached as pages locally. The filenames for those pages are comprised
     <br />
     of the hash of the search term and the page number. The results are returned along with per page and
     <br />
     total number of pages as metrics.
     </div>",
     *      @SWG\Parameter(
     *          name="search",
     *          in="header",
     *          required=true,
     *          description="Query to search for"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/search",
     *                      "httpVerb": "POST",
     *                      "success": true,
     *                      "results": {},
     *                      "resultsUrl": "..."
     *                  }
     *              )
     *          }
     *      ),
     *      @SWG\Response(
     *          response="400",
     *          description="Error Thrown.",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/search",
     *                      "httpVerb": "POST",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/search', name: 'api_search', options: ['expose' => true], methods: ['GET', 'POST'])]
    public function searchApi(Request $request, SearchCache $searchCache)
    {
        // assume params
        $data = $request->request->all();

        // get dotenv values, current page, and search param
        $maxPerPage = (int) ($_ENV['SEARCH_MAX_PER_PAGE'] ?? 50);
        $data['page'] ??= 1;

        $originalPage = $data['page'];

        $sha           = sha1(json_encode($data));
        $searchResults = $searchCache->getSavedFileSearchResults($sha);

        // if we have cached results, return early
        if ($searchResults !== false) {
            return $this->json(array_merge([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => true,
            ], $searchResults));
        }

        $results = $this->finderSearch->find($data['term'], 1000);

        $count = \count($results);

        if ($count === 0) {
            return $this->json([
                'apiTarget'    => $request->getPathInfo(),
                'httpVerb'     => $request->getMethod(),
                'success'      => true,
                'perPage'      => 0,
                'totalResults' => 0,
                'results'      => [],
            ]);
        }

        $dataHolder = [
            'perPage'      => $maxPerPage,
            'totalResults' => $count,
        ];

        $resultSet = array_map(function ($item) {
            return $item['_source'];
        }, $results);

        $resultsChunked = array_chunk($resultSet, $maxPerPage);

        $returnedResults = [
            'results' => [],
        ];

        foreach ($resultsChunked as $key => $resultData) {
            $page                  = ($key + 1);
            $dataHolder['page']    = $page;
            $dataHolder['results'] = $resultData;

            $data['page'] = $dataHolder['page'];

            $sha = sha1(json_encode($data));

            $searchResults = $searchCache->saveNewSearchResults($sha, $dataHolder);

            if ($page === $originalPage) {
                $returnedResults = $searchResults;
            }
        }

        return $this->json(array_merge([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
        ], $returnedResults));
    }

    /**
     * @SWG\Get(
     *      path="/api/search/results/{hash}/{page}",
     *      tags={"Search"},
     *      summary="Fetch the Cached Query Results.",
     *      description="<div>
     Return a slice of cached results based on a LZString Encoded URI Component Hash
     <br/>
     and a page number. The LZString is encoded from a JSON string of a search parameter and optional values.
     </div>",
     *      @SWG\Parameter(
     *          name="hash",
     *          in="path",
     *          required=true,
     *          description="LZString Encoded URI Component"
     *      ),
     *      @SWG\Parameter(
     *          name="page",
     *          in="path",
     *          required=true,
     *          description="Page Number For Results (Defaults to 1 if left unset)"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/search/results/{hash}/{page}",
     *                      "httpVerb": "GET",
     *                      "success": true,
     *                      "searchResults": {},
     *                  }
     *              )
     *          }
     *      ),
     *      @SWG\Response(
     *          response="400",
     *          description="Error Thrown.",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/search/results/{hash}/{page}",
     *                      "httpVerb": "GET",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/search/{hash}', name: 'api_search_results', options: ['expose' => true], methods: ['GET'])]
    public function getSearchResultsFromHash(Request $request, string $hash, SearchCache $searchCache)
    {
        $foundFile = $searchCache->getSavedFileSearchResults($hash);

        if ($foundFile === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Search Results with Given Hash does not exist.',
            ]), 400);
        }

        return $this->json([
            'apiTarget'    => $request->getPathInfo(),
            'httpVerb'     => $request->getMethod(),
            'success'      => true,
            'perPage'      => $foundFile['perPage'],
            'totalResults' => $foundFile['totalResults'],
            'results'      => $foundFile['results'],
        ]);
    }
}
