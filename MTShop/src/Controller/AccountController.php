<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/my-account/edit/email', name: 'app_my_account_update_email', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateEmail(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('account_update_email', $token)) {
            $this->addFlash('danger', 'Your request could not be verified.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Please enter a valid email address.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $existingUser = $userRepository->findOneBy(['email' => $email]);
        if (null !== $existingUser && $existingUser !== $user) {
            $this->addFlash('danger', 'This email address is already used.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user->setEmail($email);
        $entityManager->flush();

        $this->addFlash('success', 'Your email address has been updated.');

        return $this->redirectToRoute('app_my_account_edit');
    }
}
