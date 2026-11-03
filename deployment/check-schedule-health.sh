#!/usr/bin/env bash
# Cek kesehatan jadwal posting. HANYA MEMBACA: tidak mengubah file, tidak memanggil Instagram, Muse, atau Telegram.
# Tidak mencetak token atau kunci: file .env hanya diperiksa terisi atau kosong. Jalankan di server sebagai user jauhar:
#   bash deployment/check-schedule-health.sh
# Path bisa diganti lewat variabel lingkungan bila lokasi di server berbeda.

APP_DIR="${APP_DIR:-/var/www/jauharfauzi.my.id}"
KAUIZ_DIR="${KAUIZ_DIR:-/home/jauhar/deploy/kauiz/kauiz-worker}"
JAUKI_DIR="${JAUKI_DIR:-/home/jauhar/deploy/jauki/jauki-worker}"
REELS_DIR="${REELS_DIR:-$APP_DIR/content-bot}"
ARTICLE_DIR="${ARTICLE_DIR:-/home/jauhar/deploy/article/worker}"
BOT_DIR="${BOT_DIR:-/home/jauhar/jauki-content-bot}"
PY="${PY:-python3}"
PROBLEMS=0

section() { printf '\n== %s ==\n' "$1"; }
ok()      { printf '  OK       %s\n' "$1"; }
warn()    { printf '  PERIKSA  %s\n' "$1"; PROBLEMS=$((PROBLEMS + 1)); }
mask()    { sed -E 's/(TOKEN|KEY|SECRET|PASSWORD)=[^ ]*/\1=***/g'; }

# Nilai variabel dari file env (hanya untuk path dan angka, bukan rahasia).
envval() { [ -f "$1" ] && grep -E "^$2=" "$1" | head -1 | cut -d= -f2- | sed -E "s/^['\"]//; s/['\"]$//"; }
# Variabel terisi atau kosong, tanpa menampilkan isinya.
haskey() {
  local value; value="$(envval "$1" "$2")"
  if [ -n "$value" ]; then ok "$2 terisi ($1)"; else warn "$2 KOSONG atau tidak ada di $1"; fi
}

section "Waktu server"
date '+  %Y-%m-%d %H:%M:%S %Z'
echo "  Jakarta: $(TZ=Asia/Jakarta date '+%Y-%m-%d %H:%M %A')"

section "Cron user $(whoami)"
CRON="$(crontab -l 2>/dev/null)"
if [ -z "$CRON" ]; then warn "tidak ada crontab untuk user ini"; else
  ACTIVE="$(printf '%s\n' "$CRON" | grep -v '^[[:space:]]*#' | grep -E 'kauiz|jauki|generate_content|schedule:run|auto_worker')"
  COMMENTED="$(printf '%s\n' "$CRON" | grep -E '^[[:space:]]*#.*(kauiz|jauki|generate_content|schedule:run|auto_worker)')"
  if [ -n "$ACTIVE" ]; then printf '%s\n' "$ACTIVE" | mask | sed 's/^/  aktif: /'; else warn "tidak ada baris cron aktif untuk worker atau scheduler"; fi
  [ -n "$COMMENTED" ] && printf '%s\n' "$COMMENTED" | mask | sed 's/^/  DIKOMENTARI (tidak jalan): /'
  printf '%s\n' "$ACTIVE" | grep -q 'schedule:run' && ok "scheduler Laravel (schedule:run) aktif" || warn "schedule:run tidak ada: Threads, artikel, dan penerbitan dari dashboard tidak akan jalan"
  printf '%s\n' "$ACTIVE" | grep -q 'kauiz_auto_worker.py' && ok "ada cron untuk worker Instagram" || warn "tidak ada cron worker Instagram (Feed dan Story Kauiz dan Jauki)"
  printf '%s\n' "$ACTIVE" | grep -q 'generate_content.py' && ok "ada cron untuk Reels" || warn "tidak ada cron Reels (generate_content.py --auto --live)"
fi

section "Layanan systemd (user)"
for unit in jauki-content jauki-article-worker jauki-analyst-worker; do
  state="$(systemctl --user is-active "$unit.service" 2>/dev/null)"
  [ "$state" = "active" ] && ok "$unit: active" || warn "$unit: ${state:-tidak ditemukan}"
done

section "Scheduler Laravel"
if [ -d "$APP_DIR" ]; then
  (cd "$APP_DIR" && php artisan schedule:list 2>&1 | grep -E 'office-scheduled|office:' | sed 's/^/  /' | head -6)
else warn "folder dashboard $APP_DIR tidak ada"; fi

