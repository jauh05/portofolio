# Jadwal posting baru: grid Content Planner

Dokumen ini menjelaskan skema jadwal yang berlaku di branch `feature/planner-grid`. Baca sampai habis sebelum mengubah jadwal, bot Reels, atau worker Kauiz.

## Ringkasan

- Jadwal dibuat dan diubah di grid Content Planner (Living Office). Tidak ada lagi pemilihan hari acak.
- Setiap chip di grid adalah satu jadwal mingguan. Hari: 1 = Senin sampai 7 = Minggu. Jam: `HH:MM`.
- Generate dimulai `lead_minutes` sebelum jam tayang. Default 120 menit. Kartu yang baru dipindah atau diubah tidak langsung di-generate: tunggu sampai jendela itu tiba.
- Kemunculan yang sudah lewat jam tayang tidak pernah di-generate otomatis.
- Posting terjadi tepat di jam tayang, tanpa konfirmasi.
- Hapus dari tabel Hasil Generate hanya menandai konten sebagai `dismissed` (database) atau menyembunyikan entri Reels (`REELS_HIDDEN_FILE`). Konten itu tidak dibuat ulang otomatis. Konten yang sedang diproses tidak bisa dihapus.
- Dashboard menulis setiap jadwal ke file slot JSON. Bot Reels dan worker Kauiz membaca file itu, bukan database.

## File slot

| Jenis | File default | Dibaca oleh | Variabel env |
| --- | --- | --- | --- |
| Reels Jauki | `storage/app/reels/slots.json` | `content-bot/generate_content.py --auto` | `REELS_SLOTS_FILE` |
| Feed dan Story Kauiz | `storage/app/kauiz/slots.json` | `deployment/kauiz-worker/kauiz_auto_worker.py auto` | `KAUIZ_SLOTS_FILE` |

Isi satu slot Reels: `id`, `label`, `day`, `time`, `lead_minutes`, `active`.
Isi satu slot Kauiz: `id`, `label`, `format` (`feed` atau `story`), `platform`, `day`, `time`, `timezone`, `lead_minutes`.

Jangan mengedit file slot dengan tangan. Ubah jadwal lewat grid, karena dashboard menulis file secara atomik.

## Bot Reels (content-bot)

Setiap kemunculan slot punya status di `content-bot/slot_state.json`, dengan kunci `id@waktu-ISO`:

| Status | Arti |
| --- | --- |
| (tanpa status) | Belum diproses. Render dimulai saat sudah masuk `lead_minutes` sebelum jam tayang |
| `rendered` | Video siap. Posting dilakukan setelah jam tayang |
| `posted` | Sudah terposting. Berisi `media_id` |
| `failed` | Posting gagal. Tidak diulang otomatis |
| `missed` | Lewat jam tayang tanpa status, misalnya bot mati. Tidak dikerjakan |

- Bot hanya melihat kemunculan dari 6 jam ke belakang sampai 7 hari ke depan.
- Tanpa `--live`, bot tidak menulis `slot_state.json` atau `history.json`.
- `videos_per_week` dan pemilihan hari acak tidak dipakai di jalur `--auto`. Flag `--force` dan `--import-bank` tetap ada untuk mode bank.

Cron yang dituju (belum dipasang di server):

```
*/5 * * * * cd /var/www/jauharfauzi.my.id/content-bot && nice -n 19 ./venv/bin/python generate_content.py --auto --live >> logs/cron.log 2>&1
```

## Worker Kauiz (deployment/kauiz-worker)

- Cron tiap 5 menit menjalankan `auto --live`. Tiap jalan hanya mengerjakan slot yang aktif.
- Gambar dirender sejak `lead_minutes` sebelum jam tayang, lalu disimpan di `KAUIZ_OUTPUT_DIR`.
- Posting terjadi di jam tayang. Jika sudah lewat 60 menit sejak jam tayang, slot tidak dipublish.
- `state.json` mencatat `tanggal-jam:format`, misalnya `2026-10-25-0900:feed`.
- File slot hilang: tidak ada yang dikerjakan. File slot rusak: worker berhenti dengan exit code 2.
- Kauiz belum tampil di tabel Hasil Generate. Hasilnya baru tercatat di `state.json` dan log.

## Dashboard

- Grid: `resources/js/living-office/PlannerGrid.jsx`, `ContentSlotGrid.jsx`, `plannerGridModel.js`.
- Menu "+": `ContentPlannerMenu.jsx`. Tombol kalender lama: `PlannerCalendar.jsx`.
- Tabel Hasil Generate: `ContentResultsTable.jsx`, dari `GET /office/api/content-results`.
- Slot Reels: `GET` dan `POST /office/api/reels-slots`, `PATCH` dan `DELETE /office/api/reels-slots/{id}`.

## Tes

```bash
cd content-bot && python3 test_slot_auto.py
cd deployment/kauiz-worker && python3 -m unittest test_kauiz_auto_worker -v
php artisan test --filter='OfficePlannerGridTest|OfficeReelsSettingsTest'
npm run build
```

Jalankan tes bot tanpa `-I`, karena tes mengimpor modul dari folder yang sama.

## Belum dilakukan di server

Semua langkah ini menunggu persetujuan user:

1. Deploy branch, lalu `composer dump-autoload -o`, `npm run build`, dan `php artisan optimize:clear`.
2. Isi `REELS_SLOTS_FILE` di `.env` Laravel dan `content-bot/.env`, lalu isi `KAUIZ_SLOTS_FILE` di `worker.env`.
3. Ganti cron Reels dari harian menjadi `*/5`.
4. Pasang cron worker Kauiz tiap 5 menit.

## Aturan untuk agen

- Jangan publish live, mengubah cron, atau push tanpa persetujuan user.
- Jangan mencetak isi `.env`, `worker.env`, atau token.
- Jangan commit `slot_state.json`, `state.json`, `history.json`, folder `output/`, atau `worker.env`.
- Zona waktu: Asia/Jakarta. Hari: 1 = Senin, 7 = Minggu.

## Prompt untuk opencode

Buka opencode di folder repo, lalu tempelkan teks ini:

```
Baca docs/jadwal-baru.md sampai habis. Setelah itu jelaskan skema jadwal baru dalam bahasa Indonesia, dan sebutkan apa yang masih harus dilakukan di server. Jangan publish, jangan ubah cron, dan jangan push tanpa persetujuan saya.
```
