<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class DefaultController
{
    public function test():Response
    {
        return new Response('OK');
    }
}