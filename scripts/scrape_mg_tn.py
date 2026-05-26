#!/usr/bin/env python3
"""
Scraper d'articles mg.tn
Usage:
    python scrape_mg_tn.py                        # scrape la page d'accueil
    python scrape_mg_tn.py --pages 3              # scrape 3 pages
    python scrape_mg_tn.py --output articles.json # fichier de sortie JSON
    python scrape_mg_tn.py --csv articles.csv     # export CSV
    python scrape_mg_tn.py --db                   # insertion PostgreSQL product_import_raw
    python scrape_mg_tn.py --category economie    # section spécifique
    python scrape_mg_tn.py --site --max-urls 500  # scrape les URLs internes du site
"""

import argparse
import csv
import json
import logging
import os
import sys
import time
import xml.etree.ElementTree as ET
from collections import deque
from dataclasses import asdict, dataclass
from typing import Optional
from urllib.parse import parse_qsl, urljoin, urlparse, urlunparse
from uuid import uuid4

import requests
from bs4 import BeautifulSoup

BASE_URL = "https://mg.tn"
DEFAULT_DB_SOURCE_NAME = "mg.tn"
DEFAULT_DATABASE_URL_ENV = "SCRAPER_DATABASE_URL"
DEFAULT_SITEMAP_URL = "https://mg.tn/sitemap.xml"

PRIVATE_QUERY_KEYS = {
    "order",
    "tag",
    "id_currency",
    "search_query",
    "back",
    "n",
}

PRIVATE_PATH_PARTS = {
    "/connexion",
    "/mon-compte",
    "/panier",
    "/commande",
    "/adresse",
    "/identite",
}

STATIC_EXTENSIONS = (
    ".css", ".js", ".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg",
    ".ico", ".pdf", ".zip", ".woff", ".woff2", ".ttf",
)

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/124.0.0.0 Safari/537.36"
    ),
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8,ar;q=0.7",
    "Accept-Encoding": "gzip, deflate, br",
    "Connection": "keep-alive",
    "Cache-Control": "no-cache",
}

# Sélecteurs CSS candidats pour les blocs article (du plus spécifique au plus général)
ARTICLE_SELECTORS = [
    "article",
    ".post",
    ".article",
    ".entry",
    ".card",
    ".news-item",
    ".item",
    "div[class*='article']",
    "div[class*='post']",
    "div[class*='news']",
    "div[class*='card']",
    "li[class*='post']",
    "li[class*='article']",
]

# Sélecteurs pour les titres
TITLE_SELECTORS = [
    "h1 a", "h2 a", "h3 a", "h4 a",
    ".entry-title a", ".post-title a", ".article-title a",
    ".title a",
    "h1", "h2", "h3", "h4",
    ".entry-title", ".post-title", ".article-title", ".title",
]

# Sélecteurs pour les dates
DATE_SELECTORS = [
    "time[datetime]",
    ".date", ".post-date", ".entry-date", ".published",
    "span[class*='date']", "p[class*='date']",
    "time",
]

# Sélecteurs pour les catégories
CATEGORY_SELECTORS = [
    ".category", ".cat", ".tag", ".section",
    "span[class*='cat']", "a[class*='cat']",
    ".post-category", ".entry-category",
]

# Sélecteurs pour les images
IMAGE_SELECTORS = [
    "img[src]",
    ".wp-post-image",
    ".featured-image img",
    ".thumbnail img",
    "figure img",
]

# Sélecteurs pour les extraits
EXCERPT_SELECTORS = [
    ".excerpt", ".entry-excerpt", ".post-excerpt",
    ".summary", ".description",
    "p.excerpt", "div.excerpt",
]

PRODUCT_SELECTORS = [
    "article.product-miniature",
    ".product-miniature",
    "article[data-id-product]",
]

PRODUCT_TITLE_SELECTORS = [
    ".product-title a[href]",
    "h2.product-title a[href]",
    "h3.product-title a[href]",
]

PRODUCT_PRICE_SELECTORS = [
    ".price",
    ".product-price",
    "[itemprop='price']",
]

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
log = logging.getLogger(__name__)


@dataclass
class Article:
    title: str
    url: str
    date: Optional[str] = None
    category: Optional[str] = None
    image_url: Optional[str] = None
    excerpt: Optional[str] = None


class GeoBlockedError(Exception):
    pass


