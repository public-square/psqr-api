<?php

namespace PublicSquare\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/embed')]
class EmbedController extends BaseController
{
    /**
     * Get an Embed.
     */
    #[Route(path: '/', name: 'embed_page', methods: ['GET'])]
    public function embedWidget(Request $request)
    {
        return $this->render('embed/widget.html.twig');
    }
}