# Ringkas file slot: jumlah dan isi singkat.
slotsummary() {
  local label="$1" path="$2" hint="${3:-KAUIZ_SLOTS_FILE di worker.env}"
  if [ -z "$path" ]; then warn "$label: path file slot tidak diketahui (cek $hint)"; return; fi
  if [ ! -f "$path" ]; then warn "$label: file slot tidak ada ($path)"; return; fi
  if [ ! -r "$path" ]; then warn "$label: file slot tidak bisa dibaca user ini ($path)"; return; fi
  $PY - "$label" "$path" <<'PYEOF'
import json, sys, datetime
label, path = sys.argv[1], sys.argv[2]
try:
    slots = json.load(open(path))
except Exception as err:
    print(f"  PERIKSA  {label}: file slot rusak ({err})"); sys.exit(0)
age = (datetime.datetime.now().timestamp() - __import__("os").path.getmtime(path)) / 3600
days = "Sn Sl Rb Km Jm Sb Mg".split()
print(f"  OK       {label}: {len(slots)} slot, file diperbarui {age:.1f} jam lalu ({path})")
for s in sorted(slots, key=lambda s: (s.get("day", 0), s.get("time") or "")):
    brand = s.get("brand", "-")
    flag = "" if s.get("time") else "  <-- BELUM ADA JAM, tidak akan diproses"
    print(f"           {days[int(s['day']) - 1]} {s.get('time') or '--:--'} {s.get('platform','-')}/{s.get('format','-')} brand={brand} lead={s.get('lead_minutes','-')}{flag}")
PYEOF
}

# Path slot yang ditulis dashboard harus sama dengan yang dibaca worker. Kalau beda, worker membaca file yang tidak pernah terisi.
samepath() {
  local label="$1" dash="$2" worker="$3"
  [ -n "$dash" ] && [ -n "$worker" ] || { warn "$label: tidak bisa membandingkan path (dashboard='$dash' worker='$worker')"; return; }
  if [ "$(readlink -f "$dash" 2>/dev/null || echo "$dash")" = "$(readlink -f "$worker" 2>/dev/null || echo "$worker")" ]; then
    ok "$label: dashboard menulis dan worker membaca file yang sama"
  else
    warn "$label: BEDA FILE. Dashboard menulis ke $dash, worker membaca $worker. Samakan keduanya."
  fi
}

# Ringkas state worker Instagram: kapan terakhir terbit dan error terakhir di log.
workersummary() {
  local label="$1" dir="$2"
  section "Worker Instagram $label"
  [ -d "$dir" ] || { warn "folder $dir tidak ada"; return; }
  local envf="$dir/worker.env"
  [ -f "$envf" ] || warn "$envf tidak ada"
  for key in KAUIZ_IG_USER_ID KAUIZ_IG_ACCESS_TOKEN OPENROUTER_API_KEY KAUIZ_PUBLIC_DIR KAUIZ_PUBLIC_BASE_URL; do haskey "$envf" "$key"; done
  local brand; brand="$(envval "$envf" KAUIZ_BRAND)"; echo "  brand worker: ${brand:-kauiz (bawaan)}"
  local want; want="$(printf '%s' "$label" | tr 'A-Z' 'a-z')"
  [ "${brand:-kauiz}" = "$want" ] && ok "KAUIZ_BRAND sesuai ($want)" || warn "KAUIZ_BRAND=${brand:-kauiz} tidak cocok untuk worker $label (harus $want)"
  slotsummary "slot $label" "$(envval "$envf" KAUIZ_SLOTS_FILE)"
  local dashvar="KAUIZ_SLOTS_FILE"; [ "$want" = "jauki" ] && dashvar="JAUKI_SLOTS_FILE"
  local dashpath; dashpath="$(envval "$APP_DIR/.env" "$dashvar")"; [ -z "$dashpath" ] && dashpath="$APP_DIR/storage/app/$want/slots.json"
  samepath "slot $label" "$dashpath" "$(envval "$envf" KAUIZ_SLOTS_FILE)"
  local pub; pub="$(envval "$envf" KAUIZ_PUBLIC_DIR)"
  [ -n "$pub" ] && { [ -w "$pub" ] && ok "folder publik bisa ditulis ($pub)" || warn "folder publik tidak bisa ditulis ($pub)"; }
  if [ -f "$dir/state.json" ]; then
    $PY - "$dir/state.json" <<'PYEOF'
import json, sys
state = json.load(open(sys.argv[1]))
published = state.get("published", {})
recent = sorted(published.items(), key=lambda kv: kv[1].get("published_at", ""))[-4:]
print(f"  terbit total {len(published)}; terakhir:")
for key, rec in recent:
    print(f"           {key} pada {rec.get('published_at','?')[:16]}")
images = state.get("images", {})
print(f"  gambar Muse per hari (batas harian): {dict(list(images.items())[-3:])}")
PYEOF
  else warn "state.json belum ada (belum pernah posting)"; fi
  local log="$dir/logs/kauiz-worker.log"
  if [ -f "$log" ]; then
    echo "  log terakhir yang bermasalah:"
    grep -E "ERROR|WARNING" "$log" | tail -4 | mask | cut -c1-200 | sed 's/^/           /'
    echo "  entri log terakhir: $(tail -1 "$log" | cut -c1-19)"
    since="$(date -d '60 minutes ago' '+%Y-%m-%d %H:%M' 2>/dev/null)"
    if [ -n "$since" ]; then
      recent="$(awk -v s="$since" 'substr($0,1,16) >= s && / ERROR /' "$log")"
      count="$(printf '%s' "$recent" | grep -c .)"
      if [ "$count" -gt 0 ]; then
        if printf '%s' "$recent" | grep -qiE "budget|quota|credit|HTTP 40[123]"; then
          warn "$label: $count error dalam 60 menit terakhir, penyebab: ANGGARAN/KREDENSIAL OPENROUTER DITOLAK. Gambar berikutnya tidak bisa dibuat sampai anggaran dinaikkan"
        else
          warn "$label: $count error dalam 60 menit terakhir (lihat log di atas)"
        fi
      else ok "$label: tidak ada error dalam 60 menit terakhir"; fi
    fi
  else warn "log $log belum ada"; fi
}
workersummary "Kauiz" "$KAUIZ_DIR"
workersummary "Jauki" "$JAUKI_DIR"

