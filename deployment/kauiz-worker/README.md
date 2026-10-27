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
