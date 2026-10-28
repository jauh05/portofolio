# Kauiz auto worker

Worker untuk postingan Instagram Kauiz. Jadwal diambil dari grid Content Planner (file slot dari dashboard). Tiap slot: gambar dirender dengan Pillow 2 jam sebelum tayang, lalu dipublish ke Instagram Graph API tepat di jam tayang. Dijalankan dari crontab tiap 5 menit.

**Status: belum dipasang di server.** Publish live menunggu persetujuan. Posting Instagram tidak bisa ditarik kembali.

Skrip utama di server (`/home/jauhar/deploy/kauiz/generate_kauiz_post.py`) tidak ada di repo ini dan belum bisa diakses saat worker ini dibuat. Worker ini berdiri sendiri dan tidak mengimpor skrip tersebut.

## Isi folder

- `kauiz_auto_worker.py`: worker dan CLI (`auto`)
- `test_kauiz_auto_worker.py`: tes offline, tanpa panggilan jaringan
- `worker.env.example`: contoh konfigurasi
- `.gitignore`: mengecualikan `worker.env`, `state.json`, `slots.json`, log, dan folder render

## Syarat server

- Python 3.10 atau lebih baru, dengan `zoneinfo` dan tzdata sistem.
- Pillow 10.1 atau lebih baru: `pip install "pillow>=10.1"`
- Folder `KAUIZ_PUBLIC_DIR` harus bisa diakses publik lewat `KAUIZ_PUBLIC_BASE_URL` (HTTPS). Instagram mengambil gambar dari URL itu.
- Akun Instagram Professional (Business atau Creator) terhubung ke Halaman Facebook. Token memiliki izin `instagram_content_publish`.

## Pemasangan

```bash
cd /home/jauhar/deploy/kauiz
cp worker.env.example worker.env
chmod 600 worker.env
# isi worker.env: KAUIZ_IG_USER_ID, KAUIZ_IG_ACCESS_TOKEN, KAUIZ_PUBLIC_DIR, KAUIZ_PUBLIC_BASE_URL, KAUIZ_SLOTS_FILE
mkdir -p logs
```

## Jadwal

Jadwal tidak diisi di worker ini. Dashboard Content Planner menulis tiap slot Kauiz ke file `KAUIZ_SLOTS_FILE` (di server Laravel: `storage/app/kauiz/slots.json`). Isi satu slot:

- `format`: `feed` atau `story`
- `day`: 1 sampai 7, Senin = 1, Minggu = 7
- `time`: `HH:MM` dalam zona `timezone`
- `lead_minutes`: berapa menit sebelum jam tayang gambar mulai dibuat (default 120)

Tiap jalan cron mengerjakan hal berikut:

1. Slot yang sudah masuk jendela lead time: gambar dirender dan disimpan di `KAUIZ_OUTPUT_DIR`. Belum ada posting.
2. Slot yang sudah sampai jam tayang: gambar yang sudah ada dipublish sekali.
3. Slot yang lewat lebih dari 60 menit sejak jam tayang tidak dipublish, dan tidak dikirim ulang.

Jika file slot belum ada, worker tidak mengerjakan apa pun. Jika file slot rusak, worker berhenti dengan exit code 2 dan tidak memposting apa pun.

## Dry run (tanpa panggilan API)

Tanpa `--live`, worker hanya merender gambar dan mencatat caption. Tidak ada request ke Instagram dan tidak ada yang ditulis ke `state.json`.

```bash
python3 kauiz_auto_worker.py auto --verbose
```

Jalankan tes:

```bash
python3 -m unittest test_kauiz_auto_worker -v
```

## Mode Muse (gambar dari Muse, teks dari Pillow)

`KAUIZ_IMAGE_MODE=muse` membuat latar gambar lewat OpenRouter (`meta/muse-image`, body sama dengan bot Jauki), lalu
menulis teks dengan Pillow. Cara kerjanya:

1. Template dipilih dari 27 template oleh `VisualTemplateSelector`, memakai pola konten (`infer_pattern`) dan riwayat di
   `state.json` (tidak mengulang template dalam 3 pemilihan terakhir). Seed berasal dari `slot_key`.
2. Prompt dibuat compiler visual memory dengan palet Kauiz. Prompt melarang teks dan menyebut area yang harus polos,
   diturunkan dari zona teks di `template-layouts.json`.
3. `visual_overlay.py` menulis headline, subline, poin, CTA, dan footer di zona template itu dengan font brand
   (League Spartan, Archivo Black, Plus Jakarta Sans). Ukuran font disesuaikan, dan panel muncul hanya bila latar ramai.
4. Isi diambil dari slot dashboard (`topic`, `key_message`, `brief`, `key_points`, `visual_prompt`). Bila slot kosong,
   dipakai topik bawaan (`TOPICS`) sebagai data acak.

