<?php

namespace PublicSquare\Service;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;

class JWSValidator
{
    private DIDGetter $didGetter;
    private JWSSerializerManager $serializerManager;
    private JWSVerifier $jwsVerifier;

    public function __construct()
    {
        $algorithmManager  = new AlgorithmManager([new ES384()]);
        $this->jwsVerifier = new JWSVerifier(
            $algorithmManager
        );
        $this->serializerManager = new JWSSerializerManager([
            new CompactSerializer(),
        ]);
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setDidGetter(DIDGetter $didGetter)
    {
        $this->didGetter = $didGetter;
    }

    /**
     * validate jws token string with pubkey from specified did.
     *
     * @param string $token jws token string
     *
     * @return bool is it valid
     */
    public function validateJWS(string $token): bool
    {
        $jws = $this->serializerManager->unserialize($token);
        $kid = $jws->getSignatures()[0]->getProtectedHeader()['kid'];

        $didDoc = $this->didGetter->getDistributedIdentity($kid);

        // try to find valid public keys
        $keys   = $didDoc['psqr']['publicKeys'];
        $pubKey = false;

        for ($i = 0; $i < \count($keys); ++$i) {
            $k = $keys[$i];
            if ($k['kid'] === $kid) {
                $pubKey = new JWK((array) $k);

                break;
            }
        }
        // return false if no pubKey was found
        if ($pubKey === false) {
            return false;
        }

        return $this->jwsVerifier->verifyWithKey($jws, $pubKey, 0);
    }
}
