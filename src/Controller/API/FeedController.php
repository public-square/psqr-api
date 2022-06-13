<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use Psr\Log\LoggerInterface;
use PublicSquare\Controller\BaseController;
use PublicSquare\Utility\BCFHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api', requirements: ['_format' => 'json'])]
class FeedController extends BaseController
{
    public function __construct(private LoggerInterface $logger, private KernelInterface $kernel)
    {
    }

    /**
     * @SWG\Put(
     *      path="/api/feed",
     *      tags={"Feed"},
     *      summary="Create or Update a Feed.",
     *      description="<div>
     Create or Update a feed based on an array of Distributed Identities (dids) in the body of the request header.
     <br />
     Cache the feed and collate the results (Documents associated with the dids) upon success.
     </div>",
     *      @SWG\Parameter(
     *          name="dids",
     *          in="header",
     *          required=true,
     *          description="Array of DIDs to collate data for"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/feed",
     *                      "httpVerb": "PUT",
     *                      "success": true,
     *                      "feed": {}
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
     *                      "apiTarget": "/api/feed",
     *                      "httpVerb": "PUT",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/feed', name: 'api_feed_put', options: ['expose' => true], methods: ['PUT'])]
    public function putFeedContent(Request $request, BCFHelper $bcfHelper)
    {
        // assuming json object of dids like so:
        // {
        //    dids: [did1, did2, did3]
        // }
        $content = $request->request->all();

        // build, cache and fetch query results
        // 0 - feed items, 1 - query sha
        $results = $bcfHelper->BCFQueryResults($request, $content['dids']);

        $feed     = $results[0];
        $shaQuery = $results[1];

        // create file information
        $path = $this->kernel->getProjectDir() . '/' . $_ENV['FEED_OUTPUT_DIR'] . '/';

        $dataHolder = [];

        // if search results are not empty, append them to the array of data
        if (!empty($feed)) {
            foreach ($feed as $item) {
                $dataHolder[] = $item['_source'];
            }
        }

        // make sure the sha1 named directory exists
        if (!is_dir($path . $shaQuery)) {
            mkdir($path . $shaQuery, 0777, true);
        }

        // TODO: Add Proper Feed Time Sharding Here; For now, stick with latest.jsonl
        $files = [
            $path . $shaQuery . '/latest.jsonl',
        ];

        $fs = new Filesystem();

        foreach ($files as $file) {
            $fs->remove($file);

            foreach ($dataHolder as $dataObj) {
                $fs->appendToFile($file, json_encode($dataObj) . PHP_EOL);
            }
        }

        $this->logger->info('Feed content saved');

        // url of feed
        $feedUrl = $_ENV['FEED_LOCATION_ENDPOINT'] . '/feed/' . $shaQuery . '/latest.jsonl';

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'feedUrl'   => $feedUrl,
        ]);
    }

    /**
     * @SWG\Get(
     *      path="/api/feed/{name}",
     *      tags={"Feed"},
     *      summary="Return a feed based on its Feed Name.",
     *      description="<div>
     A feed can be given a name in either SHA1, Alphanumeric or Distributed Identity (did) Form.
     <br />
     Using the feed name, verify the existence of the feed.
     <br/>
     If the Feed Name is in DID Format, create the feed if it doesn't exist.
     <br/>
     Return Feed Contents upon success.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="name",
     *          in="path",
     *          required=true,
     *          description="Feed Name to Search For."
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/feed/{name}",
     *                      "httpVerb": "GET",
     *                      "success": true,
     *                      "feed": {}
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
     *                      "apiTarget": "/api/feed/{name}",
     *                      "httpVerb": "GET",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/feed/{name}', name: 'api_feed_get', options: ['expose' => true], methods: ['GET'])]
    public function getFeed(Request $request, $name, BCFHelper $bcfHelper)
    {
        if (file_exists(__DIR__ . '/../../../public/feed/' . $name . '/latest.jsonl') === true) {
            // get a file into array, skip empty lines, ignore new lines
            $lines = file(__DIR__ . '/../../../public/feed/' . $name . '/latest.jsonl', FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

            // json decode array contents
            $contents = array_map(function ($line) {
                return json_decode($line, true);
            }, $lines);

            return $this->json([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => true,
                'feed'      => $contents,
            ]);
        }

        if ((bool) preg_match('/^[0-9a-f]{40}$/i', $name) === true || !str_contains($name, 'did:')) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Feed with Hash or Feed Name Does Not Exist.',
            ]), 400);
        }

        $dids = [$name];

        $results = $bcfHelper->BCFQueryResults($request, $dids);

        $feed = $results[0];

        // create file information
        $path = $this->kernel->getProjectDir() . '/' . $_ENV['FEED_OUTPUT_DIR'] . '/';

        $dataHolder = [];

        // if search results are not empty, append them to the array of data
        if (!empty($feed)) {
            foreach ($feed as $item) {
                $dataHolder[] = $item['_source'];
            }
        }

        // make sure the sha1 named directory exists
        if (!is_dir($path . $name)) {
            mkdir($path . $name, 0777, true);
        }

        // TODO: Add Proper Feed Time Sharding Here; For now, stick with latest.jsonl
        $files = [
            $path . $name . '/latest.jsonl',
        ];

        $fs = new Filesystem();

        foreach ($files as $file) {
            $fs->remove($file);

            foreach ($dataHolder as $dataObj) {
                $fs->appendToFile($file, json_encode($dataObj) . PHP_EOL);
            }
        }

        // get a file into array, skip empty lines, ignore new lines
        $lines = file($files[0], FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

        // json decode array contents
        $contents = array_map(function ($line) {
            return json_decode($line, true);
        }, $lines);

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'feed'      => $contents,
        ]);
    }
}