PRODUCT_IMPORT_RAW_UPSERT_SQL = """
INSERT INTO product_import_raw (
    id,
    source_name,
    source_url,
    raw_title,
    raw_brand,
    raw_quantity,
    raw_category,
    raw_payload,
    production_usable,
    created_at,
    updated_at
) VALUES (
    %(id)s,
    %(source_name)s,
    %(source_url)s,
    %(raw_title)s,
    %(raw_brand)s,
    %(raw_quantity)s,
    %(raw_category)s,
    %(raw_payload)s::json,
    %(production_usable)s,
    NOW(),
    NOW()
)
ON CONFLICT (source_name, source_url) DO UPDATE SET
    raw_title = EXCLUDED.raw_title,
    raw_brand = EXCLUDED.raw_brand,
    raw_quantity = EXCLUDED.raw_quantity,
    raw_category = EXCLUDED.raw_category,
    raw_payload = EXCLUDED.raw_payload,
    production_usable = EXCLUDED.production_usable,
    updated_at = NOW()
"""


def fetch_page(url: str, session: requests.Session, retries: int = 3) -> Optional[BeautifulSoup]:
    for attempt in range(1, retries + 1):
        try:
            response = session.get(url, timeout=15)
            # mg.tn est géo-restreint : x-deny-reason: host_not_allowed pour les IPs hors Tunisie
            deny_reason = response.headers.get("x-deny-reason", "")
            if deny_reason == "host_not_allowed":
                raise GeoBlockedError(
                    f"Accès refusé ({response.status_code}) — raison : '{deny_reason or 'inconnue'}'.\n"
                    "mg.tn bloque les requêtes depuis des IPs hors Tunisie.\n"
                    "Solution : exécuter le script depuis une machine en Tunisie ou via un VPN tunisien."
                )
            response.raise_for_status()
            return BeautifulSoup(response.text, "html.parser")
        except GeoBlockedError:
            raise
        except requests.HTTPError as e:
            log.warning("HTTP %s pour %s (tentative %d/%d)", e.response.status_code, url, attempt, retries)
            if e.response.status_code in (404, 410):
                break
        except requests.RequestException as e:
            log.warning("Erreur réseau %s (tentative %d/%d) : %s", url, attempt, retries, e)
        if attempt < retries:
            time.sleep(2 ** attempt)
    return None


def _first_text(element, selectors: list[str]) -> Optional[str]:
    for sel in selectors:
        found = element.select_one(sel)
        if found and found.get_text(strip=True):
            return found.get_text(strip=True)
    return None


def _first_attr(element, selectors: list[str], attr: str) -> Optional[str]:
    for sel in selectors:
        found = element.select_one(sel)
        if found and found.get(attr):
            return found[attr]
    return None


def normalize_site_url(url: str, base_url: str = BASE_URL) -> Optional[str]:
    absolute = urljoin(base_url, url)
    parsed = urlparse(absolute)
    base = urlparse(base_url)

    if parsed.scheme not in ("http", "https"):
        return None
    if parsed.netloc != base.netloc:
        return None

    path = parsed.path or "/"
    lower_path = path.lower()
    if lower_path.endswith(STATIC_EXTENSIONS):
        return None
    if any(part in lower_path for part in PRIVATE_PATH_PARTS):
        return None

    query_items = parse_qsl(parsed.query, keep_blank_values=True)
    if any(key in PRIVATE_QUERY_KEYS or key.startswith("controller") for key, _ in query_items):
        return None

    query = "&".join(f"{key}={value}" if value else key for key, value in query_items)

    return urlunparse((parsed.scheme, parsed.netloc, path, "", query, ""))


def discover_internal_links(soup: BeautifulSoup, base_url: str) -> list[str]:
    urls: list[str] = []
    seen: set[str] = set()

    for link in soup.select("a[href]"):
        normalized = normalize_site_url(link["href"], base_url)
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        urls.append(normalized)

    return urls


def fetch_sitemap_urls(sitemap_url: str, session: requests.Session, base_url: str) -> list[str]:
    try:
        response = session.get(sitemap_url, timeout=20)
        response.raise_for_status()
    except requests.RequestException as e:
        log.warning("Impossible de charger le sitemap %s : %s", sitemap_url, e)
        return []

    try:
        root = ET.fromstring(response.text.strip())
    except ET.ParseError as e:
        log.warning("Sitemap XML invalide %s : %s", sitemap_url, e)
        return []

    urls: list[str] = []
    seen: set[str] = set()
    for loc in root.findall(".//{*}loc"):
        if loc.text is None:
            continue
        normalized = normalize_site_url(loc.text.strip(), base_url)
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        urls.append(normalized)

    return urls


