#!/usr/bin/env python3
"""
Scraper d'articles mg.tn
Usage:
    python scrape_mg_tn.py                        # scrape la page d'accueil
    python scrape_mg_tn.py --pages 3              # scrape 3 pages
    python scrape_mg_tn.py --output articles.json # fichier de sortie JSON
    python scrape_mg_tn.py --csv articles.csv     # export CSV
    python scrape_mg_tn.py --category economie    # section spécifique
"""

import argparse
import csv
import json
import logging
import sys
import time
from dataclasses import asdict, dataclass, field
from typing import Optional
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

BASE_URL = "https://mg.tn"

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
) -> list[Article]:
    session = requests.Session()
    session.headers.update(HEADERS)

    all_articles: list[Article] = []
    seen_urls: set[str] = set()

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

        page_articles = parse_articles(soup, BASE_URL)
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


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Scraper d'articles mg.tn",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("--pages", type=int, default=1, help="Nombre de pages à scraper (défaut : 1)")
    parser.add_argument("--category", default=None, help="Section du site (ex: economie, sport)")
    parser.add_argument("--output", default=None, help="Fichier JSON de sortie")
    parser.add_argument("--csv", dest="csv_file", default=None, help="Fichier CSV de sortie")
    parser.add_argument("--delay", type=float, default=1.5, help="Délai entre les pages en secondes (défaut : 1.5)")
    parser.add_argument("--quiet", action="store_true", help="Supprimer les logs INFO")
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

    articles = scrape(pages=args.pages, category=args.category, delay=args.delay)

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

    return 0


if __name__ == "__main__":
    sys.exit(main())
