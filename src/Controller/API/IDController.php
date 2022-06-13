<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use PublicSquare\Controller\BaseController;
use PublicSquare\Utility\JWSHelper;
use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/identity', requirements: ['_format' => 'json'])]
class IDController extends BaseController
{
    /**
     * @SWG\Put(
     *      path="/api/identity/{did}",
     *      tags={"Identity"},
     *      summary="Create or Update a Distributed Identity.",
     *      description="<div>
     Validate the Distributed Identity (did) in the path against a did in the JSON Web Signature (JWS) of the request header.
     <br/>
     Verify existence of did, and associated permissions with the JWS's did.
     <br/>
     Create or update the associated did if authorization is met.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="did",
     *          in="path",
     *          required=true,
     *          description="Distributed Identity to Create or Update."
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
     *                      "apiTarget": "/api/identity/{did}",
     *                      "httpVerb": "PUT",
     *                      "success": true,
     *                      "did": "..."
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
     *                      "apiTarget": "/api/identity/{did}",
     *                      "httpVerb": "PUT",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{did}', name: 'api_did_create_update', options: ['expose' => true], methods: ['PUT'])]
    public function putDID(Request $request, $did, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        // validate acceptable DID Subdomains
        $didFilename = $validationHelper->validateAcceptableDIDSubdomain($request, $did);

        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        // get payload and header
        $payload = json_decode($jws->getPayload(), true);
        $header  = $jws->getSignatures()[0]->getProtectedHeader();

        // get file contents (will return file contents or false)
        $didContents = $validationHelper->verifyDIDExists($request, $didFilename, false);

        // if file exists, verify if admin provenance exists in file; else verify admin exists in payload
        if ($didContents !== false) {
            $existingAdminKey = $validationHelper->validateKIDPermissions($request, 'admin', $header['kid'], $didContents);
        } else {
            $existingAdminKey = $validationHelper->validateKIDPermissions($request, 'admin', $header['kid'], $payload);
        }

        if ($existingAdminKey === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid Permissions to Do Operation.',
            ]), 400);
        }

        $filesystem = new Filesystem();

        // create/update file via token payload
        $filesystem->dumpFile($didFilename, json_encode($payload));

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'did'       => $did,
        ]);
    }

    /**
     * @SWG\Delete(
     *      path="/api/identity/{did}",
     *      tags={"Identity"},
     *      summary="Delete a Distributed Identity.",
     *      description="<div>
     Validate the Distributed Identity (did) in the path against a did in the JSON Web Signature (JWS) of the request header.
     <br/>
     Verify existence of the did, and associated permissions with the JWS's did.
     <br/>
     Delete the associated did if authorizations are met.
     <br/>
     </div>",
     *      @SWG\Parameter(
     *          name="did",
     *          in="path",
     *          required=true,
     *          description="Distributed Identity to Create or Update."
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
     *                      "apiTarget": "/api/identity/{did}",
     *                      "httpVerb": "DELETE",
     *                      "success": true,
     *                      "did": "...",
     *                      "response": "..."
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
     *                      "apiTarget": "/api/identity/{did}",
     *                      "httpVerb": "DELETE",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{did}', name: 'api_did_delete', options: ['expose' => true], methods: ['DELETE'])]
    public function deleteDID(Request $request, $did, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        // validate acceptable DID Subdomains
        $didFilename = $validationHelper->validateAcceptableDIDSubdomain($request, $did);

        // get JWS for validation and use
        $body = $request->request->all();

        // unpack and serialize jws
        $jws = $jwsHelper->unpackAndSerializeJWS($request, $body);

        $header = $jws->getSignatures()[0]->getProtectedHeader();

        // get file contents (will return file contents or false)
        $didContents = $validationHelper->verifyDIDExists($request, $didFilename);

        $existingAdminKey = $validationHelper->validateKIDPermissions($request, 'admin', $header['kid'], $didContents);

        if ($existingAdminKey === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Invalid Permissions to Do Operation.',
            ]), 400);
        }

        // get directory name for checking if directory is empty after folder removal
        $dirname = pathinfo($didFilename, PATHINFO_DIRNAME);

        $filesystem = new Filesystem();

        $filesystem->remove($didFilename);

        // query directory for files post did.json removal. if empty, remove directory
        $queryDirectory = (\count(glob("{$dirname}/*")) === 0) ? 'Empty' : 'Not empty';

        if ($queryDirectory === 'Empty') {
            $filesystem->remove($dirname);
        }

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'did'       => $did,
            'response'  => 'File Successfully Deleted.',
        ]);
    }
}
