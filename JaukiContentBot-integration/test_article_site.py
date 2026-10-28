"""Tes offline untuk article_site (klien API artikel kauiz.web.id dan jaukitugas.my.id). Tidak ada panggilan jaringan."""

import json
import unittest

import article_site as site

ARTICLE = {"title": "Judul", "excerpt": "Ringkasan", "content": "<p>Isi</p>", "category": "Tips", "tags": ["a", "b"],
           "seo_title": "SEO", "seo_description": "Deskripsi"}
OK = {"success": True, "data": {"id": 7, "slug": "judul", "status": "draft", "url": "https://situs/artikel/judul", "featured_image": "https://situs/img.jpg"}}


class FakeSend:
    def __init__(self, *responses):
        self.responses = list(responses) or [(201, OK)]
        self.calls = []

    def __call__(self, method, url, headers, body, timeout=60.0):
        self.calls.append((method, url, dict(headers), body))
        response = self.responses.pop(0) if len(self.responses) > 1 else self.responses[0]
        if isinstance(response, Exception):
            raise response
        return response


def client(brand="kauiz", *responses, **kwargs):
    send = FakeSend(*responses)
    return site.ArticleSite(brand, "TOKEN-UJI", send=send, sleep=lambda _: None, **kwargs), send


class RequestTests(unittest.TestCase):
    def test_each_brand_uses_its_own_base_url_and_bearer_token(self):
        for brand, base in (("kauiz", "https://kauiz.web.id/v1/internal/articles"), ("jauki", "https://jaukitugas.my.id/api/internal/articles")):
            api = site.ArticleSite(brand, "T", base_url={"kauiz": "https://kauiz.web.id/v1/internal", "jauki": "https://jaukitugas.my.id/api/internal"}[brand],
                                   send=FakeSend(), sleep=lambda _: None)
            api.create_draft(ARTICLE)
            method, url, headers, body = api._send.calls[0]
            self.assertEqual((method, url), ("POST", base))
            self.assertEqual(headers["Authorization"], "Bearer T")

    def test_default_urls_include_the_internal_articles_path_through_the_spec(self):
        self.assertEqual(site.SITES["kauiz"].default_base_url, "https://kauiz.web.id/v1")
        self.assertEqual(site.SITES["jauki"].default_base_url, "https://jaukitugas.my.id/api/internal")

    def test_draft_without_image_is_json_with_status_draft_and_tags(self):
        api, send = client("jauki", base_url="https://j/api/internal")
        summary = api.create_draft({**ARTICLE, "scheduled_at": "2026-10-29 10:00:00", "status": "published"})
        body = json.loads(send.calls[0][3])
        self.assertEqual((body["status"], body["tags"], "scheduled_at" in body), ("draft", ["a", "b"], False))
        self.assertEqual(send.calls[0][2]["Content-Type"], "application/json")
        self.assertEqual((summary["id"], summary["url"], summary["image_url"]), (7, "https://situs/artikel/judul", "https://situs/img.jpg"))

    def test_draft_with_image_is_multipart_with_array_tags_and_the_cover_file(self):
        api, send = client("kauiz")
        api.create_draft(ARTICLE, b"\xff\xd8jpegdata")
        method, url, headers, body = send.calls[0]
        self.assertTrue(headers["Content-Type"].startswith("multipart/form-data; boundary="))
        self.assertIn(b'name="tags[]"\r\n\r\na', body)
        self.assertIn(b'name="featured_image"; filename="cover.jpg"', body)
        self.assertIn(b"jpegdata", body)

    def test_oversized_cover_is_refused_before_any_request(self):
        api, send = client("jauki")
        with self.assertRaises(site.SiteError):
            api.create_draft(ARTICLE, b"x" * (2048 * 1024 + 1))
        self.assertEqual(send.calls, [])

    def test_update_uses_put_json_or_post_with_method_spoofing_for_images(self):
        api, send = client("kauiz")
        api.update_draft(7, ARTICLE)
        self.assertEqual(send.calls[0][:2], ("PUT", "https://kauiz.web.id/v1/articles/7"))
        api.update_draft(7, ARTICLE, b"img")
        self.assertEqual(send.calls[1][0], "POST")
        self.assertIn(b'name="_method"\r\n\r\nPUT', send.calls[1][3])

    def test_publish_and_schedule_paths(self):
        api, send = client("jauki", (200, {"success": True, "message": "ok", "data": {"id": 7, "status": "published", "url": "https://u"}}))
        self.assertEqual(api.publish(7)["status"], "published")
        api.schedule(7, "2026-10-29 10:00:00")
        self.assertEqual([call[1].rsplit("/", 2)[-2:] for call in send.calls], [["7", "publish"], ["7", "schedule"]])
        self.assertEqual(json.loads(send.calls[1][3]), {"scheduled_at": "2026-10-29 10:00:00"})


class ErrorTests(unittest.TestCase):
    def test_missing_token_and_unknown_brand_fail_early(self):
        with self.assertRaises(site.SiteError):
            site.ArticleSite("kauiz", "")
        with self.assertRaises(site.SiteError):
            site.ArticleSite("lain", "T")

    def test_http_errors_become_clear_messages_without_leaking_the_token(self):
        cases = {401: "ditolak", 404: "tidak ditemukan"}
        for status, text in cases.items():
            api, _ = client("kauiz", (status, {"success": False, "message": "x"}))
            with self.assertRaises(site.SiteError) as caught:
                api.publish(1)
            self.assertIn(text, str(caught.exception))
            self.assertNotIn("TOKEN-UJI", str(caught.exception))
        api, _ = client("jauki", (422, {"success": False, "message": "Validation failed", "errors": {"title": ["The title field is required."]}}))
        with self.assertRaises(site.SiteError) as caught:
            api.create_draft(ARTICLE)
        self.assertIn("title: The title field is required.", str(caught.exception))

    def test_server_errors_are_retried_then_reported(self):
        api, send = client("kauiz", (503, {}), (503, {}), (201, OK))
        self.assertEqual(api.create_draft(ARTICLE)["id"], 7)
        self.assertEqual(len(send.calls), 3)
        api, send = client("kauiz", (500, {}))
        with self.assertRaises(site.SiteError):
            api.create_draft(ARTICLE)
        self.assertEqual(len(send.calls), 3)

    def test_connection_errors_are_retried(self):
        api, send = client("kauiz", site.SiteError("koneksi putus"), (201, OK))
        self.assertEqual(api.create_draft(ARTICLE)["id"], 7)

    def test_response_without_an_id_is_an_error(self):
        api, _ = client("kauiz", (201, {"success": True, "data": {"title": "x"}}))
        with self.assertRaises(site.SiteError):
            api.create_draft(ARTICLE)


if __name__ == "__main__":
    unittest.main()
