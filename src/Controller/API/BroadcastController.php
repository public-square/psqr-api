<?php

declare(strict_types=1);

namespace PublicSquare\Controller\API;

use OpenApi\Annotations as SWG;
use Psr\Log\LoggerInterface;
use PublicSquare\Controller\BaseController;
use PublicSquare\Message\Event\PutContentEvent;
use PublicSquare\Utility\JWSHelper;
use PublicSquare\Utility\ValidationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/broadcast', requirements: ['_format' => 'json'])]
class BroadcastController extends BaseController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @SWG\Put(
     *      path="/api/broadcast/{infoHash}",
     *      tags={"Broadcaster"},
     *      summary="Append Data to the Broadcaster for Ingestion.",
     *      description="<div>
     Retrieve and unpack a JSON Web Signature (JWS) from the request header. Validate it. Extract the contents of the payload.
     <br/>
     Create a MessageBus Event to dispatch the content, which will then get consumed by the ingester.
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
     *          description="JWS Token"
     *      ),
     *      @SWG\Response(
     *          response="200",
     *          description="OK",
     *          content={
     *              @SWG\MediaType(
     *                  mediaType="application/json",
     *                  example={
     *                      "apiTarget": "/api/broadcast/{infoHash}",
     *                      "httpVerb": "PUT",
     *                      "success": true,
     *                      "content": {}
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
     *                      "apiTarget": "/api/broadcast/{infoHash}",
     *                      "httpVerb": "PUT",
     *                      "success": false,
     *                      "error": "..."
     *                  }
     *              )
     *          }
     *      )
     * )
     */
    #[Route(path: '/{infoHash}', name: 'api_broadcast_put', options: ['expose' => true], methods: ['PUT'])]
    public function putBroadcastContent(Request $request, $infoHash, MessageBusInterface $bus, JWSHelper $jwsHelper)
    {
        $validationHelper = new ValidationHelper();

        // validate SHA-1 InfoHash
        $validationHelper->validateInfoHash($request, $infoHash);

        $body = $request->request->all();

        // json decode body, retrieve token, validate jws of token
        $content = $jwsHelper->unpackAndValidateJWS($request, $body);

        // create post event
        $event = new PutContentEvent($content, $infoHash, time());

        $bus->dispatch($event);

        $this->logger->notice('Broadcaster Content - ' . $infoHash . ' Created', ['data' => $body]);

        return $this->json([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => true,
            'content'   => $content,
        ]);
    }
}