def _detect_article_blocks(soup: BeautifulSoup) -> list:
    for sel in ARTICLE_SELECTORS:
        blocks = soup.select(sel)
        # garder les blocs qui contiennent au moins un lien avec du texte
        valid = [b for b in blocks if b.find("a") and b.find("a").get_text(strip=True)]
        if len(valid) >= 3:
            log.info("Sélecteur retenu : '%s' — %d blocs trouvés", sel, len(valid))
            return valid
    return []


def _extract_article_link(block) -> Optional[tuple[str, str]]:
    """Retourne (titre, url) depuis un bloc article."""
    if getattr(block, "name", None) == "a":
        title = block.get_text(strip=True)
        href = block.get("href")
        if title and href:
            return title, href

    for sel in TITLE_SELECTORS:
        el = block.select_one(sel)
        if not el:
            continue
        title = el.get_text(strip=True)
        href = el.get("href") if el.name == "a" else (el.find("a") or {}).get("href")
        if title and href:
            return title, href
    return None


def parse_articles(soup: BeautifulSoup, base_url: str) -> list[Article]:
    blocks = _detect_article_blocks(soup)
    if not blocks:
        log.warning("Aucun bloc article détecté — tentative fallback sur titres contenant un lien")
        # fallback : chercher les titres qui contiennent directement un lien d'article
        blocks = soup.select("h1 a[href], h2 a[href], h3 a[href], h4 a[href]")

    articles: list[Article] = []
    seen_urls: set[str] = set()

    for block in blocks:
        result = _extract_article_link(block)
        if not result:
            continue
        title, href = result

        url = urljoin(base_url, href)
        # filtrer les liens hors domaine ou déjà vus
        if urlparse(url).netloc not in (urlparse(base_url).netloc, ""):
            continue
        if url in seen_urls:
            continue
        seen_urls.add(url)

        date_el = block.select_one(", ".join(DATE_SELECTORS))
        date = None
        if date_el:
            date = date_el.get("datetime") or date_el.get_text(strip=True) or None

        category = _first_text(block, CATEGORY_SELECTORS)
        excerpt = _first_text(block, EXCERPT_SELECTORS)

        image_url = None
        for sel in IMAGE_SELECTORS:
            img = block.select_one(sel)
            if img:
                src = img.get("src") or img.get("data-src") or img.get("data-lazy-src")
                if src and not src.startswith("data:"):
                    image_url = urljoin(base_url, src)
                    break

        articles.append(Article(
            title=title,
            url=url,
            date=date,
            category=category,
            image_url=image_url,
            excerpt=excerpt,
        ))

    return articles


def _page_category(soup: BeautifulSoup) -> Optional[str]:
    breadcrumb_items = [
        item.get_text(" ", strip=True)
        for item in soup.select(".breadcrumb li, nav.breadcrumb li")
        if item.get_text(strip=True)
    ]
    if breadcrumb_items:
        return breadcrumb_items[-1]

    title = soup.select_one("h1")
    if title and title.get_text(strip=True):
        return title.get_text(strip=True)

    return None


def parse_products(soup: BeautifulSoup, base_url: str) -> list[Article]:
    products: list[Article] = []
    seen_urls: set[str] = set()
    page_category = _page_category(soup)

    blocks = []
    for selector in PRODUCT_SELECTORS:
        blocks = soup.select(selector)
        if blocks:
            break

    for block in blocks:
        link = None
        for selector in PRODUCT_TITLE_SELECTORS:
            link = block.select_one(selector)
            if link:
                break
        if not link:
            continue

        title = link.get_text(" ", strip=True)
        href = link.get("href")
        if not title or not href:
            continue

        normalized = normalize_site_url(href, base_url)
        if not normalized or normalized in seen_urls:
            continue
        seen_urls.add(normalized)

        price = _first_text(block, PRODUCT_PRICE_SELECTORS)
        image_src = None
        for selector in IMAGE_SELECTORS:
            image = block.select_one(selector)
            if image:
                image_src = image.get("src") or image.get("data-src") or image.get("data-lazy-src")
                break

        products.append(Article(
            title=title,
            url=normalized,
            category=page_category,
            image_url=urljoin(base_url, image_src) if image_src and not image_src.startswith("data:") else None,
            excerpt=price,
        ))

    return products


def parse_observations(soup: BeautifulSoup, base_url: str) -> list[Article]:
    products = parse_products(soup, base_url)
    if products:
        return products

    return parse_articles(soup, base_url)


