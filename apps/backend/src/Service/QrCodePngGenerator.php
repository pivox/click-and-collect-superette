<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Minimal QR encoder for the beta store URL use case.
 *
 * Supports byte-mode Version 5-L payloads rendered as PNG through GD. The MVP QR
 * content is an ASCII/UTF-8 absolute URL well below this version capacity.
 */
final class QrCodePngGenerator
{
    private const int VERSION = 5;
    private const int SIZE = 37;
    private const int DATA_CODEWORDS = 108;
    private const int EC_CODEWORDS = 26;
    private const int QUIET_ZONE = 4;
    private const int SCALE = 8;

    /** @var list<list<bool|null>> */
    private array $modules = [];

    /** @var list<list<bool>> */
    private array $reserved = [];

    public function generate(string $payload): string
    {
        $bytes = array_values(unpack('C*', $payload) ?: []);
        if (\count($bytes) > 106) {
            throw new \InvalidArgumentException('QR_PAYLOAD_TOO_LONG');
        }

        $this->initializeMatrix();
        $this->drawFunctionPatterns();
        $codewords = $this->buildCodewords($bytes);
        $this->placeDataBits($this->codewordsToBits($codewords));
        $this->drawFormatBits($this->formatBits());

        return $this->renderPng();
    }

    private function initializeMatrix(): void
    {
        $this->modules = array_fill(0, self::SIZE, array_fill(0, self::SIZE, null));
        $this->reserved = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
    }

    private function drawFunctionPatterns(): void
    {
        $this->drawFinder(0, 0);
        $this->drawFinder(self::SIZE - 7, 0);
        $this->drawFinder(0, self::SIZE - 7);
        $this->drawAlignment(30, 30);

        for ($i = 8; $i < self::SIZE - 8; ++$i) {
            $this->setFunctionModule($i, 6, 0 === $i % 2);
            $this->setFunctionModule(6, $i, 0 === $i % 2);
        }

        $this->setFunctionModule(8, 4 * self::VERSION + 9, true);
        $this->reserveFormatInformationModules();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; ++$dy) {
            for ($dx = -1; $dx <= 7; ++$dx) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $xx >= self::SIZE || $yy < 0 || $yy >= self::SIZE) {
                    continue;
                }

