"use client";

import { useEffect, useState } from "react";
import type { ProductImage } from "@/types";
import { mediaUrl } from "@/lib/media";
import { cn } from "@/lib/cn";

export interface ProductThumbnailProps {
  image?: ProductImage | null;
  /** Product name — used for the alt text and the placeholder initial. */
  nameFr: string;
  /** Emoji fallback shown when no image is available. */
  emoji?: string;
  /** Responsive `sizes` hint for the browser (e.g. "(max-width: 768px) 50vw, 200px"). */
  sizes?: string;
  /** Box classes (dimensions, rounding, text size for the placeholder). */
  className?: string;
}

/**
 * Renders the official product image (responsive WebP with a JPEG fallback) or,
 * when none is available / it fails to load, the category-style placeholder.
 *
 * A missing or broken image never shows the browser's broken-image icon and
 * never breaks the card layout: the box keeps its dimensions and falls back to
 * the emoji/initial placeholder.
 */
export function ProductThumbnail({
  image,
  nameFr,
  emoji,
  sizes,
  className,
}: ProductThumbnailProps) {
  const [failed, setFailed] = useState(false);

  // A new image (e.g. after an admin upload) should clear a previous error.
  useEffect(() => {
    setFailed(false);
  }, [image?.cardUrl, image?.fallbackJpegUrl]);

  const primary = mediaUrl(
    image?.fallbackJpegUrl ??
      image?.cardUrl ??
      image?.detailUrl ??
      image?.thumbnailUrl ??
      null,
  );

  const webpSrcSet = [
    image?.thumbnailUrl ? `${mediaUrl(image.thumbnailUrl)} 200w` : null,
    image?.cardUrl ? `${mediaUrl(image.cardUrl)} 400w` : null,
    image?.detailUrl ? `${mediaUrl(image.detailUrl)} 800w` : null,
    image?.zoomUrl ? `${mediaUrl(image.zoomUrl)} 1200w` : null,
  ]
    .filter(Boolean)
    .join(", ");

  const showImage = !failed && primary != null;

  return (
    <div className={cn("relative overflow-hidden bg-product-tile", className)}>
      {showImage ? (
        <picture>
          {webpSrcSet && (
            <source type="image/webp" srcSet={webpSrcSet} sizes={sizes} />
          )}
          <img
            src={primary}
            alt={image?.alt ?? nameFr}
            loading="lazy"
            decoding="async"
            onError={() => setFailed(true)}
            className="h-full w-full object-contain"
          />
        </picture>
      ) : (
        <span
          role="img"
          aria-label={nameFr}
          className="grid h-full w-full place-items-center"
        >
          <span aria-hidden="true">{emoji ?? nameFr.charAt(0)}</span>
        </span>
      )}
    </div>
  );
}