def parse_site_observations(soup: BeautifulSoup, base_url: str) -> list[Article]:
    return parse_products(soup, base_url)


def build_page_url(base_url: str, category: Optional[str], page: int) -> str:
    if category:
        path = f"/{category.strip('/')}"
    else:
        path = "/"
    if page > 1:
        path = path.rstrip("/") + f"/page/{page}/"
    return urljoin(base_url, path)


def scrape(
    pages: int = 1,
    category: Optional[str] = None,
    delay: float = 1.5,
    site: bool = False,
    max_urls: int = 500,
    sitemap_url: str = DEFAULT_SITEMAP_URL,
) -> list[Article]:
    session = requests.Session()
    session.headers.update(HEADERS)

    all_articles: list[Article] = []
    seen_urls: set[str] = set()

    if site:
        seed_urls = fetch_sitemap_urls(sitemap_url, session, BASE_URL)
        if not seed_urls:
            seed_urls = [BASE_URL]

        queue = deque(seed_urls)
        queued = set(seed_urls)
        visited: set[str] = set()

        while queue and (max_urls <= 0 or len(visited) < max_urls):
            url = queue.popleft()
            if url in visited:
                continue
            visited.add(url)
            log.info("Scraping URL %d/%s : %s", len(visited), max_urls if max_urls > 0 else "∞", url)

            try:
                soup = fetch_page(url, session)
            except GeoBlockedError as e:
                log.error("%s", e)
                break
            if soup is None:
                continue

            page_articles = parse_site_observations(soup, url)
            new = [a for a in page_articles if a.url not in seen_urls]
            seen_urls.update(a.url for a in new)
            all_articles.extend(new)
            log.info("  → %d observation(s) trouvée(s) (total : %d)", len(new), len(all_articles))

            for discovered_url in discover_internal_links(soup, url):
                if discovered_url in visited or discovered_url in queued:
                    continue
                queue.append(discovered_url)
                queued.add(discovered_url)

            if delay > 0 and queue and (max_urls <= 0 or len(visited) < max_urls):
                time.sleep(delay)

        return all_articles

    for page_num in range(1, pages + 1):
        url = build_page_url(BASE_URL, category, page_num)
        log.info("Scraping page %d : %s", page_num, url)

        try:
            soup = fetch_page(url, session)
        except GeoBlockedError as e:
            log.error("%s", e)
            break
        if soup is None:
            log.error("Impossible de charger la page %d — arrêt", page_num)
            break

        page_articles = parse_observations(soup, url)
        new = [a for a in page_articles if a.url not in seen_urls]
        seen_urls.update(a.url for a in new)
        all_articles.extend(new)

        log.info("  → %d article(s) trouvé(s) (total : %d)", len(new), len(all_articles))

        if page_num < pages:
            time.sleep(delay)

    return all_articles


def write_json(articles: list[Article], path: str) -> None:
    data = [asdict(a) for a in articles]
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    log.info("JSON écrit : %s (%d articles)", path, len(articles))


def write_csv(articles: list[Article], path: str) -> None:
    if not articles:
        return
    fieldnames = list(asdict(articles[0]).keys())
    with open(path, "w", newline="", encoding="utf-8-sig") as f:  # utf-8-sig pour Excel
        writer = csv.DictWriter(f, fieldnames=fieldnames, delimiter=";")
        writer.writeheader()
        writer.writerows(asdict(a) for a in articles)
    log.info("CSV écrit : %s (%d articles)", path, len(articles))


def build_product_import_raw_rows(
    articles: list[Article],
    source_name: str = DEFAULT_DB_SOURCE_NAME,
) -> list[dict[str, object]]:
    rows: list[dict[str, object]] = []

    for article in articles:
        payload = {
            "title": article.title,
            "url": article.url,
        }
        if article.date:
            payload["date"] = article.date
        if article.category:
            payload["category"] = article.category

        rows.append({
            "source_name": source_name,
            "source_url": article.url,
            "raw_title": article.title,
            "raw_brand": None,
            "raw_quantity": None,
            "raw_category": article.category,
            "raw_payload": payload,
            "production_usable": False,
        })

    return rows


def insert_product_import_raw(connection, articles: list[Article]) -> int:
    rows = build_product_import_raw_rows(articles)

    with connection.cursor() as cursor:
        for row in rows:
            params = {
                "id": str(uuid4()),
                **row,
                "raw_payload": json.dumps(row["raw_payload"], ensure_ascii=False),
            }
            cursor.execute(PRODUCT_IMPORT_RAW_UPSERT_SQL, params)

    connection.commit()

    return len(rows)


