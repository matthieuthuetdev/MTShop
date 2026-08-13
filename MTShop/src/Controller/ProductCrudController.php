<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_SELLER") or is_granted("ROLE_ADMIN")'))]
final class ProductCrudController extends AbstractController
{
    #[Route('/seller/products', name: 'product_crud_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findBy([], ['name' => 'ASC']);

        return $this->render('product_crud/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/seller/products/new', name: 'product_crud_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSlug(strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $product->getName()))));
            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('product_crud_index');
        }

        return $this->render('product_crud/new.html.twig', [
            'productForm' => $form->createView(),
        ]);
    }

    #[Route('/seller/products/{id}', name: 'product_crud_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product_crud/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/seller/products/{id}/edit', name: 'product_crud_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setSlug(strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $product->getName()))));
            $entityManager->flush();

            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('product_crud_index');
        }

        return $this->render('product_crud/edit.html.twig', [
            'productForm' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/seller/products/{id}', name: 'product_crud_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-product-' . $product->getId(), (string) $request->request->get('_token', ''))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('product_crud_index');
    }
}