Dry run di mode muse tidak memanggil OpenRouter. Worker menulis `output/<slot>-<format>.prompt.txt`, `.plan.json`, dan
`.preview.jpg` (layout di atas latar placeholder). Latar yang sudah dibayar disimpan sebagai `.bg.jpg` dan dipakai ulang
bila overlay gagal. Batas harian diatur `KAUIZ_MAX_IMAGES_PER_DAY` (bawaan 12).

### Sumber materi: analisis dashboard atau bank

- **Slot punya isi** (topik, key message, brief dari analisis atau jadwal): model teks (`KAUIZ_LLM_MODEL`, bawaan
  `meta-llama/llama-3.1-8b-instruct` lewat OpenRouter, API key sama dengan Muse) menulis prompt Muse, caption, dan hashtag.
  Hasilnya diperiksa dulu: teks persis dalam tanda kutip, warna hanya dari palet, harga hanya dari data, tanpa klaim
  terlarang, kalimat margin dipertahankan, caption maksimal 2200 karakter, 3 sampai 12 hashtag. Bila gagal, ada satu
  perbaikan, lalu endpoint cadangan (bila diatur), lalu dipakai versi dari kartu gaya.
- **Slot kosong**: dipakai materi dari bank `prompt-bank/<brand>-<format>.json` (Kauiz dan Jauki masing-masing 100 feed dan
  200 story). Materi memuat keluh kesah, tip, dan fitur nyata dari data brand, jadi tidak monoton. Tidak ada panggilan
  model teks. Tiap materi hanya dipakai sekali (dicatat di `state.json`, kunci `bank`). Bila bank habis dan slot kosong,
  slot dilewati dengan pesan error di log, bukan mengulang.
- Dry run tidak menghabiskan materi bank. Materi baru dianggap terpakai saat latar Muse berhasil dibuat.
- Jauki (humor tentang tugas dan mahasiswa) hanya berupa data: jalur live Jauki ada di bot luar dan belum tersambung ke
  worker ini.

### Penulis prompt dan caption (mode teks Muse)

Dengan `KAUIZ_TEXT_MODE=muse` (default), Muse menulis headline, subline, poin, dan footer langsung di gambar. Prompt dibuat
`visual_prompt_writer.py` dari data brand (`brand-data/<brand>.json`), kartu gaya template (`template-styles.json`),
palet, dan ukuran (feed 4:5 atau story 9:16). Story tidak memakai caption.

- Hasil penulis disimpan di `output/<slot>-<format>.writer.json` dan dipakai ulang, jadi model tidak dipanggil dua kali.
- Isi `brand-data` yang berstatus draft (nada bahasa dan hashtag) perlu dikonfirmasi pemilik.
- Muse bisa mengembalikan rasio yang meleset (story 2:3), lalu tepinya dipotong. Karena itu margin kiri dan kanan story 20 persen.
- Ejaan teks di gambar ditulis Muse dan belum diperiksa otomatis. Periksa hasil pertama di Instagram.

### Dua brand (Kauiz dan Jauki)

Worker yang sama melayani dua brand lewat `KAUIZ_BRAND`. Pisahkan per folder: `/home/jauhar/deploy/kauiz/kauiz-worker` untuk
Kauiz dan folder lain (mis. `/home/jauhar/deploy/jauki/jauki-worker`) untuk Jauki, masing-masing dengan `worker.env`, `state.json`,
dan token Instagram sendiri. Bank materi, data brand, dan footer per brand: `prompt-bank/<brand>-<format>.json`,
`brand-data/<brand>.json`, `footer_text`. Kode menolak bank atau data yang berlabel brand lain, dan pencatatan pemakaian bank
memakai kunci `brand:format`. Konten Jauki berupa humor tentang tugas dan mahasiswa, tanpa promosi layanan.

### Probe rasio Muse

Muse bisa mengembalikan 2:3 walau diminta 4:5. Probe ini mencoba empat cara mengirim rasio (sekitar $0,01 per gambar yang
berhasil) dan mencetak ukuran gambar yang kembali. Pakai cara yang menghasilkan 1024x1280 atau 4:5 untuk `KAUIZ_MUSE_ASPECT`.

