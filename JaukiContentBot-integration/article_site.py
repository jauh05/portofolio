"""Klien API artikel internal untuk kauiz.web.id dan jaukitugas.my.id.

Kedua situs memakai pola yang sama (POST artikel, PUT ubah, POST {id}/publish, POST {id}/schedule) dengan token Bearer CONTENT_BOT_TOKEN
milik masing-masing situs, tetapi alamat, aturan gambar, dan bentuk respons berbeda. Perbedaan itu dipegang di SITES. Hanya pustaka standar.
"""

from __future__ import annotations

import json
import mimetypes
import time
import uuid
import http.client
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Any, Callable, Mapping, Optional


class SiteError(RuntimeError):
    pass


class NotFound(SiteError):
    """404 dari situs. `detail` memuat pesan dari situs: "Article not found" berarti jalurnya benar tetapi artikelnya tidak ada."""

    def __init__(self, message: str, detail: str = ""):
        super().__init__(message)
        self.detail = detail


@dataclass(frozen=True)
class SiteSpec:
    brand: str
    default_base_url: str
    image_field: str
    image_limit_kb: int
    timestamp_format: str


SITES = {
    "kauiz": SiteSpec("kauiz", "https://kauiz.web.id/v1/internal", "featured_image", 5120, "%Y-%m-%d %H:%M:%S"),
    "jauki": SiteSpec("jauki", "https://jaukitugas.my.id/api/internal", "featured_image", 2048, "%Y-%m-%d %H:%M:%S"),
}
USER_AGENT = "JaukiArticleWorker/1.0 (+https://jaukitugas.my.id)"
TEXT_FIELDS = ("title", "excerpt", "content", "category", "seo_title", "seo_description", "status", "scheduled_at")


def encode_multipart(fields: Mapping[str, Any], file_field: Optional[str] = None, file_name: str = "cover.jpg",
                     file_bytes: Optional[bytes] = None) -> tuple[bytes, str]:
    """Badan multipart/form-data. Daftar (tags) dikirim sebagai tags[]."""
    boundary = "----jauki" + uuid.uuid4().hex
    parts: list[bytes] = []

    def add(name: str, value: Any) -> None:
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode("utf-8"))

    for name, value in fields.items():
        if value is None:
            continue
        if isinstance(value, (list, tuple)):
            for item in value:
                add(f"{name}[]", item)
        else:
            add(name, value)
    if file_field and file_bytes is not None:
        kind = mimetypes.guess_type(file_name)[0] or "image/jpeg"
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{file_field}"; filename="{file_name}"\r\n'
                      f"Content-Type: {kind}\r\n\r\n").encode("utf-8") + file_bytes + b"\r\n")
    parts.append(f"--{boundary}--\r\n".encode("utf-8"))
    return b"".join(parts), f"multipart/form-data; boundary={boundary}"


def _default_send(method: str, url: str, headers: Mapping[str, str], body: Optional[bytes], timeout: float = 60.0) -> tuple[int, Any]:
    request = urllib.request.Request(url, data=body, method=method, headers=dict(headers))
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            raw = response.read().decode("utf-8", errors="replace")
            return response.status, json.loads(raw) if raw.strip() else {}
    except urllib.error.HTTPError as err:
        raw = err.read().decode("utf-8", errors="replace")
        try:
            return err.code, json.loads(raw)
        except ValueError:
            server = err.headers.get("Server", "") if err.headers else ""
            return err.code, {"message": (raw[:160] or "badan kosong") + (f" [server: {server}]" if server else "")}
    except (urllib.error.URLError, http.client.HTTPException, OSError, ValueError) as err:
        raise SiteError(f"koneksi ke situs gagal: {err!r}") from err