section "Reels Jauki (content-bot)"
if [ -d "$REELS_DIR" ]; then
  for key in IG_USER_ID IG_ACCESS_TOKEN PUBLIC_VIDEO_BASE_URL TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID; do haskey "$REELS_DIR/.env" "$key"; done
  lead="$(envval "$REELS_DIR/.env" REELS_LEAD_MINUTES)"; echo "  lead render: ${lead:-1440 (bawaan, sehari)} menit"
  botslots="$(envval "$REELS_DIR/.env" REELS_SLOTS_FILE)"; [ -z "$botslots" ] && botslots="$REELS_DIR/reels_slots.json"
  dashslots="$(envval "$APP_DIR/.env" REELS_SLOTS_FILE)"; [ -z "$dashslots" ] && dashslots="$APP_DIR/storage/app/reels/slots.json"
  slotsummary "slot Reels" "$botslots" "REELS_SLOTS_FILE di content-bot/.env"
  samepath "slot Reels" "$dashslots" "$botslots"
  if [ -f "$REELS_DIR/slot_state.json" ]; then
    $PY - "$REELS_DIR/slot_state.json" <<'PYEOF'
import json, sys, collections
state = json.load(open(sys.argv[1]))
print("  status slot:", dict(collections.Counter(v.get("status") for v in state.values())))
print("  telegram:", dict(collections.Counter(v.get("telegram", "-") for v in state.values() if v.get("status") == "rendered")))
for key, rec in sorted(state.items(), key=lambda kv: kv[1].get("slot_at", ""))[-4:]:
    print(f"           {rec.get('slot_at','?')[:16]} {rec.get('status')} telegram={rec.get('telegram','-')}")
PYEOF
  else warn "slot_state.json belum ada (Reels belum pernah dirender lewat mode --auto)"; fi
  if [ -f "$REELS_DIR/copy_bank.json" ]; then
    left="$($PY -c "import json,sys; b=json.load(open(sys.argv[1])); print(sum(1 for i in b if not i.get('used_at')))" "$REELS_DIR/copy_bank.json" 2>/dev/null)"
    if [ -n "$left" ] && [ "$left" -ge 10 ]; then ok "bank teks Reels: $left tersisa"; else warn "bank teks Reels tinggal ${left:-?} (render berhenti saat habis)"; fi
  else warn "copy_bank.json tidak ada: Reels tidak punya teks untuk dirender"; fi
  [ -d "$REELS_DIR/raw_videos" ] && echo "  video mentah: $(ls "$REELS_DIR/raw_videos" 2>/dev/null | wc -l) file" || warn "folder raw_videos tidak ada"
  log="$(ls -t "$REELS_DIR"/logs/*.log 2>/dev/null | head -1)"
  [ -n "$log" ] && { echo "  log terakhir yang bermasalah:"; grep -E "ERROR|WARNING|Traceback" "$log" | tail -3 | mask | cut -c1-200 | sed 's/^/           /'; }
else warn "folder Reels $REELS_DIR tidak ada"; fi