```bash
cd /home/jauhar/deploy/kauiz/kauiz-worker && python3 - <<'EOF'
import base64, io, os
import kauiz_auto_worker as w
from PIL import Image
w.load_env_file(w.WORKER_DIR / "worker.env")
key = os.environ["OPENROUTER_API_KEY"]
prompt = "Portrait 4:5 composition. A plain cream background with a single navy circle in the center. No text."
base = {"model": "meta/muse-image", "prompt": prompt, "output_format": "jpeg"}
variants = {
    "tanpa parameter": {},
    "image_config": {"image_config": {"aspect_ratio": "4:5"}},
    "aspect_ratio": {"aspect_ratio": "4:5"},
    "size": {"size": "1024x1280"},
}
for name, extra in variants.items():
    try:
        data = w.http_post_json("https://openrouter.ai/api/v1/images", {"Authorization": f"Bearer {key}"}, {**base, **extra})
        item = data["data"][0]
        raw = base64.b64decode(item["b64_json"]) if item.get("b64_json") else w.http_get_bytes(item["url"])
        print(name, "->", Image.open(io.BytesIO(raw)).size)
    except Exception as err:
        print(name, "-> gagal:", str(err)[:200])
EOF
```

## Crontab

Baris di bawah **belum dipasang**. Pasang hanya setelah publish live disetujui. Cron berjalan tiap 5 menit. Jam tayang tidak diatur di crontab, melainkan di file slot.

Buka editor crontab dengan `crontab -e`, lalu tambahkan:

```bash
*/5 * * * * cd /home/jauhar/deploy/kauiz && /usr/bin/python3 kauiz_auto_worker.py auto --live >> /home/jauhar/deploy/kauiz/logs/cron.log 2>&1
```

- Zona waktu server tidak memengaruhi jam tayang. Jam tayang memakai `timezone` di setiap slot.
- Cek path Python dengan `which python3`. Pastikan Pillow terpasang untuk Python itu.
- Setelah disimpan, cek dengan `crontab -l`.
- Worker membaca `worker.env` sendiri, jadi crontab tidak perlu menyetel variabel lain.
- `logs/cron.log` hanya menerima error yang terjadi sebelum logging aktif, misalnya Python tidak ditemukan. Log normal ada di `logs/kauiz-worker.log`.

## Error log dan exit code

Log normal ditulis ke `logs/kauiz-worker.log` (rotasi 5 MB, 5 file). Token tidak pernah dicetak.

| Kejadian | Level | Perilaku |
| --- | --- | --- |
| Koneksi ke Graph API putus | WARNING lalu ERROR | Retry dengan jeda 10, 20, 40 detik. Jika gagal semua: `koneksi ke Graph API terputus` |
| Rate limit (HTTP 429 atau kode 4, 17, 32, 613, 80001) | WARNING lalu ERROR | Menunggu `Retry-After` jika tidak melebihi `KAUIZ_MAX_WAIT_SECONDS`. Jika tidak, slot dilewati: `rate limit Instagram`. Slot itu tidak diulang |
| Token ditolak (kode 190, 102) | ERROR | Tidak di-retry. Perbaiki `KAUIZ_IG_ACCESS_TOKEN` |
| Error sementara (5xx, kode 1, 2, 9007) | WARNING lalu ERROR | Retry dengan jeda 15, 30, 60 detik |
| Variabel publish kosong | ERROR | Berhenti sebelum render |
| `state.json` rusak | ERROR | Berhenti. Jangan dihapus sebelum mengecek Instagram, agar tidak ada posting dobel |
| `slots.json` tidak bisa dibaca atau rusak | ERROR | Berhenti. Tidak ada yang diposting sampai file diperbaiki |

Exit code: `0` berhasil atau dry run, `1` ada format yang gagal, `2` konfigurasi, state, atau file slot bermasalah, `3` worker lain masih berjalan.

## Idempotensi

`state.json` mencatat tiap `tanggal-jam:format` yang sudah dipublish, misalnya `2026-10-25-0900:feed`. Slot yang sama tidak dikirim ulang jika cron berjalan dua kali. Lock file mencegah dua worker berjalan bersamaan.

## Batasan yang perlu diketahui

- **Link di story:** Graph API tidak mendukung link sticker. Ajakan ke link bio ditulis di gambar story ("LINK DI BIO KAUIZ").
- **Komentar KAUIZ:** caption feed mengajak pembaca berkomentar "KAUIZ". Worker ini tidak membalas komentar. Balasan harus dilakukan manual, atau dibuat sebagai worker terpisah yang memakai webhook komentar.
- **Teks:** topik dan kalimatnya ada di daftar `TOPICS` di kode, bukan dari LLM.
- **Font:** tanpa `KAUIZ_FONT_BOLD` dan `KAUIZ_FONT_REGULAR`, render memakai font default Pillow. Brand memakai League Spartan dan Plus Jakarta Sans.
- **Versi API:** `v23.0` ditetapkan dari perkiraan, belum diverifikasi ke dokumentasi Meta. Cek sebelum publish live.
- **Folder publik:** file hasil render menumpuk di `KAUIZ_PUBLIC_DIR`. Bersihkan secara berkala, misalnya file yang lebih dari 30 hari.
