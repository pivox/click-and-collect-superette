import unittest
from unittest.mock import patch

import requests
from bs4 import BeautifulSoup

from scripts.scrape_mg_tn import (
    Article,
    GeoBlockedError,
    build_product_import_raw_rows,
    discover_internal_links,
    fetch_sitemap_urls,
    insert_product_import_raw,
    fetch_page,
    parse_articles,
    parse_observations,
    parse_products,
    parse_site_observations,
)


class FakeResponse:
    def __init__(self, status_code, headers=None, text=""):
        self.status_code = status_code
        self.headers = headers or {}
        self.text = text

    def raise_for_status(self):
        if self.status_code >= 400:
            error = requests.HTTPError(f"{self.status_code} error")
            error.response = self
            raise error


class FakeSession:
    def __init__(self, *responses):
        self.responses = list(responses)
        self.calls = 0

    def get(self, url, timeout):
        self.calls += 1
        return self.responses.pop(0)


class FakeCursor:
    def __init__(self):
        self.executions = []

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, traceback):
        return False

    def execute(self, sql, params):
        self.executions.append((sql, params))


class FakeConnection:
    def __init__(self):
        self.cursor_obj = FakeCursor()
        self.commits = 0

    def cursor(self):
        return self.cursor_obj

    def commit(self):
        self.commits += 1


