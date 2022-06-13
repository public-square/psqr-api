<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Entity\Permission;
use PublicSquare\Utility\JWSHelper;
use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/article', requirements: ['_format' => 'json'])]
class ArticleController extends BaseController
{
    /**
     * @SWG\Get(
     *      path="/api/article/{infoHash}",
     *      tags={"Article"},
     *      summary="Return the metainfo of an article.",
     *      description="<div>
     Validate an infoHash (a SHA1 hash) and fetch the associated document from the Feed Index.
     <br/>
     If no errors are thrown, return the metainfo of said document.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="infoHash",
     *          in="path",
     *          required=true,
     *          description="SHA1 hash over the info element in the metainfo file"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/article/{infoHash}",
     *                      "httpVerb": "GET",
     *                      "success": true,
     *                      "data": {
     *                          "name": "...",
     *                          "infoHash": "...",
     *                          "created": "...",
     *                          "createdBy": "...",
     *                          "publishDate": "...",
     *                          "title": "...",
     *                          "description": "...",
     *                          "image": "...",
     *                          "canonicalUrl": "...",
     *                          "body": "...",
     *                          "reply": "..."
     *                      }
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
     *                      "apiTarget": "/api/article/{infoHash}",
     *                      "httpVerb": "GET",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{infoHash}', name: 'article_page', options: ['expose' => true], methods: ['GET'])]
    public function getArticle(Request $request, string $infoHash)
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

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'data'      => $document->getData(),
        ]);
    }

    /**
     * @SWG\Delete(
     *      path="/api/article/{infoHash}",
     *      tags={"Article"},
     *      summary="Delete an Article from the Search and Feed Indices.",
     *      description="<div>
     Validate the infoHash in the path. Verify the authorizations associated with the accompanying JSON Web Signature (JWS) Token.
     <br/>
     If the permissions associated with the JWS are adequate, and no errors are thrown during retrieval of the document,
     <br />
     delete the associated article from both the Search and Feed Indices.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="infoHash",
     *          in="path",
     *          required=true,
     *          description="SHA1 hash over the info element in the metainfo file"
     *      ),
     *      @SWG\Parameter(
     *          name="token",
     *          in="header",
     *          required=true,
     *          description="JWS Authorization Token"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/article/{infoHash}",
     *                      "httpVerb": "DELETE",
     *                      "success": true,
     *                      "message": "..."
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
     *                      "apiTarget": "/api/article/{infoHash}",
     *                      "httpVerb": "DELETE",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{infoHash}', name: 'article_delete', options: ['expose' => true], methods: ['DELETE'])]
    public function deleteArticle(Request $request, $infoHash, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        $validationHelper->validateInfoHash($request, $infoHash);

        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        // get payload and header
        $header = $jws->getSignatures()[0]->getProtectedHeader();

        // unpack header into its base KID, DID, and Grant
        $kid   = $header['kid'];
        $did   = explode('#', $header['kid'])[0];
        $grant = explode('#', $header['kid'])[1];

        // if signing token isnt using admin or curate permissions, throw an error
        if ($grant !== 'curate' && $grant !== 'admin') {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid JWS Signature Permission to Do Operation.',
            ]), 400);
        }

        // get user grant permissions from DB
        $permission = $this->getDoctrine()->getRepository(Permission::class)->findOneBy([
            'did' => $did,
        ]);

        // if no DB Entry found, throw error
        if (null === $permission) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'User Grant Permissions Do Not Exist.',
            ]), 400);
        }

        // if KID exists in DB entry, match with JWS signature KID, throw error if they don't match
        if ($permission->getKid() !== null && $permission->getKid() !== $kid) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'JWS Signature KID and Permissions KID do not match.',
            ]), 400);
        }

        // validate permissions of the did (network access, curate permission, existence)
        $validPermissions = $validationHelper->validatePermission($did);

        // if validation comes back false, throw an error
        if ($validPermissions === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid DID Permissions to Do Operation.',
            ]), 400);
        }

        $contentIndex = $this->esClient->getIndex($_ENV['CONTENT_INDEX']);
        $feedIndex    = $this->esClient->getIndex($_ENV['FEED_INDEX']);

        try {
            // delete content by infoHash
            $contentIndex->deleteById($infoHash);
            $feedIndex->deleteById($infoHash);
        } catch (\Throwable $e) {
            // return not found or whatever - 404 error code
            throw new \Exception(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]), 404);
        }

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'message'   => 'Successfully deleted article ' . $infoHash,
        ]);
    }
}
