<?php

declare(strict_types=1);

namespace PublicSquare\Utility;

use PublicSquare\Service\DIDGetter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper Functions pertaining to the DIDGetter Service.
 */
class DIDGetterHelper
{
    public function __construct(protected DIDGetter $didGetter)
    {
    }

    /**
     * Verifies the existence of a Distributed Identity given the DIDGetter Service and returns an array of the results.
     *
     *
     * @throws \Exception if an error occurs during the retrieval of the DID
     */
    public function validateDistributedIdentity(Request $request, string $did): array | \Exception
    {
        try {
            $didObject = $this->didGetter->getDistributedIdentity($did);
        } catch (\Throwable $e) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => $e->getMessage(),
            ]), 400);
        }

        // convert stdObject to array
        return json_decode(json_encode($didObject), true);
    }
}
