<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Image;

final class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'required' => true,
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                ],
            ])

            ->add('price', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'required' => true,

                // Utilise un véritable input HTML5 de type number.
                'html5' => true,

                // Deux chiffres maximum après la virgule.
                'scale' => 2,

                'attr' => [
                    'min' => '0',
                    'step' => '0.01',
                    'inputmode' => 'decimal',
                ],

                // Vérification côté serveur.
                'constraints' => [
                    new GreaterThanOrEqual(0),
                ],
            ])

            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'required' => true,
            ])

            ->add('promotion', IntegerType::class, [
                'label' => 'Promotion (%)',
                'required' => false,
            ])

            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
            ])

            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier PNG, JPEG ou WebP.',
                    ]),
                ],
            ])

            ->add('isActive', CheckboxType::class, [
                'label' => 'Produit actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}