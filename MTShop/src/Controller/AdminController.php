<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(UserRepository $userRepository, ProductRepository $productRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $userRepository->countAllUsers(),
            'totalProducts' => $productRepository->countAllProducts(),
            'totalAdmins' => count($userRepository->findBy(['role' => UserRole::ADMIN->value])),
            'totalSellers' => count($userRepository->findBy(['role' => UserRole::SELLER->value])),
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(Request $request, UserRepository $userRepository): Response
    {
        $currentPage = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $totalUsers = $userRepository->countAllUsers();
        $totalPages = max(1, (int) ceil($totalUsers / $limit));
        $currentPage = min($currentPage, $totalPages);

        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findPaginatedUsers($currentPage, $limit),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'roleOptions' => [
                ['value' => UserRole::BUYER->value, 'label' => 'Acheteur'],
                ['value' => UserRole::SELLER->value, 'label' => 'Vendeur'],
                ['value' => UserRole::ADMIN->value, 'label' => 'Administrateur'],
            ],
        ]);
    }

    #[Route('/admin/sellers', name: 'app_admin_sellers', methods: ['GET'])]
    public function sellers(): Response
    {
        return $this->redirectToRoute('app_seller_dashboard');
    }

    #[Route('/admin/users/{id}/role', name: 'app_admin_user_update_role', methods: ['POST'])]
    public function updateRole(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_role_' . $user->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Votre demande n\'a pas pu être vérifiée.');

            return $this->redirectToRoute('app_admin_users', [
                'page' => max(1, (int) $request->request->get('page', 1)),
            ]);
        }

        $roleValue = (string) $request->request->get('role', '');
        try {
            $newRole = UserRole::from($roleValue);
        } catch (\ValueError) {
            $this->addFlash('danger', 'Le rôle sélectionné est invalide.');

            return $this->redirectToRoute('app_admin_users', [
                'page' => max(1, (int) $request->request->get('page', 1)),
            ]);
        }

        if ($user->getRole() === $newRole) {
            $this->addFlash('warning', 'L\'utilisateur possède déjà ce rôle.');

            return $this->redirectToRoute('app_admin_users', [
                'page' => max(1, (int) $request->request->get('page', 1)),
            ]);
        }

        $user->setRole($newRole);
        $entityManager->flush();

        $this->addFlash('success', 'Le rôle de l\'utilisateur a bien été mis à jour.');

        return $this->redirectToRoute('app_admin_users', [
            'page' => max(1, (int) $request->request->get('page', 1)),
        ]);
    }
}
