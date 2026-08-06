<?php

namespace App\Controller;

use App\Enum\UserRole;
use App\Entity\User;
use App\Form\SignUpType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SignUpController extends AbstractController
{
    #[Route('/sign/up', name: 'app_sign_up')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response
    {
        $form = $this->createForm(SignUpType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower((string) $form->get('email')->getData());

            if ($userRepository->findOneBy(['email' => $email]) !== null) {
                $form->get('email')->addError(new FormError('Cette adresse mail est déjà utilisée.'));
            } else {
                $user = new User();
                $user->setFirstName((string) $form->get('firstName')->getData());
                $user->setLastName((string) $form->get('lastName')->getData());
                $user->setEmail($email);
                $user->setRole(UserRole::BUYER);
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        (string) $form->get('password')->getData()
                    )
                );
                $user->setShippingAddress('');
                $user->setBillingAddress('');

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte a bien été créé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_sign_up');
            }
        }

        return $this->render('sign_up/index.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
