<?php

declare(strict_types=1);

namespace App\Tests\Library\Infrastructure\Cover;

use App\Library\Application\Cover\InvalidCoverImage;
use App\Library\Infrastructure\Cover\ImagickCoverImageProcessor;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImagickCoverImageProcessorTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) unlink($path); }
    }

    private function path(string $blob): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-test-');
        $this->paths[] = $path;
        file_put_contents($path, $blob);
        return $path;
    }

    public static function formats(): array { return [['JPEG'], ['PNG'], ['WEBP']]; }

    #[DataProvider('formats')]
    public function testCoverVariantsPreserveRatioAndRemoveMetadata(string $format): void
    {
        $input = $this->path(file_get_contents(__DIR__.'/../../../Fixtures/covers/portrait.'.strtolower($format)));
        $result = (new ImagickCoverImageProcessor())->process($input);
        foreach ([[$result->thumbnail, 160, 320], [$result->large, 600, 1200]] as [$blob, $width, $height]) {
            $variant = new Imagick();
            $variant->readImageBlob($blob);
            self::assertSame('WEBP', $variant->getImageFormat());
            self::assertSame($width, $variant->getImageWidth());
            self::assertSame($height, $variant->getImageHeight());
            self::assertSame([], $variant->getImageProfiles('*', false));
            self::assertSame([], $variant->getImageProperties('exif:*'));
            self::assertSame([], $variant->getImageProperties('gps:*'));
            self::assertSame([], $variant->getImageProperties('comment'));
            $variant->clear();
        }
    }

    public static function invalidImages(): array
    {
        return [
            ['not an image'], ['<svg xmlns="http://www.w3.org/2000/svg"/>'],
            ["GIF89a\x01\0\x01\0"], ["\x89PNG\r\n\x1a\ncorrupt"],
            [str_repeat('x', 10000001)],
        ];
    }

    #[DataProvider('invalidImages')]
    public function testInvalidCoverFilesAreRejected(string $blob): void
    {
        $this->expectException(InvalidCoverImage::class);
        (new ImagickCoverImageProcessor())->process($this->path($blob));
    }
}
