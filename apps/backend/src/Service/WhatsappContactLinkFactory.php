<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Builds wa.me contact links for the semi-manual WhatsApp flows (S14-005).
 *
 * Phone normalization matches the Tunisian conventions already used by the
 * billing WhatsApp contact: digits only, "00" international prefix stripped,
 * 8-digit local numbers prefixed with the 216 country code.
 */
final readonly class WhatsappContactLinkFactory
{
    public function normalizePhone(?string $phone): ?string
    {
        if (null === $phone || '' === trim($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (null === $digits || '' === $digits) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (8 === \strlen($digits)) {
            return '216'.$digits;
        }

        return $digits;
    }

    public function buildUrl(string $normalizedPhone, string $message): string
    {
        return \sprintf('https://wa.me/%s?text=%s', $normalizedPhone, rawurlencode($message));
    }
}
