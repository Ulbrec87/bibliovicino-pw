<?php

declare(strict_types=1);

namespace App\Library\Domain\Category;

use InvalidArgumentException;

final class Category
{
    private function __construct(
        private CategoryId $id,
        private string $name,
        private string $slug,
        private bool $active,
    ) {
        $this->name = self::normaliseName($name);
        $this->slug = self::normaliseSlug($slug);
    }

    public static function create(CategoryId $id, string $name, string $slug, bool $active = true): self
    {
        return new self($id, $name, $slug, $active);
    }

    public function id(): CategoryId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    private static function normaliseName(string $name): string
    {
        $name = trim($name);
        $length = mb_strlen($name);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('A Category name must contain between 1 and 255 characters.');
        }

        return $name;
    }

    private static function normaliseSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));

        if (mb_strlen($slug) < 1 || mb_strlen($slug) > 100 || 1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new InvalidArgumentException('A Category slug must be canonical and contain between 1 and 100 characters.');
        }

        return $slug;
    }
}
