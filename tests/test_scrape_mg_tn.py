import unittest
from unittest.mock import patch

import requests
from bs4 import BeautifulSoup

from scripts.scrape_mg_tn import GeoBlockedError, fetch_page, parse_articles


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


if __name__ == "__main__":
    unittest.main()
