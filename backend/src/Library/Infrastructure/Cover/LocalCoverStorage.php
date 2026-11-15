<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\Cover\CoverStorage;
use App\Library\Application\Cover\ProcessedCover;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final readonly class LocalCoverStorage implements CoverStorage
{
    public function __construct(private string $root)
    {
    }

    public function write(string $copyId, string $reference, ProcessedCover $images): void
    {
        $directory = $this->directory($copyId, $reference);
        if (file_exists($directory) || !mkdir($directory, 0700, true)) {
            throw new RuntimeException('Cover storage unavailable.');
        }
        foreach (['thumbnail' => $images->thumbnail, 'large' => $images->large] as $variant => $blob) {
            $file = $directory.'/'.$variant.'.webp';
            if (file_put_contents($file, $blob) !== strlen($blob) || !chmod($file, 0600)) {
                throw new RuntimeException('Cannot store cover.');
            }
        }
    }

    public function open(string $copyId, string $reference, string $variant): mixed
    {
        if (!in_array($variant, ['thumbnail', 'large'], true)) {
            return null;
        }
        $path = $this->directory($copyId, $reference).'/'.$variant.'.webp';
        if (!is_file($path) || is_link($path)) {
            return null;
        }
        return fopen($path, 'rb') ?: null;
    }

    public function delete(string $copyId, string $reference): void
    {
        $directory = $this->directory($copyId, $reference);
        if (!is_dir($directory)) {
            return;
        }
        foreach (['thumbnail', 'large'] as $variant) {
            $file = $directory.'/'.$variant.'.webp';
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('Cannot remove cover.');
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Cannot remove cover directory.');
        }
    }

    public function candidates(): iterable
    {
        foreach (glob($this->root.'/*/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $copyId = basename(dirname($directory));
            $reference = basename($directory);
            if (Uuid::isValid($copyId) && Uuid::isValid($reference) && !is_link(dirname($directory)) && !is_link($directory)) {
                yield ['copyId' => $copyId, 'reference' => $reference, 'modifiedAt' => filemtime($directory)];
            }
        }
    }

    private function directory(string $copyId, string $reference): string
    {
        if (!Uuid::isValid($copyId) || !Uuid::isValid($reference)) {
            throw new RuntimeException('Invalid storage identifier.');
        }
        $parent = $this->root.'/'.$copyId;
        $directory = $parent.'/'.$reference;
        if (is_link($this->root) || is_link($parent) || is_link($directory)) {
            throw new RuntimeException('Unsafe storage directory.');
        }
        return $directory;
    }
}
