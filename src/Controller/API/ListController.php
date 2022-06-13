<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Entity\Aggregation;
use PublicSquare\Entity\Permission;
use PublicSquare\Utility\JWSHelper;
use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/list', requirements: ['_format' => 'json'])]
class ListController extends BaseController
{
    /**
     * @SWG\Put(
     *      path="/api/list/{listName}",
     *      tags={"List"},
     *      summary="Create or Edit a List.",
     *      description="<div>
     Verify the existence of the list based on its list name and the authorization of the accompanying JSON Web Signature (JWS) Token in the request header.
     <br/>
     If the List with desired List Name exists and the Distributed Identity (did) is authorized, update the list with changes.
     <br/>
     If the List does not exist and the did is authorized, create the list.
     <br />
     Update the Aggregations based on the above and throw errors for all other possible combinations not listed.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="listName",
     *          in="path",
     *          required=true,
     *          description="Name of the List to Create or Update."
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
     *                      "apiTarget": "/api/list/{listName}",
     *                      "httpVerb": "PUT",
     *                      "success": true,
     *                      "list": "...",
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
     *                      "apiTarget": "/api/list/{listName}",
     *                      "httpVerb": "PUT",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{listName}', name: 'api_list_create_update', options: ['expose' => true], methods: ['PUT'])]
    public function putList(Request $request, $listName, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        // get payload and header
        $payload = $jws->getPayload();
        $header  = $jws->getSignatures()[0]->getProtectedHeader();

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

        // verify if listname has impromper characters for a directory name
        if (strpbrk($listName, '\\/?%*:|"<>') !== false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Listname contains invalid characters.',
            ]), 400);
        }

        // get location using list config
        $listname = $validationHelper->validateFileLocation($listName);

        // find if aggregation exists
        $listDBEntry = $this->getDoctrine()->getRepository(Aggregation::class)->findOneByName($listName);

        // check for aggregation - permission combo
        $grantPermission = null !== $listDBEntry ? $this->getDoctrine()->getRepository(Permission::class)->findOneBy([
            'did'         => $did,
            'aggregation' => $listDBEntry->getId(),
        ]) : null;

        // if file exists, and either the owner section does not exist or it exists but we arent the owner, throw an error
        if ($listname['contents'] !== false) {
            // if file exists but aggregation db entry doesnt, throw error
            if (null === $listDBEntry) {
                throw new \Exception(json_encode([
                    'apiTarget' => $request->getPathInfo(),
                    'httpVerb'  => $request->getMethod(),
                    'success'   => false,
                    'error'     => 'Listname does not exist.',
                ]), 400);
            }

            if ($permission->getNetwork() !== true && null === $grantPermission) {
                // if no network access, and there is no did - aggregation pairing in the db, throw an error
                throw new \Exception(json_encode([
                    'apiTarget' => $request->getPathInfo(),
                    'httpVerb'  => $request->getMethod(),
                    'success'   => false,
                    'error'     => 'You have not been granted access to this List.',
                ]), 400);
            }
        } else {
            // if file doesn't exist
            $aggregation = $listDBEntry;

            // if aggregation doesn't exist in db
            if (null === $listDBEntry) {
                $aggregation = new Aggregation();
                $aggregation->setName($listName);
                $aggregation->setType(Aggregation::LIST_TYPE);
                $this->getDoctrine()->getManager()->persist($aggregation);
            }

            // if new, grant permission to this list to did unless did has network true boolean
            if ($permission->getNetwork() !== true) {
                if (null === $grantPermission) {
                    $newCombo = new Permission();
                    $newCombo->setAggregation($aggregation);
                    $newCombo->setNetwork($permission->getNetwork());
                    $newCombo->setPublisher($permission->getPublisher());
                    $newCombo->setType($permission->getType());
                    $newCombo->setDid($did);
                    $newCombo->setKid($kid);
                    $this->getDoctrine()->getManager()->persist($newCombo);
                }
            }

            $this->getDoctrine()->getManager()->flush();
        }

        $filesystem = new Filesystem();

        // create/update file via token payload
        $filesystem->dumpFile($listname['fileLocation'], $payload);

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'list'      => $listName,
        ]);
    }

    /**
     * @SWG\Delete(
     *      path="/api/list/{listName}",
     *      tags={"List"},
     *      summary="Delete a List.",
     *      description="<div>
     Verify the existence of the list with the list name and the authorization of the accompanying JSON Web Signature (JWS) Token in the request header.
     <br/>
     If the List with desired List Name exists and the Distributed Identity (did) is authorized, delete the list.
     <br/>
     Update the associated aggregations based on said deletion.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="listName",
     *          in="path",
     *          required=true,
     *          description="Name of the List to Create or Update."
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
     *                      "apiTarget": "/api/list/{listName}",
     *                      "httpVerb": "DELETE",
     *                      "success": true,
     *                      "list": "...",
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
     *                      "apiTarget": "/api/list/{listName}",
     *                      "httpVerb": "DELETE",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{listName}', name: 'api_list_delete', options: ['expose' => true], methods: ['DELETE'])]
    public function deleteList(Request $request, $listName, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        // get header
        $header = $jws->getSignatures()[0]->getProtectedHeader();

        $kid   = $header['kid'];
        $did   = explode('#', $header['kid'])[0];
        $grant = explode('#', $header['kid'])[1];

        if ($grant !== 'curate' && $grant !== 'admin') {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid JWS Signature Permission to Do Operation.',
            ]), 400);
        }

        // get user grant permissions from DB
        $permission = $this->getDoctrine()->getRepository(Permission::class)->findOneByDid($did);

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

        // validate permissions of the did (network access, curate permission, existance)
        $validPermissions = $validationHelper->validatePermission($did);

        if ($validPermissions === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid DID Permissions to Do Operation.',
            ]), 400);
        }

        $listname = $validationHelper->validateFileLocation($listName);

        // if file contents are improper
        if ($listname['contents'] === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'List File Does Not Exist.',
            ]), 400);
        }

        // find aggregation in DB
        $listDBEntry = $this->getDoctrine()->getRepository(Aggregation::class)->findOneByName($listName);

        if (null === $listDBEntry) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Listname does not exist in DB.',
            ]), 400);
        }

        // verify if the signing key has curate on the list
        if ($permission->getNetwork() !== true) {
            $grantPermission = $this->getDoctrine()->getRepository(Permission::class)->findOneBy([
                'did'         => $did,
                'aggregation' => $listDBEntry->getId(),
            ]);

            if (null === $grantPermission) {
                throw new \Exception(json_encode([
                    'apiTarget' => $request->getPathInfo(),
                    'httpVerb'  => $request->getMethod(),
                    'success'   => false,
                    'error'     => 'You have not been granted access to this List.',
                ]), 400);
            }
        }

        // get directory name for checking if directory is empty after folder removal
        $dirname = pathinfo($listname['fileLocation'], PATHINFO_DIRNAME);

        $filesystem = new Filesystem();

        $filesystem->remove($listname['fileLocation']);

        // query directory for files post index.jsonl removal. If empty, remove directory
        $queryDirectory = (\count(glob("{$dirname}/*")) === 0) ? 'Empty' : 'Not empty';

        if ($queryDirectory === 'Empty') {
            $filesystem->remove($dirname);
        }

        // remove from DB with cascade
        $this->getDoctrine()->getManager()->remove($listDBEntry);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'response'  => 'File Successfully Deleted.',
        ]);
    }
}
