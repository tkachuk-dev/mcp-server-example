<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    // The json_login authenticator handles the request before this action is reached.
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
