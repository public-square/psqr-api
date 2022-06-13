<?php

namespace PublicSquare\Service;

use Doctrine\Persistence\ManagerRegistry;
use FOS\ElasticaBundle\Doctrine\ORM\ElasticaToModelTransformer;

class ElasticaPSQRTransformer extends ElasticaToModelTransformer
{
    public function __construct(ManagerRegistry $registry, string $objectClass = 'NOT USED', array $options = [])
    {
        parent::__construct($registry, $objectClass, $options);
    }

    public function transform(array $elasticaObjects): array
    {
        // if index, score, etc are not needed use getData
        return array_map(function ($elasticaObject) {
            return $elasticaObject->getHit();
        }, $elasticaObjects);
    }
}
