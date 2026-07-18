<?php

namespace App\Controller;

use App\Form\SignUpType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SignUpController extends AbstractController
{
    #[Route('/sign/up', name: 'app_sign_up')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(SignUpType::class);
        $form->handleRequest($request);

        return $this->render('sign_up/index.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
