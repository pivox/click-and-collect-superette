import unittest
from unittest.mock import patch

import requests
from bs4 import BeautifulSoup

from scripts.scrape_mg_tn import (
    Article,
    GeoBlockedError,
    build_product_import_raw_rows,
    insert_product_import_raw,
    fetch_page,
    parse_articles,
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
