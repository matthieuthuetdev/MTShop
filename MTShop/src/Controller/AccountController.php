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
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Veuillez saisir une adresse mail valide.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $existingUser = $userRepository->findOneBy(['email' => $email]);
        if (null !== $existingUser && $existingUser !== $user) {
            $this->addFlash('danger', 'Cette adresse mail est déjà utilisée.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user->setEmail($email);
        $entityManager->flush();

        $this->addFlash('success', 'Votre adresse mail a été mise à jour.');

        return $this->redirectToRoute('app_my_account_edit');
    }

    #[Route('/my-account/edit/basic-information', name: 'app_my_account_update_basic_information', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateBasicInformation(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('account_update_basic_information', $token)) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $birthDateRaw = trim((string) $request->request->get('birthDate', ''));

        if ('' === $firstName || '' === $lastName) {
            $this->addFlash('danger', 'Le prénom et le nom sont obligatoires.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        if ('' !== $birthDateRaw) {
            $birthDate = \DateTimeImmutable::createFromFormat('Y-m-d', $birthDateRaw);

            if (false === $birthDate) {
                $this->addFlash('danger', 'Veuillez saisir une date de naissance valide.');

                return $this->redirectToRoute('app_my_account_edit');
            }

            $user->setBirthDate($birthDate);
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $entityManager->flush();

        $this->addFlash('success', 'Vos informations de base ont été mises à jour.');

        return $this->redirectToRoute('app_my_account_edit');
    }

    #[Route('/my-account/edit/address', name: 'app_my_account_update_address', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateAddress(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('account_update_address', $token)) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $shippingAddress = trim((string) $request->request->get('shippingAddress', ''));
        $billingAddress = trim((string) $request->request->get('billingAddress', ''));

        if ('' === $shippingAddress || '' === $billingAddress) {
            $this->addFlash('danger', 'Les adresses de livraison et de facturation sont obligatoires.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user->setShippingAddress($shippingAddress);
        $user->setBillingAddress($billingAddress);
        $entityManager->flush();

        $this->addFlash('success', 'Vos adresses postales ont été mises à jour.');

        return $this->redirectToRoute('app_my_account_edit');
    }

    #[Route('/my-account/edit/two-factor', name: 'app_my_account_update_two_factor', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateTwoFactor(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('account_update_two_factor', $token)) {
            $this->addFlash('danger', 'Votre demande n’a pas pu être vérifiée.');

            return $this->redirectToRoute('app_my_account_edit');
        }

        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        $twoFactorEnabled = '1' === (string) $request->request->get('twoFactorEnabled', '0');
        $user->setTwoFactorEnabled($twoFactorEnabled);

        if (!$twoFactorEnabled) {
            $user->setTwoFactorCodeHash(null);
            $user->setTwoFactorCodeExpiresAt(null);
        }

        $entityManager->flush();

        $this->addFlash('success', $twoFactorEnabled ? 'La double authentification a été activée.' : 'La double authentification a été désactivée.');

        return $this->redirectToRoute('app_my_account_edit');
    }
}