                $isFinder = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                    && (0 === $dx || 6 === $dx || 0 === $dy || 6 === $dy || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $this->setFunctionModule($xx, $yy, $isFinder);
            }
        }
    }

    private function drawAlignment(int $centerX, int $centerY): void
    {
        for ($dy = -2; $dy <= 2; ++$dy) {
            for ($dx = -2; $dx <= 2; ++$dx) {
                $isDark = 2 === max(abs($dx), abs($dy)) || (0 === $dx && 0 === $dy);
                $this->setFunctionModule($centerX + $dx, $centerY + $dy, $isDark);
            }
        }
    }

    private function reserveFormatInformationModules(): void
    {
        foreach ($this->formatCoordinates() as [$x, $y]) {
            $this->reserved[$y][$x] = true;
        }
    }

    private function setFunctionModule(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    /**
     * @param list<int> $bytes
     *
     * @return list<int>
     */
    private function buildCodewords(array $bytes): array
    {
        $bits = [0, 1, 0, 0];
        $bits = array_merge($bits, $this->intToBits(\count($bytes), 8));
        foreach ($bytes as $byte) {
            $bits = array_merge($bits, $this->intToBits($byte, 8));
        }

        $remainingBits = self::DATA_CODEWORDS * 8 - \count($bits);
        $bits = array_merge($bits, array_fill(0, min(4, $remainingBits), 0));
        while (0 !== \count($bits) % 8) {
            $bits[] = 0;
        }

        $data = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $data[] = $this->bitsToInt($chunk);
        }

        $pad = 0;
        while (\count($data) < self::DATA_CODEWORDS) {
            $data[] = 0 === $pad % 2 ? 0xEC : 0x11;
            ++$pad;
        }

        return array_merge($data, $this->reedSolomon($data, self::EC_CODEWORDS));
    }

    /**
     * @param list<int> $data
     *
     * @return list<int>
     */
    private function reedSolomon(array $data, int $degree): array
    {
        $generator = [1];
        for ($i = 0; $i < $degree; ++$i) {
            $generator = $this->polyMultiply($generator, [1, $this->gfPow(2, $i)]);
        }

        $message = array_merge($data, array_fill(0, $degree, 0));
        for ($i = 0, $dataCount = \count($data); $i < $dataCount; ++$i) {
            $coefficient = $message[$i];
            if (0 === $coefficient) {
                continue;
            }

            foreach ($generator as $j => $generatorValue) {
                $message[$i + $j] ^= $this->gfMultiply($generatorValue, $coefficient);
            }
        }

        return \array_slice($message, -$degree);
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     *
     * @return list<int>
     */
    private function polyMultiply(array $left, array $right): array
    {
        $result = array_fill(0, \count($left) + \count($right) - 1, 0);
        foreach ($left as $i => $leftValue) {
            foreach ($right as $j => $rightValue) {
                $result[$i + $j] ^= $this->gfMultiply($leftValue, $rightValue);
            }
        }

        return $result;
    }

    private function gfMultiply(int $left, int $right): int
    {
        $result = 0;
        while ($right > 0) {
            if (0 !== ($right & 1)) {
                $result ^= $left;
            }
            $left <<= 1;
            if (0 !== ($left & 0x100)) {
                $left ^= 0x11D;
            }
            $right >>= 1;
        }

        return $result & 0xFF;
    }

    private function gfPow(int $base, int $exponent): int
    {
        $result = 1;
        for ($i = 0; $i < $exponent; ++$i) {
            $result = $this->gfMultiply($result, $base);
        }

        return $result;
    }

    /**
     * @param list<int> $codewords
     *
     * @return list<int>
     */
    private function codewordsToBits(array $codewords): array
    {
        $bits = [];
        foreach ($codewords as $codeword) {
            $bits = array_merge($bits, $this->intToBits($codeword, 8));
        }

        return $bits;
    }

    /**
     * @param list<int> $bits
     */
    private function placeDataBits(array $bits): void
    {
        $bitIndex = 0;
        $direction = -1;
        for ($x = self::SIZE - 1; $x >= 1; $x -= 2) {
            if (6 === $x) {
                --$x;
            }

            for ($row = 0; $row < self::SIZE; ++$row) {
                $y = -1 === $direction ? self::SIZE - 1 - $row : $row;
                for ($columnOffset = 0; $columnOffset < 2; ++$columnOffset) {
                    $xx = $x - $columnOffset;
                    if ($this->reserved[$y][$xx]) {
                        continue;
                    }

                    $dark = ($bits[$bitIndex] ?? 0) === 1;
                    if (0 === (($xx + $y) % 2)) {
                        $dark = !$dark;
                    }
                    $this->modules[$y][$xx] = $dark;
                    ++$bitIndex;
                }
            }

            $direction *= -1;
        }
    }

    private function formatBits(): int
    {
        $format = (1 << 3); // Error correction level L and mask 0.
        $bits = $format << 10;
        $generator = 0x537;
        for ($i = 14; $i >= 10; --$i) {
            if (0 !== (($bits >> $i) & 1)) {
                $bits ^= $generator << ($i - 10);
            }
        }

        return (($format << 10) | $bits) ^ 0x5412;
    }

    private function drawFormatBits(int $formatBits): void
    {
        $coordinates = \array_slice($this->formatCoordinates(), 0, 15);
        $mirrored = \array_slice($this->formatCoordinates(), 15);

        for ($i = 0; $i < 15; ++$i) {
            $dark = 1 === (($formatBits >> $i) & 1);
            $this->setFunctionModule($coordinates[$i][0], $coordinates[$i][1], $dark);
            $this->setFunctionModule($mirrored[$i][0], $mirrored[$i][1], $dark);
        }
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function formatCoordinates(): array
    {
        return [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
            [self::SIZE - 1, 8], [self::SIZE - 2, 8], [self::SIZE - 3, 8], [self::SIZE - 4, 8],
            [self::SIZE - 5, 8], [self::SIZE - 6, 8], [self::SIZE - 7, 8], [self::SIZE - 8, 8],
            [8, self::SIZE - 7], [8, self::SIZE - 6], [8, self::SIZE - 5], [8, self::SIZE - 4],
            [8, self::SIZE - 3], [8, self::SIZE - 2], [8, self::SIZE - 1],
        ];
    }

    /**
     * @return list<int>
     */
    private function intToBits(int $value, int $length): array
    {
        $bits = [];
        for ($i = $length - 1; $i >= 0; --$i) {
            $bits[] = ($value >> $i) & 1;
        }

        return $bits;
    }

    /**
     * @param list<int> $bits
     */
    private function bitsToInt(array $bits): int
    {
        $value = 0;
        foreach ($bits as $bit) {
            $value = ($value << 1) | $bit;
        }

        return $value;
    }

    private function renderPng(): string
    {
        $pixels = (self::SIZE + 2 * self::QUIET_ZONE) * self::SCALE;
        $image = imagecreatetruecolor($pixels, $pixels);
        if (!\is_resource($image) && !$image instanceof \GdImage) {
            throw new \RuntimeException('QR_IMAGE_CREATE_FAILED');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 17, 17);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < self::SIZE; ++$y) {
            for ($x = 0; $x < self::SIZE; ++$x) {
                if (true !== $this->modules[$y][$x]) {
                    continue;
                }
                imagefilledrectangle(
                    $image,
                    ($x + self::QUIET_ZONE) * self::SCALE,
                    ($y + self::QUIET_ZONE) * self::SCALE,
                    ($x + self::QUIET_ZONE + 1) * self::SCALE - 1,
                    ($y + self::QUIET_ZONE + 1) * self::SCALE - 1,
                    $black,
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        if (!\is_string($png)) {
            throw new \RuntimeException('QR_IMAGE_EXPORT_FAILED');
        }

        return $png;
    }
}
