<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Utility\DIDGetterHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/publisher', requirements: ['_format' => 'json'])]
class PublisherController extends BaseController
{
    /**
     * @SWG\Get(
     *      path="/api/publisher/{did}",
     *      tags={"Publisher"},
     *      summary="Fetch the Authorizations permitted to a Distributed Identity.",
     *      description="<div>
     Retrieve a Distributed Identity (DID) from the path and validate the DID via the network's cached Identity.
     <br/>
     Collate all granted authorizations and return the results.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="did",
     *          in="path",
     *          required=true,
     *          description="Distributed Identity to retrieve Information on."
     *      ),
     *      @SWG\Parameter(
     *          name="token",
     *          in="header",
     *          required=true,
     *          description="JWS Authorization Token."
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/publisher/{did}",
     *                      "httpVerb": "GET",
     *                      "success": true,
     *                      "data": {},
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
     *                      "apiTarget": "/api/publisher/{did}",
     *                      "httpVerb": "GET",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{did}', name: 'publisher_page', options: ['expose' => true], methods: ['GET'])]
    public function getPublisherDid(Request $request, $did, DIDGetterHelper $didGetterHelper)
    {
        // validate acceptable DID Subdomains
        $jsonDid = $didGetterHelper->validateDistributedIdentity($request, $did);

        $grantedRights = [];

        foreach ($jsonDid['authorization']['rules'] as $rules) {
            foreach ($rules['grant'] as $rights) {
                if (!\in_array($rights, $grantedRights, true)) {
                    $grantedRights[] = $rights;
                }
            }
        }

        $data = [
            'context' => $jsonDid['@context'],
            'id'      => $jsonDid['id'],
            'name'    => $jsonDid['publicIdentity']['name'],
            'url'     => $jsonDid['publicIdentity']['url'] ?? '',
            'updated' => $jsonDid['publicIdentity']['updated'],
            'rights'  => $grantedRights,
        ];

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'data'      => $data,
        ]);
    }
}
