// PRODUCT-IMAGE-002 (#582): neutral category placeholders.
//
// When a product has neither an exact image nor a shared generic image, the
// catalog falls back to a neutral, license-free pictogram derived from its
// category — never a brand, never a fake packaging. Assets are Unicode emoji
// (source_type: generated_placeholder) registered in
// docs/product/image-library-registry.md.

const DEFAULT_PLACEHOLDER = "🛒";

/**
 * Keyword → pictogram, checked in order against the category slug and the
 * French category name (lowercased, accents stripped).
 */
const CATEGORY_PLACEHOLDERS: Array<[RegExp, string]> = [
  [/fruit|legume|primeur/, "🥬"],
  [/pain|boulang|viennois/, "🥖"],
  [/lait|cremerie|fromage|yaourt/, "🥛"],
  [/boisson|jus|eau|soda/, "🥤"],
  [/vrac|cereale|legumineuse/, "🌾"],
  [/surgele|congele/, "❄️"],
  [/hygiene|beaute|cosmetique/, "🧼"],
  [/entretien|menage|nettoyage/, "🧽"],
  [/bebe|puericulture/, "🍼"],
  [/animal|animaux/, "🐾"],
  [/depannage|bazar|divers/, "🔦"],
  [/sucre|confiserie|biscuit|patisserie|dessert/, "🍬"],
  [/epicerie|sale|conserve|condiment/, "🥫"],
];

function normalize(value: string): string {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "");
}

/**
 * Returns the neutral pictogram for a category (slug and/or display name),
 * or the generic basket placeholder when no category matches.
 */
export function categoryPlaceholder(
  categorySlug?: string | null,
  categoryNameFr?: string | null,
): string {
  const haystack = normalize(`${categorySlug ?? ""} ${categoryNameFr ?? ""}`);
  for (const [pattern, emoji] of CATEGORY_PLACEHOLDERS) {
    if (pattern.test(haystack)) {
      return emoji;
    }
  }
  return DEFAULT_PLACEHOLDER;
}
