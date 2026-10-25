"""Publish Reels ke Instagram lewat Graph API: buat container, tunggu diproses, lalu publish.

Mengikuti pola worker Kauiz: versi Graph API yang sama, klasifikasi error, dan retry rate limit.
Instagram yang mengambil file video, jadi video harus bisa diakses publik lewat URL.
Posting Instagram tidak bisa ditarik kembali. Panggilan ini hanya dipakai dengan flag --live.
"""

import json
import logging
import time
import urllib.error
import urllib.parse
import urllib.request

LOG = logging.getLogger("content-bot.instagram")

GRAPH_HOST = "https://graph.facebook.com"
GRAPH_VERSION = "v23.0"
CAPTION_LIMIT = 2200
RATE_LIMIT_CODES = {4, 17, 32, 613, 80001}
AUTH_CODES = {102, 190}
TRANSIENT_CODES = {1, 2, 9007}


class GraphError(Exception):
    retryable = False
    wait_hint = 0

    def __init__(self, message, *, status=None, code=None, subcode=None, fbtrace_id=None, retry_after=None):
        super().__init__(message)
        self.status = status
        self.code = code
        self.subcode = subcode
        self.fbtrace_id = fbtrace_id
        self.retry_after = retry_after

    def __str__(self):
        parts = [super().__str__()]
        if self.status:
            parts.append(f"HTTP {self.status}")
        if self.code is not None:
            parts.append(f"code={self.code}")
        if self.subcode:
            parts.append(f"subcode={self.subcode}")
        if self.fbtrace_id:
            parts.append(f"fbtrace_id={self.fbtrace_id}")
        if self.retry_after is not None:
            parts.append(f"retry_after={self.retry_after}s")
        return " | ".join(parts)


class NetworkError(GraphError):
    retryable = True
    wait_hint = 10


class TransientError(GraphError):
    retryable = True
    wait_hint = 15


class RateLimitError(GraphError):
    retryable = True
    wait_hint = 60


class AuthError(GraphError):
    retryable = False


class PublishError(RuntimeError):
    """Video gagal diproses Instagram, atau belum selesai sampai batas tunggu."""


def parse_retry_after(headers):
    value = {key.lower(): val for key, val in headers.items()}.get("retry-after")
    try:
        return max(0, int(value))
    except (TypeError, ValueError):
        return None


def classify_error(status, headers, body):
    error = body.get("error") if isinstance(body, dict) else None
    error = error if isinstance(error, dict) else {}
    code = error.get("code")
    details = {
        "status": status,
        "code": code,
        "subcode": error.get("error_subcode"),
        "fbtrace_id": error.get("fbtrace_id"),
        "retry_after": parse_retry_after(headers),
    }
    message = error.get("message") or f"HTTP {status}"
    if status == 429 or code in RATE_LIMIT_CODES:
        return RateLimitError(message, **details)
    if code in AUTH_CODES:
        return AuthError(message, **details)
    if status >= 500 or code in TRANSIENT_CODES:
        return TransientError(message, **details)
    return GraphError(message, **details)


def http_request(method, url, form, timeout=30.0):
    query = urllib.parse.urlencode(form)
    if method == "GET":
        request = urllib.request.Request(f"{url}?{query}", method="GET")
    else:
        request = urllib.request.Request(url, data=query.encode("utf-8"), method="POST")
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, dict(response.headers.items()), json.loads(response.read() or b"{}")
    except urllib.error.HTTPError as err:
        raw = err.read()
        try:
            body = json.loads(raw or b"{}")
        except ValueError:
            body = {"error": {"message": raw[:300].decode("utf-8", "replace")}}
        return err.code, dict(err.headers.items()), body


class ReelsClient:
    """Klien Graph API untuk Reels, dengan retry koneksi putus, rate limit, dan error sementara."""

    def __init__(self, access_token, ig_user_id, *, version=GRAPH_VERSION, transport=None,
                 sleep=time.sleep, max_retries=3, max_wait=300):
        self.access_token = access_token
        self.ig_user_id = ig_user_id
        self.version = version
        self.transport = transport or http_request
        self.sleep = sleep
        self.max_retries = max_retries
        # Retry-After atau backoff yang lebih lama dari ini tidak ditunggu. Slot berikutnya yang mencoba lagi.
        self.max_wait = max_wait

    def create_container(self, video_url, caption):
        fields = {
            "media_type": "REELS",
            "video_url": video_url,
            "caption": caption[:CAPTION_LIMIT],
        }
        return self._id(self._request("POST", f"{self.ig_user_id}/media", fields))

    def container_status(self, container_id):
        body = self._request("GET", container_id, {"fields": "status_code"})
        return str(body.get("status_code", ""))

    def wait_until_ready(self, container_id, *, attempts=30, interval=10):
        for _ in range(attempts):
            status = self.container_status(container_id)
            if status == "FINISHED":
                return
            if status in ("ERROR", "EXPIRED"):
                raise PublishError(f"Instagram gagal memproses video: {status}")
            self.sleep(interval)
        raise PublishError("video belum selesai diproses Instagram setelah batas tunggu")

    def publish(self, container_id):
        return self._id(self._request("POST", f"{self.ig_user_id}/media_publish", {"creation_id": container_id}))

    def _request(self, method, path, fields):
        url = f"{GRAPH_HOST}/{self.version}/{path}"
        form = {**fields, "access_token": self.access_token}
        attempts = self.max_retries + 1
        for attempt in range(1, attempts + 1):
            try:
                status, headers, body = self.transport(method, url, form)
            except OSError as err:
                error = NetworkError(f"koneksi ke Graph API terputus ({type(err).__name__}: {err})")
            else:
                if status < 400 and "error" not in body:
                    return body
                error = classify_error(status, headers, body)
            if not error.retryable:
                raise error
            wait = error.retry_after if error.retry_after is not None else error.wait_hint * 2 ** (attempt - 1)
            if attempt == attempts or wait > self.max_wait:
                raise error
            LOG.warning("%s; percobaan %d dari %d gagal, coba lagi dalam %d detik",
                        error, attempt, attempts, wait)
            self.sleep(wait)
        raise AssertionError("unreachable")

    @staticmethod
    def _id(body):
        if not isinstance(body, dict) or not body.get("id"):
            raise GraphError(f"respons Graph API tanpa id: {str(body)[:200]}")
        return str(body["id"])


def publish_reel(client, video_url, caption):
    """Alur lengkap: container, tunggu sampai FINISHED, lalu publish. Mengembalikan id media."""
    container_id = client.create_container(video_url, caption)
    client.wait_until_ready(container_id)
    return client.publish(container_id)
