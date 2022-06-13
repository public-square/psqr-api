<?php

namespace PublicSquare\Controller;

use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/link')]
class LinkController extends BaseController
{
    /**
     * @param mixed $infoHash
     * @param mixed $inlineLinkNumber
     */
    #[Route(path: '/{infoHash}/{inlineLinkNumber}', name: 'link_redirect', methods: ['GET'])]
    public function getUrlByInfoHash(Request $request, $infoHash, $inlineLinkNumber = 1)
    {
        (new ValidationHelper())->validateInfoHash($request, $infoHash);

        $this->initEsClient();

        $contentIndex = $this->esClient->getIndex($_ENV['CONTENT_INDEX']);

        $document = null;

        try {
            // get content by infoHash
            $document = $contentIndex->getDocument($infoHash);
        } catch (\Throwable $e) {
            // return not found or whatever - 404 error code
            throw new \Exception(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]), 404);
        }

        $url = $document->get('metainfo')['info']['publicSquare']['package']['canonicalUrl'];

        $queryParameters = $request->query->all();

        if (!empty($queryParameters)) {
            $query = parse_url($url, PHP_URL_QUERY);

            if ($query !== null) {
                $url .= '&';
            } else {
                $url .= '?';
            }

            $url .= http_build_query($queryParameters);
        }

        // 307 Temporary Redirect https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/307
        return new RedirectResponse($url, 307);
    }
}
