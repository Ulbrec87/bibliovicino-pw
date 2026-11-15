<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Domain\Category\Category;
use App\Library\Domain\Category\CategoryId;
use App\Library\Domain\Category\CategoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function nextId(): CategoryId
    {
        return CategoryId::fromString(Uuid::v7()->toRfc4122());
    }

    public function findById(CategoryId $id): ?Category
    {
        return $this->registry->getManager()->find(Category::class, $id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->registry->getManager()->getRepository(Category::class)->findOneBy(['slug' => $slug]);
    }

    public function save(Category $category): void
    {
        $this->registry->getManager()->persist($category);
        $this->registry->getManager()->flush();
    }
}
