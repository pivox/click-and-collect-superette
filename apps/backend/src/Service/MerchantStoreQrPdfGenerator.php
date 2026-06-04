<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MerchantStoreQrPdfGenerator
{
    public function generate(string $storeName, string $targetUrl, string $png): string
    {
        $image = imagecreatefromstring($png);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('QR_PDF_IMAGE_READ_FAILED');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);
        $rgbStream = $this->pngToRgbStream($png);

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /QR 6 0 R >> >> /Contents 7 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = \sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream",
            $width,
            $height,
            \strlen($rgbStream),
            $rgbStream,
        );

        $content = $this->buildPageContent($storeName, $targetUrl);
        $objects[] = \sprintf("<< /Length %d >>\nstream\n%s\nendstream", \strlen($content), $content);

        return $this->buildPdf($objects);
    }

    private function buildPageContent(string $storeName, string $targetUrl): string
    {
        return implode("\n", [
            'q',
            'BT /F2 28 Tf 72 760 Td ('.$this->escapeText($storeName).') Tj ET',
            'BT /F1 16 Tf 72 724 Td (Scannez ce QR code pour preparer votre Kadhia.) Tj ET',
            '260 0 0 260 167 390 cm /QR Do',
            'BT /F1 11 Tf 72 330 Td ('.$this->escapeText($targetUrl).') Tj ET',
            'BT /F1 12 Tf 72 300 Td (Affichez ce document a l entree de la superette.) Tj ET',
            'Q',
        ]);
    }

    /**
     * @param list<string> $objects
     */
    private function buildPdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= \sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefOffset = \strlen($pdf);
        $pdf .= \sprintf("xref\n0 %d\n0000000000 65535 f \n", \count($objects) + 1);
        for ($i = 1, $count = \count($offsets); $i < $count; ++$i) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= \sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            \count($objects) + 1,
            $xrefOffset,
        );

        return $pdf;
    }

    private function pngToRgbStream(string $png): string
    {
        $image = imagecreatefromstring($png);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('QR_PDF_IMAGE_READ_FAILED');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $raw = '';
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $color = imagecolorat($image, $x, $y);
                $raw .= \chr(($color >> 16) & 0xFF).\chr(($color >> 8) & 0xFF).\chr($color & 0xFF);
            }
        }
        imagedestroy($image);

        return gzcompress($raw);
    }

    private function escapeText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (!\is_string($ascii)) {
            $ascii = $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
