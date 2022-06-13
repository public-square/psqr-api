<?php

namespace PublicSquare\Controller;

use Elastica\Client as ElasticaClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected $esClient;

    public function initEsClient()
    {
        $esConfig = [
            'host'      => $_ENV['ELASTICSEARCH_URL'],
            'port'      => $_ENV['ELASTICSEARCH_PORT'],
            'username'  => $_ENV['ELASTICSEARCH_USERNAME'],
            'password'  => $_ENV['ELASTICSEARCH_PASSWORD'],
            'transport' => 'https',
        ];

        $this->esClient = new ElasticaClient($esConfig);
    }
}
