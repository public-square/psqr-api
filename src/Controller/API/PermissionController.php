<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use Doctrine\ORM\EntityManager;
use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Entity\Permission;
use PublicSquare\Utility\JWSHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/permission', requirements: ['_format' => 'json'])]
class PermissionController extends BaseController
{
    /**
     * @SWG\Get(
     *      path="/api/permission",
     *      tags={"Permission"},
     *      summary="Get permissions associated with a Distributed Identity.",
     *      description="<div>
     Unpack a JSON Web Signature (JWS) in the request header to get the Distributed Identity.
     <br/>
     Query, fetch, and collate all permissions associated with the Distributed Identity.
     <br/>
     </div>",
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
     *                      "apiTarget": "/api/permission",
     *                      "httpVerb": "GET",
     *                      "success": true,
     *                      "permissions": {},
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
     *                      "apiTarget": "/api/permission",
     *                      "httpVerb": "GET",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '', name: 'api_permission', options: ['expose' => true], methods: ['GET'])]
    public function getPermissions(Request $request, JWSHelper $jwsHelper, EntityManager $em)
    {
        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        // get header
        $header = $jws->getSignatures()[0]->getProtectedHeader();

        // unpack header into its based did
        $did = explode('#', $header['kid'])[0];

        // get all permissions attached to did
        $permissionsQuery = $em->createQueryBuilder()
            ->select('p')
            ->from(Permission::class, 'p')
            ->where('p.did = :did')
            ->setParameter('did', $did)
            ->getQuery()
            ->getResult()
        ;

        // if no DB Entry found, throw error
        if (\count($permissionsQuery) === 0) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'User Grant Permissions Do Not Exist in Database.',
            ]), 400);
        }

        $permissions = [];

        // iterate over permissions and collate grants
        foreach ($permissionsQuery as $permission) {
            $data = [
                'aggregation' => $permission->getAggregation() !== null ? $permission->getAggregation()->getName() : null,
                'network'     => $permission->getNetwork(),
                'publisher'   => $permission->getPublisher(),
                'type'        => $permission->getType() === 1 ? 'Publish' : ($permission->getType() === 2 ? 'Curate' : 'Admin'),
                'did'         => $permission->getDid(),
                'kid'         => $permission->getKid(),
            ];

            $permissions[] = $data;
        }

        return $this->json([
            'apiTarget'   => $request->getPathInfo(),
            'httpVerb'    => $request->getMethod(),
            'success'     => true,
            'permissions' => $permissions,
        ]);
    }
}