def write_database(articles: list[Article], database_url: str) -> int:
    try:
        import psycopg
    except ImportError as exc:
        raise RuntimeError(
            "Dépendance PostgreSQL absente. Rebuilder l'image scraper ou installer psycopg[binary]."
        ) from exc

    with psycopg.connect(database_url) as connection:
        count = insert_product_import_raw(connection, articles)

    log.info("BDD écrite : product_import_raw (%d observation(s) mg.tn)", count)

    return count


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Scraper d'articles mg.tn",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("--pages", type=int, default=1, help="Nombre de pages à scraper (défaut : 1)")
    parser.add_argument("--category", default=None, help="Section du site (ex: economie, sport)")
    parser.add_argument(
        "--site",
        action="store_true",
        help="Découvrir et scraper les URLs internes du site mg.tn (sitemap puis liens internes)",
    )
    parser.add_argument(
        "--max-urls",
        type=int,
        default=500,
        help="Nombre maximum d'URLs internes à visiter avec --site (0 = sans limite, défaut : 500)",
    )
    parser.add_argument(
        "--sitemap-url",
        default=DEFAULT_SITEMAP_URL,
        help=f"URL du sitemap à utiliser avec --site (défaut : {DEFAULT_SITEMAP_URL})",
    )
    parser.add_argument("--output", default=None, help="Fichier JSON de sortie")
    parser.add_argument("--csv", dest="csv_file", default=None, help="Fichier CSV de sortie")
    parser.add_argument("--delay", type=float, default=1.5, help="Délai entre les pages en secondes (défaut : 1.5)")
    parser.add_argument("--quiet", action="store_true", help="Supprimer les logs INFO")
    parser.add_argument(
        "--db",
        action="store_true",
        help="Insérer les observations dans PostgreSQL (table product_import_raw)",
    )
    parser.add_argument(
        "--database-url",
        default=None,
        help=f"URL PostgreSQL (défaut : variable {DEFAULT_DATABASE_URL_ENV}, puis DATABASE_URL)",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help="Vérifier la connectivité à mg.tn sans scraper",
    )
    args = parser.parse_args()

    if args.quiet:
        logging.getLogger().setLevel(logging.WARNING)

    if args.check:
        session = requests.Session()
        session.headers.update(HEADERS)
        try:
            r = session.get(BASE_URL, timeout=10)
            deny = r.headers.get("x-deny-reason", "")
            if r.status_code == 200:
                print(f"OK — mg.tn répond ({r.status_code})")
            elif deny:
                print(f"BLOQUÉ — HTTP {r.status_code}, x-deny-reason: {deny}")
                print("Exécuter depuis une IP tunisienne ou un VPN tunisien.")
            else:
                print(f"HTTP {r.status_code} — vérifier manuellement")
        except requests.RequestException as e:
            print(f"ERREUR réseau : {e}")
        return 0

    articles = scrape(
        pages=args.pages,
        category=args.category,
        delay=args.delay,
        site=args.site,
        max_urls=args.max_urls,
        sitemap_url=args.sitemap_url,
    )

    if not articles:
        log.warning("Aucun article récupéré.")
        return 1

    print(f"\n{'─' * 60}")
    print(f"  {len(articles)} article(s) récupéré(s) depuis mg.tn")
    print(f"{'─' * 60}")
    for i, art in enumerate(articles, 1):
        print(f"\n[{i}] {art.title}")
        print(f"     URL      : {art.url}")
        if art.date:
            print(f"     Date     : {art.date}")
        if art.category:
            print(f"     Catégorie: {art.category}")
        if art.excerpt:
            print(f"     Extrait  : {art.excerpt[:120]}{'…' if len(art.excerpt) > 120 else ''}")

    if args.output:
        write_json(articles, args.output)
    if args.csv_file:
        write_csv(articles, args.csv_file)
    if args.db:
        database_url = args.database_url or os.getenv(DEFAULT_DATABASE_URL_ENV) or os.getenv("DATABASE_URL")
        if not database_url:
            log.error(
                "Aucune URL PostgreSQL fournie. Utiliser --database-url ou définir %s.",
                DEFAULT_DATABASE_URL_ENV,
            )
            return 1
        write_database(articles, database_url)

    return 0


if __name__ == "__main__":
    sys.exit(main())