section "Worker artikel dan Threads"
[ -f "$ARTICLE_DIR/worker.env" ] && { for key in OFFICE_BRIDGE_URL OFFICE_BRIDGE_TOKEN ARTICLE_KAUIZ_TOKEN ARTICLE_JAUKI_TOKEN OPENROUTER_API_KEY; do haskey "$ARTICLE_DIR/worker.env" "$key"; done; } || warn "$ARTICLE_DIR/worker.env tidak ada"
[ -f "$BOT_DIR/.env" ] && { for key in OPENROUTER_API_KEY VISUAL_MEMORY_DIR OFFICE_BRIDGE_URL OFFICE_BRIDGE_TOKEN; do haskey "$BOT_DIR/.env" "$key"; done; }

section "Dashboard: jadwal, konten, dan perintah 3 hari terakhir"
if [ -d "$APP_DIR" ]; then
  (cd "$APP_DIR" && php artisan tinker --execute='
    use App\Models\OfficeContentSchedule as S; use App\Models\OfficeContentItem as I; use App\Models\OfficeCommand as C;
    echo "  jadwal aktif per brand/platform/timing/publishing:\n";
    foreach (S::with("brand")->where("is_active", true)->get()->groupBy(fn($s) => ($s->brand?->slug ?? "-")."/".$s->platform."/".$s->content_type." timing=".($s->generation_timing ?? "-")."/".($s->generation_lead_minutes ?? "-")." publish=".($s->publishing_mode ?? "-")) as $k => $g) echo "    ".$g->count()."x $k\n";
    echo "  Instagram Kauiz/Jauki yang TIDAK akan diposting worker (publishing review, atau tanpa jam):\n";
    $none = 0;
    foreach (S::with("brand")->where("is_active", true)->where("platform", "instagram")->get() as $s) {
        if (! in_array($s->brand?->slug, ["kauiz", "jauki"], true)) continue;
        $auto = ($s->publishing_mode ?? "review") === "automatic";
        $timed = $s->schedule_type === "one_time" ? (bool) $s->scheduled_at : ! empty($s->recurrence_rule["time"]);
        if ($auto && $timed) continue;
        $none++;
        echo "    ".$s->brand->slug."/".$s->content_type." ".$s->name." (".($s->schedule_type === "one_time" ? ($s->scheduled_at?->format("Y-m-d H:i") ?? "tanpa tanggal") : "mingguan").") publish=".($s->publishing_mode ?? "-").($timed ? "" : " TANPA JAM").PHP_EOL;
    }
    if ($none === 0) echo "    (tidak ada)\n";
    echo "  Threads/artikel yang menunggu persetujuan sebelum terbit (publishing review):\n";
    $held = S::with("brand")->where("is_active", true)->whereIn("platform", ["threads", "article", "blog"])->where("publishing_mode", "review")->get();
    foreach ($held as $s) echo "    ".($s->brand?->slug ?? "-")."/".$s->platform." ".$s->name.PHP_EOL;
    if ($held->isEmpty()) echo "    (tidak ada)\n";
    echo "  konten 3 hari terakhir per status:\n";
    foreach (I::where("created_at", ">=", now()->subDays(3))->get()->groupBy(fn($i) => $i->platform."/".$i->content_type." ".$i->status) as $k => $g) echo "    ".$g->count()."x $k\n";
    echo "  perintah 3 hari terakhir per agen/aksi/status:\n";
    foreach (C::where("created_at", ">=", now()->subDays(3))->get()->groupBy(fn($c) => $c->agent_id." ".$c->action." ".$c->status) as $k => $g) echo "    ".$g->count()."x $k\n";
    $failedHour = C::where("status", "failed")->where("created_at", ">=", now()->subHour())->count();
    if ($failedHour > 3) echo "  PERIKSA  ".$failedHour." perintah worker gagal dalam 1 jam terakhir (kemungkinan terus diulang atau anggaran/kredensial ditolak)\n";
    foreach (C::where("status", "failed")->where("created_at", ">=", now()->subDays(3))->latest()->limit(3)->get() as $c) echo "  gagal: ".$c->agent_id." ".$c->action." - ".mb_substr((string) $c->error, 0, 110)."\n";
  ' 2>&1 | grep -v -E "^\s*$|Psy Shell|INFO" | head -40 | tee /tmp/.health-dash.$$)
  PROBLEMS=$((PROBLEMS + $(grep -c '^  PERIKSA' /tmp/.health-dash.$$ 2>/dev/null || echo 0))); rm -f /tmp/.health-dash.$$
else warn "tidak bisa membaca database dashboard"; fi

section "Ringkasan"
if [ "$PROBLEMS" -eq 0 ]; then echo "  Tidak ada temuan. Jadwal siap berjalan."; else echo "  $PROBLEMS temuan PERIKSA di atas. Perbaiki sebelum menganggap jadwal siap."; fi
exit 0