class ArticleSite:
    def __init__(self, brand: str, token: str, base_url: Optional[str] = None, *, send: Optional[Callable[..., tuple[int, Any]]] = None,
                 sleep: Callable[[float], None] = time.sleep, retry_waits: tuple[float, ...] = (3.0, 10.0)):
        if brand not in SITES:
            raise SiteError(f"Situs untuk brand {brand!r} tidak dikenal")
        if not token:
            raise SiteError(f"Token API artikel {brand} belum diatur")
        self.spec = SITES[brand]
        self.token = token
        self.base_url = (base_url or self.spec.default_base_url).rstrip("/")
        self._send = send or _default_send
        self._sleep = sleep
        self._waits = retry_waits

    # -- permintaan -----------------------------------------------------------------
    def _headers(self, extra: Optional[Mapping[str, str]] = None) -> dict[str, str]:
        # User-Agent sendiri: firewall seperti Cloudflare sering menolak "Python-urllib" bawaan dengan 403.
        return {"Authorization": "Bearer " + self.token, "Accept": "application/json", "User-Agent": USER_AGENT, **(extra or {})}

    def _request(self, method: str, path: str, *, json_body: Optional[Mapping[str, Any]] = None, multipart: Optional[tuple[bytes, str]] = None) -> dict[str, Any]:
        url = f"{self.base_url}{path}"
        if multipart:
            body, content_type = multipart
            headers = self._headers({"Content-Type": content_type})
        elif json_body is not None:
            body, headers = json.dumps(json_body).encode("utf-8"), self._headers({"Content-Type": "application/json"})
        else:
            body, headers = None, self._headers()
        for attempt in range(len(self._waits) + 1):
            try:
                status, payload = self._send(method, url, headers, body)
            except SiteError:
                if attempt == len(self._waits):
                    raise
                self._sleep(self._waits[attempt])
                continue
            if status >= 500 or status == 429:
                if attempt == len(self._waits):
                    raise SiteError(f"situs {self.spec.brand} gangguan (HTTP {status})")
                self._sleep(self._waits[attempt])
                continue
            return self._interpret(status, payload)
        raise SiteError("permintaan ke situs gagal")  # tidak tercapai

    def _interpret(self, status: int, payload: Any) -> dict[str, Any]:
        payload = payload if isinstance(payload, dict) else {}
        if status == 401:
            raise SiteError(f"token API artikel {self.spec.brand} ditolak (401), periksa CONTENT_BOT_TOKEN")
        if status == 404:
            raise NotFound("artikel atau jalur API tidak ditemukan di situs (404)", str(payload.get("message", "")))
        if status == 403:
            detail = str(payload.get("message", "")).strip()[:160]
            raise SiteError(f"akses ke situs {self.spec.brand} ditolak (403)" + (f": {detail}" if detail else " tanpa pesan")
                            + ". Token yang salah menghasilkan 401, jadi 403 biasanya berarti firewall atau Cloudflare memblokir klien ini")
        if status == 422:
            errors = payload.get("errors") or {}
            detail = "; ".join(f"{field}: {', '.join(map(str, messages))}" for field, messages in list(errors.items())[:4]) or payload.get("message", "")
            raise SiteError(f"situs {self.spec.brand} menolak isi artikel (422): {detail}")
        if status >= 400 or payload.get("success") is False:
            raise SiteError(f"situs {self.spec.brand} menolak permintaan (HTTP {status}): {str(payload.get('message', ''))[:200]}")
        data = payload.get("data")
        return data if isinstance(data, dict) else payload

    # -- operasi --------------------------------------------------------------------
    def _form(self, article: Mapping[str, Any], status: Optional[str] = None) -> dict[str, Any]:
        fields = {key: article.get(key) for key in TEXT_FIELDS if article.get(key) not in (None, "")}
        if article.get("tags"):
            fields["tags"] = list(article["tags"])
        if status:
            fields["status"] = status
        return fields

    def create_draft(self, article: Mapping[str, Any], image: Optional[bytes] = None) -> dict[str, Any]:
        fields = self._form(article, "draft")
        fields.pop("scheduled_at", None)
        if image is not None:
            self.check_image(image)
            body = encode_multipart(fields, self.spec.image_field, "cover.jpg", image)
            return self._summary(self._request("POST", "/articles", multipart=body))
        return self._summary(self._request("POST", "/articles", json_body=fields))

    def update_draft(self, article_id: Any, article: Mapping[str, Any], image: Optional[bytes] = None) -> dict[str, Any]:
        fields = self._form(article)
        fields.pop("status", None)
        fields.pop("scheduled_at", None)
        if image is not None:
            self.check_image(image)
            fields["_method"] = "PUT"  # Laravel tidak membaca multipart pada PUT, jadi POST dengan _method
            body = encode_multipart(fields, self.spec.image_field, "cover.jpg", image)
            return self._summary(self._request("POST", f"/articles/{article_id}", multipart=body))
        return self._summary(self._request("PUT", f"/articles/{article_id}", json_body=fields))

    def publish(self, article_id: Any) -> dict[str, Any]:
        return self._summary(self._request("POST", f"/articles/{article_id}/publish", json_body={}))

    def schedule(self, article_id: Any, scheduled_at: str) -> dict[str, Any]:
        return self._summary(self._request("POST", f"/articles/{article_id}/schedule", json_body={"scheduled_at": scheduled_at}))

    def check_access(self) -> None:
        """Pemeriksaan murah sebelum menulis artikel atau memanggil Muse. Artikel nomor 0 harus dijawab 404 "Article not found" dari aplikasi
        (token dan jalur benar). 404 lain, misalnya halaman galat framework atau proxy, berarti alamat dasar API salah."""
        try:
            self._request("GET", "/articles/0")
        except NotFound as err:
            if "article not found" not in err.detail.lower():
                raise SiteError(f"jalur API artikel tidak ditemukan di {self.base_url}/articles (404). Periksa alamat dasar API situs {self.spec.brand}") from err

    def get(self, article_id: Any) -> dict[str, Any]:
        return self._summary(self._request("GET", f"/articles/{article_id}"))

    def check_image(self, image: bytes) -> None:
        if len(image) > self.spec.image_limit_kb * 1024:
            raise SiteError(f"gambar sampul {len(image) // 1024} KB melebihi batas {self.spec.image_limit_kb} KB situs {self.spec.brand}")

    @staticmethod
    def _summary(data: Mapping[str, Any]) -> dict[str, Any]:
        if data.get("id") in (None, ""):
            raise SiteError("respons situs tidak memuat id artikel")
        return {"id": data["id"], "slug": data.get("slug"), "status": data.get("status"), "url": data.get("url"),
                "image_url": data.get("featured_image") or data.get("image_url"), "published_at": data.get("published_at")}
