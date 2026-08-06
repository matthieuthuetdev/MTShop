<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function findAlphabeticalActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Product[]
     */
    public function findPromotedActiveProducts(int $limit = 3): array
    {
        $products = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :isActive')
            ->andWhere('p.promotion > 0')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        if (
            count($products) <= $limit ||
            count($products) === 0
        ) {
            return $products;
        }

        shuffle($products);

        return array_slice($products, 0, $limit);
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