class ScrapeMgTnTest(unittest.TestCase):
    def test_parse_articles_fallback_detects_heading_links(self):
        soup = BeautifulSoup(
            """
            <html>
              <body>
                <main>
                  <h2><a href="/article-1">Premier article</a></h2>
                  <h3><a href="/article-2">Deuxieme article</a></h3>
                </main>
              </body>
            </html>
            """,
            "html.parser",
        )

        articles = parse_articles(soup, "https://mg.tn")

        self.assertEqual(
            [article.title for article in articles],
            ["Premier article", "Deuxieme article"],
        )
        self.assertEqual(
            [article.url for article in articles],
            [
                "https://mg.tn/article-1",
                "https://mg.tn/article-2",
            ],
        )

    def test_parse_products_detects_prestashop_product_cards(self):
        soup = BeautifulSoup(
            """
            <html>
              <body>
                <nav class="breadcrumb">
                  <ol>
                    <li><a>Accueil</a></li>
                    <li><a>Alimentaire</a></li>
                  </ol>
                </nav>
                <article class="product-miniature">
                  <h2 class="product-title">
                    <a href="/alimentaire/271-ail-en-poudre-70-gr-mg-j-aime.html">
                      Ail en poudre 70 gr MG J'AIME
                    </a>
                  </h2>
                  <span class="price">5,250 DT</span>
                  <img src="/img/ail.jpg" />
                </article>
                <article class="product-miniature">
                  <h2 class="product-title">
                    <a href="/alimentaire/273-romarin-sachet-de-20-gr-koll-youm.html">
                      Romarin Sachet de 20 gr KOLL YOUM
                    </a>
                  </h2>
                </article>
              </body>
            </html>
            """,
            "html.parser",
        )

        products = parse_products(soup, "https://mg.tn/15-alimentaire")

        self.assertEqual(
            [product.title for product in products],
            [
                "Ail en poudre 70 gr MG J'AIME",
                "Romarin Sachet de 20 gr KOLL YOUM",
            ],
        )
        self.assertEqual(
            [product.url for product in products],
            [
                "https://mg.tn/alimentaire/271-ail-en-poudre-70-gr-mg-j-aime.html",
                "https://mg.tn/alimentaire/273-romarin-sachet-de-20-gr-koll-youm.html",
            ],
        )
        self.assertEqual(products[0].category, "Alimentaire")
        self.assertEqual(products[0].excerpt, "5,250 DT")
        self.assertEqual(products[0].image_url, "https://mg.tn/img/ail.jpg")

    def test_parse_observations_prefers_products_over_articles(self):
        soup = BeautifulSoup(
            """
            <html>
              <body>
                <article class="product-miniature">
                  <h2 class="product-title">
                    <a href="/alimentaire/271-ail-en-poudre-70-gr-mg-j-aime.html">Ail en poudre 70 gr MG J'AIME</a>
                  </h2>
                </article>
                <h2><a href="/blog/post">Article blog</a></h2>
              </body>
            </html>
            """,
            "html.parser",
        )

        observations = parse_observations(soup, "https://mg.tn/15-alimentaire")

        self.assertEqual(len(observations), 1)
        self.assertEqual(observations[0].title, "Ail en poudre 70 gr MG J'AIME")

    def test_parse_site_observations_ignores_editorial_links_without_products(self):
        soup = BeautifulSoup(
            """
            <html>
              <body>
                <h2><a href="/bien-manger">Bien manger</a></h2>
                <h2><a href="/consommer-responsable">Consommer responsable</a></h2>
              </body>
            </html>
            """,
            "html.parser",
        )

        observations = parse_site_observations(soup, "https://mg.tn/")

        self.assertEqual(observations, [])

    def test_fetch_sitemap_urls_reads_same_domain_locations(self):
        session = FakeSession(FakeResponse(
            200,
            text="""
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://mg.tn/15-alimentaire</loc></url>
              <url><loc>https://external.test/page</loc></url>
            </urlset>
            """,
        ))

        urls = fetch_sitemap_urls("https://mg.tn/sitemap.xml", session, "https://mg.tn")

        self.assertEqual(urls, ["https://mg.tn/15-alimentaire"])

    def test_discover_internal_links_filters_private_and_external_urls(self):
        soup = BeautifulSoup(
            """
            <html>
              <body>
                <a href="/15-alimentaire">Alimentaire</a>
                <a href="https://mg.tn/20-hygiene#content">Hygiène</a>
                <a href="https://mg.tn/connexion?back=my-account">Connexion</a>
                <a href="https://external.test/page">Externe</a>
              </body>
            </html>
            """,
            "html.parser",
        )

        urls = discover_internal_links(soup, "https://mg.tn/")

        self.assertEqual(
            urls,
            [
                "https://mg.tn/15-alimentaire",
                "https://mg.tn/20-hygiene",
            ],
        )

    def test_fetch_page_retries_plain_403_without_geoblock_header(self):
        session = FakeSession(
            FakeResponse(403),
            FakeResponse(200, text="<html><body>OK</body></html>"),
        )

        with patch("scripts.scrape_mg_tn.time.sleep"):
            result = fetch_page("https://mg.tn", session, retries=2)

        self.assertIsNotNone(result)
        self.assertEqual(session.calls, 2)

    def test_fetch_page_raises_geoblock_only_for_explicit_deny_reason(self):
        response = FakeResponse(403, headers={"x-deny-reason": "host_not_allowed"})
        session = FakeSession(response)

        with self.assertRaises(GeoBlockedError):
            fetch_page("https://mg.tn", session, retries=1)

    def test_build_product_import_raw_rows_keeps_minimal_traceable_fields(self):
        article = Article(
            title="Huile d'olive extra vierge 750 ml",
            url="https://mg.tn/huile-olive",
            date="2026-05-22",
            category="Epicerie",
            image_url="https://mg.tn/image.jpg",
            excerpt="Description marketing a ne pas stocker",
        )

        rows = build_product_import_raw_rows([article])

        self.assertEqual(len(rows), 1)
        row = rows[0]
        self.assertEqual(row["source_name"], "mg.tn")
        self.assertEqual(row["source_url"], "https://mg.tn/huile-olive")
        self.assertEqual(row["raw_title"], "Huile d'olive extra vierge 750 ml")
        self.assertEqual(row["raw_category"], "Epicerie")
        self.assertIsNone(row["raw_brand"])
        self.assertIsNone(row["raw_quantity"])
        self.assertFalse(row["production_usable"])
        self.assertEqual(
            row["raw_payload"],
            {
                "title": "Huile d'olive extra vierge 750 ml",
                "url": "https://mg.tn/huile-olive",
                "date": "2026-05-22",
                "category": "Epicerie",
            },
        )
        self.assertNotIn("image_url", row["raw_payload"])
        self.assertNotIn("excerpt", row["raw_payload"])

    def test_insert_product_import_raw_upserts_rows_and_commits(self):
        connection = FakeConnection()
        articles = [
            Article(title="Produit 1", url="https://mg.tn/p1", category="Promo"),
            Article(title="Produit 2", url="https://mg.tn/p2"),
        ]

        inserted_count = insert_product_import_raw(connection, articles)

        self.assertEqual(inserted_count, 2)
        self.assertEqual(connection.commits, 1)
        self.assertEqual(len(connection.cursor_obj.executions), 2)
        sql, params = connection.cursor_obj.executions[0]
        self.assertIn("ON CONFLICT (source_name, source_url) DO UPDATE", sql)
        self.assertEqual(params["source_name"], "mg.tn")
        self.assertEqual(params["source_url"], "https://mg.tn/p1")
        self.assertEqual(params["raw_title"], "Produit 1")
        self.assertEqual(params["raw_category"], "Promo")
        self.assertFalse(params["production_usable"])


if __name__ == "__main__":
    unittest.main()
