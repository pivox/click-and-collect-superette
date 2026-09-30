<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class FrontendOriginValidator
{
    public function normalize(string $frontendOrigin): string
    {
        $frontendOrigin = trim($frontendOrigin);
        if ('' === $frontendOrigin) {
            throw new BadRequestHttpException('FRONTEND_ORIGIN_REQUIRED');
        }

        $parts = parse_url($frontendOrigin);
        if (!\is_array($parts)) {
            throw new BadRequestHttpException('FRONTEND_ORIGIN_INVALID');
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), ['http', 'https'], true) || !\is_string($host) || '' === $host) {
            throw new BadRequestHttpException('FRONTEND_ORIGIN_INVALID');
        }

        foreach (['user', 'pass', 'path', 'query', 'fragment'] as $forbiddenPart) {
            if (isset($parts[$forbiddenPart]) && !('path' === $forbiddenPart && '/' === $parts[$forbiddenPart])) {
                throw new BadRequestHttpException('FRONTEND_ORIGIN_MUST_BE_ORIGIN_ONLY');
            }
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($scheme).'://'.strtolower($host).$port;
    }
}
