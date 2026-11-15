<?php

declare(strict_types=1);

namespace App\Library\Domain\Category;

interface CategoryRepository
{
    public function nextId(): CategoryId;

    public function findById(CategoryId $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function save(Category $category): void;
}
