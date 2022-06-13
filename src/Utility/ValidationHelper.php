<?php

declare(strict_types=1);

namespace PublicSquare\Utility;

use Doctrine\ORM\EntityManagerInterface;
use PublicSquare\Entity\Permission;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper Functions for Validation Work.
 */
class ValidationHelper
{
    protected $em;

    public function getEntityManager(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Verify that the structure of the infoHash is valid.
     *
     *
     * @throws \Exception if the infoHash is not valid
     */
    public function validateInfoHash(Request $request, string $infoHash): bool | \Exception
    {
        // determine if variable infoHash is a SHA-1
        $validateInfoHash = (bool) preg_match('/^[0-9a-f]{40}$/i', $infoHash);

        if ($validateInfoHash !== true) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Infohash provided is not a valid SHA-1.',
            ]), 400);
        }

        return $validateInfoHash;
    }

    /**
     * Verify that the provided Distributed Identity has a subdomain that exists in the list of acceptable subdomains.
     *
     * @throws \Exception if the did does not have an acceptable subdomain or if the fetched file is malformed
     */
    public function validateAcceptableDIDSubdomain(Request $request, string $did): string | \Exception
    {
        // open file of acceptable DID Subdomains
        $didConfig = '/../../config/packages/' . $_ENV['APP_ENV'] . '/did_config.json';

        $config = json_decode(file_get_contents(__DIR__ . $didConfig), true);

        // did:web:website.com:u:name-here
        // this splits the did by colon into $matches array
        preg_match_all('/[^:]+/', $did, $matches);

        // get the first 3 elements of the did: did, web, website.com
        $paths = \array_slice($matches[0], 0, 3);

        // gets any additional paths after the first 3 elements and their name (name-here)
        $identifiers = \array_slice($matches[0], 3);

        // combines did,web,website.com into did:web:website.com
        $domain = implode(':', $paths);

        if (str_contains($domain, '/')) {
            $splitDomain = preg_split('#/#', $domain);
        }

        $acceptedDomain = isset($splitDomain) ? $splitDomain[0] : $domain;

        if (!isset($config['accepted_domains'][$acceptedDomain])) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'This is not an acceptable DID subdomain.',
            ]), 400);
        }

        $fileLocation = $config['accepted_domains'][$acceptedDomain];

        if (empty($fileLocation) || \is_bool($fileLocation) === true) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Improperly Configured DID Configuration File.',
            ]), 400);
        }

        $finalLocation = $fileLocation . '/';

        if (isset($splitDomain)) {
            // remove did base and combine to get full path
            array_splice($splitDomain, 0, 1);
            $didPath = implode('/', $splitDomain);

            $finalLocation .= $didPath . '/identity.json';
        } else {
            $finalLocation .= implode('/', $identifiers) . '/identity.json';
        }

        return $finalLocation;
    }

    /**
     * Verify that the Distributed Identity (DID) exists in the file structure. The Throw Exception flag is used for allowing the creation of new DIDs.
     *
     * @param bool $throwException (optional)
     *
     * @throws \Exception if the did does not exist and throwException is set to false
     */
    public function verifyDIDExists(Request $request, string $filename, bool $throwException = true): array | bool | \Exception
    {
        if (file_exists($filename) === true) {
            return json_decode(file_get_contents($filename), true);
        }

        if ($throwException === false) {
            // throw an error in all cases except for the creation of a new DID.JSON file
            return false;
        }

        throw new \Exception(json_encode([
            'apiTarget' => $request->getPathInfo(),
            'httpVerb'  => $request->getMethod(),
            'success'   => false,
            'error'     => 'DID File Does Not Exist.',
        ]), 400);
    }

    /**
     * Validate the permissions assigned to a DID (Distributed Identity) and its KID and return true if capable of doing the requested permissionType operation.
     *
     *
     * @throws \Exception if the file is malformed
     */
    public function validateKIDPermissions(Request $request, string $permissionType, string $kid, array $fileContents): bool | \Exception
    {
        $validated = false;

        // verify that file has properly configured authorization and rules sections
        if (!isset($fileContents['psqr']['permissions'])) {
            throw new \Exception(json_encode([
                'apiTarget' => $request->getPathInfo(),
                'httpVerb'  => $request->getMethod(),
                'success'   => false,
                'error'     => 'Improperly Configured File Contents - Missing Authorization Rules Information.',
            ]), 400);
        }

        // iterate over rules, where the id matches the header KID. If that KID has been granted the right permissions, we are validated.
        foreach ($fileContents['psqr']['permissions'] as $items) {
            if ($items['kid'] !== $kid) {
                continue;
            }

            if (\in_array($permissionType, $items['grant'], true) === true) {
                $validated = true;
            }
        }

        // return true or false
        return $validated;
    }

    /**
     * Verify the permissions assigned to a DID (Distributed Identity) based on its Database Entry.
     */
    public function validatePermission(string $did): bool
    {
        // get permission by $did
        $permission = $this->em->getRepository(Permission::class)->findOneByDid($did);

        // return early if no record found
        if ((null === $permission) === true) {
            return false;
        }

        // return early if permissions are not either admin or curate for this record
        if ($permission->getType() !== Permission::GRANT_CURATE && $permission->getType() !== Permission::GRANT_ADMIN) {
            return false;
        }

        // if did has network access, permissions are valid
        if ($permission->getNetwork() === true) {
            return true;
        }

        return false;
    }

    /**
     * Verify the File Location of a list and fetch its file's contents.
     */
    public function validateFileLocation(string $listName): array
    {
        $listConfig = '/../../config/packages/' . $_ENV['APP_ENV'] . '/list_config.json';

        $config = json_decode(file_get_contents(__DIR__ . $listConfig), true);

        $fileLocation = __DIR__ . '/../../' . $config['accepted_location'] . '/' . $listName . '/index.jsonl';

        return [
            'fileLocation' => $fileLocation,
            'contents'     => file_exists($fileLocation) ? json_decode(file_get_contents($fileLocation), true) : false,
        ];
    }
}
