<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    #[Route('/my-account', name: 'app_my_account')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('account/my_account.html.twig');
    }

    #[Route('/my-account/edit', name: 'app_my_account_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(): Response
    {
        return $this->render('account/edit.html.twig');
    }
}
