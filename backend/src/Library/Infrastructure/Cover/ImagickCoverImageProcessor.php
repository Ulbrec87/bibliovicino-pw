<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\Cover\CoverImageProcessor;
use App\Library\Application\Cover\InvalidCoverImage;
use App\Library\Application\Cover\ProcessedCover;
use Imagick;
use ImagickException;

final class ImagickCoverImageProcessor implements CoverImageProcessor
{
    public const int MAX_BYTES = 10000000;

    public function process(string $temporaryPath): ProcessedCover
    {
        $size = is_file($temporaryPath) ? filesize($temporaryPath) : false;
        if (false === $size || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidCoverImage(false !== $size && $size > self::MAX_BYTES);
        }

        $image = new Imagick();
        try {
            $image->readImage($temporaryPath.'[0]');
            if (!in_array($image->getImageFormat(), ['JPEG', 'PNG', 'WEBP'], true)) {
                throw new InvalidCoverImage();
            }

            $image->autoOrient();
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            $image->stripImage();
            return new ProcessedCover($this->variant($image, 320), $this->variant($image, 1200));
        } catch (ImagickException) {
            throw new InvalidCoverImage();
        } finally {
            $image->clear();
        }
    }

    private function variant(Imagick $image, int $maximum): string
    {
        $output = clone $image;
        try {
            if (max($output->getImageWidth(), $output->getImageHeight()) > $maximum) {
                $output->thumbnailImage($maximum, $maximum, true);
            }
            $output->setImagePage(0, 0, 0, 0);
            $output->setImageFormat('WEBP');
            $output->setImageCompressionQuality(85);
            $output->stripImage();
            $blob = $output->getImageBlob();
            if ('' === $blob) {
                throw new InvalidCoverImage();
            }
            return $blob;
        } finally {
            $output->clear();
        }
    }
}
