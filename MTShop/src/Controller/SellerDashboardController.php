<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_SELLER") or is_granted("ROLE_ADMIN")'))]
final class SellerDashboardController extends AbstractController
{
    #[Route('/seller/dashboard', name: 'app_seller_dashboard', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        FormFactoryInterface $formFactory
    ): Response {
        $currentPage = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $totalProducts = $productRepository->countAllProducts();

        $totalPages = max(
            1,
            (int) ceil($totalProducts / $limit)
        );

        $currentPage = min($currentPage, $totalPages);

        $products = $productRepository->findPaginatedProducts(
            $currentPage,
            $limit
        );

        // Formulaire de création
        $newProduct = new Product();

        $newProductForm = $formFactory->createNamed(
            'product_new',
            ProductType::class,
            $newProduct,
            [
                'action' => $this->generateUrl('app_seller_product_new'),
                'method' => 'POST',
            ]
        );

        // Formulaires de modification
        $editProductForms = [];

        foreach ($products as $product) {
            $editProductForms[$product->getId()] = $formFactory->createNamed(
                'product_edit_' . $product->getId(),
                ProductType::class,
                $product,
                [
                    'action' => $this->generateUrl(
                        'app_seller_product_edit',
                        ['id' => $product->getId()]
                    ),
                    'method' => 'POST',
                ]
            )->createView();
        }

        return $this->render('seller/dashboard.html.twig', [
            'products' => $products,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newProductForm' => $newProductForm->createView(),
            'editProductForms' => $editProductForms,
        ]);
    }

    #[Route('/seller/product/new', name: 'app_seller_product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $product = new Product();

        $form = $this->createForm(
            ProductType::class,
            $product
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid('product_', true)
                    . '.'
                    . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/uploads/products',
                        $newFilename
                    );

                    $product->setImageName($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash(
                        'danger',
                        'Impossible d\'enregistrer l\'image du produit.'
                    );
                }
            }

            $product->setSlug(
                strtolower(
                    trim(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $product->getName()
                        )
                    )
                )
            );

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Produit ajouté avec succès.'
            );

            return $this->redirectToRoute(
                'app_seller_dashboard'
            );
        }

        return $this->render('seller/new.html.twig', [
            'productForm' => $form->createView(),
        ]);
    }

    #[Route('/seller/product/{id}/edit', name: 'app_seller_product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(
            ProductType::class,
            $product,
            [
                'action' => $this->generateUrl(
                    'app_seller_product_edit',
                    ['id' => $product->getId()]
                ),
                'method' => 'POST',
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid('product_', true)
                    . '.'
                    . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/uploads/products',
                        $newFilename
                    );

                    $product->setImageName($newFilename);
                } catch (FileException $exception) {
                    $this->addFlash(
                        'danger',
                        'Impossible d\'enregistrer l\'image du produit.'
                    );
                }
            }

            $product->setSlug(
                strtolower(
                    trim(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $product->getName()
                        )
                    )
                )
            );

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Produit modifié avec succès.'
            );

            return $this->redirectToRoute(
                'app_seller_dashboard'
            );
        }

        return $this->render('seller/edit.html.twig', [
            'productForm' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/seller/product/{id}/delete', name: 'app_seller_product_delete', methods: ['POST'])]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'delete_product_' . $product->getId(),
                (string) $request->request->get('_token', '')
            )
        ) {
            $this->addFlash(
                'danger',
                'Jeton CSRF invalide.'
            );

            return $this->redirectToRoute(
                'app_seller_dashboard'
            );
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Produit supprimé avec succès.'
        );

        return $this->redirectToRoute(
            'app_seller_dashboard'
        );
    }
}