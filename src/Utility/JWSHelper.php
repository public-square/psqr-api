<?php

declare(strict_types=1);

namespace PublicSquare\Utility;

use Jose\Component\Signature\JWS;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use PublicSquare\Service\JWSValidator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper Functions associated with the JWSValidator Service and Jose.
 */
class JWSHelper
{
    private JWSValidator $jwsValidator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setJwsValidator(JWSValidator $jwsValidator)
    {
        $this->jwsValidator = $jwsValidator;
    }

    /**
     * Unpack the JWS from the body from the request header. Validate the JWS and return false or JWS token.
     *
     *
     * @throws \Exception if the JSON string is malformed
     */
    public function unpackAndValidateJWS(Request $request, array $content): array | \Exception
    {
        // verify token exists
        if (isset($content['token']) === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'JSON does not contain token property.',
            ]), 400);
        }

        // validate jws
        try {
            $jwsValid = $this->jwsValidator->validateJWS($content['token']);
        } catch (\Throwable $e) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => $e->getMessage(),
            ]), 400);
        }

        if ($jwsValid === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'JWS is invalid.',
            ]), 400);
        }

        return $content;
    }

    /**
     * Unpack the JWS from the body from the request header. Unserialize the JWS to get the Payload and Header and return it.
     *
     *
     * @throws \Exception if the JSON string is malformed
     */
    public function unpackAndSerializeJWS(Request $request, array $content): JWS | \Exception
    {
        // validate jws
        if (isset($content['token']) === false) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'JSON does not contain token property.',
            ]), 400);
        }

        // unpack JWS via Compact Serializer to get Payload and Header
        $serializerManager = new JWSSerializerManager([
            new CompactSerializer(),
        ]);

        try {
            $jws = $serializerManager->unserialize($content['token']);
        } catch (\Throwable $e) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => $e->getMessage(),
            ]), 400);
        }

        return $jws;
    }
}
