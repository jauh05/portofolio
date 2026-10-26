<?php

namespace App\Http\Controllers;

use App\Models\OfficeContentItem;
use App\Services\ReelsHistoryReader;
use App\Services\SlotFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Grid jadwal mingguan: slot Reels (file untuk bot) dan tabel hasil generate (gabungan DB dan riwayat bot).
 */
class OfficePlannerGridController extends Controller
{
    public function reelsSlots(): JsonResponse
    {
        return response()->json(['slots' => SlotFile::read(config('reels.slots_path'))]);
    }

    public function storeReelsSlot(Request $request): JsonResponse
    {
        $slots = SlotFile::read(config('reels.slots_path'));
        $slot = array_merge(
            ['id' => (string) Str::uuid(), 'brand' => 'jauki', 'label' => 'Reels Jauki', 'time' => null, 'lead_minutes' => 120, 'active' => true],
            $this->validatedSlot($request, true),
        );
        $slots[] = $slot;
        SlotFile::write(config('reels.slots_path'), $slots);

        return response()->json(['slot' => $slot], 201);
    }

    public function updateReelsSlot(Request $request, string $id): JsonResponse
    {
        $slots = SlotFile::read(config('reels.slots_path'));
        $index = collect($slots)->search(fn (array $slot) => $slot['id'] === $id);
        abort_if($index === false, 404, 'Slot tidak ditemukan.');

        $slots[$index] = array_merge($slots[$index], $this->validatedSlot($request, false));
        SlotFile::write(config('reels.slots_path'), $slots);

        return response()->json(['slot' => $slots[$index]]);
    }

    public function destroyReelsSlot(string $id): JsonResponse
    {
        $slots = collect(SlotFile::read(config('reels.slots_path')))->reject(fn (array $slot) => $slot['id'] === $id)->values()->all();
        SlotFile::write(config('reels.slots_path'), $slots);

        return response()->json(['status' => 'deleted']);
    }

    public function contentResults(): JsonResponse
    {
        $items = OfficeContentItem::query()
            ->where('status', '!=', 'dismissed')
            ->with('brand')
            ->latest('generated_at')
            ->limit(200)
            ->get()
            ->map(fn (OfficeContentItem $item) => [
                'id' => $item->id,
                'source' => 'db',
                'scheduleId' => $item->schedule_id,
                'platform' => $item->platform,
                'contentType' => $item->content_type,
                'brand' => $item->brand?->slug,
                'title' => $item->title,
                'text' => $item->text,
                'imageUrl' => $item->image_url,
                'videoUrl' => null,
                'status' => $item->status,
                'scheduledAt' => $item->metadata['scheduled_at'] ?? null,
                'publishedAt' => $item->published_at?->toIso8601String(),
                'error' => $item->metadata['publish_error'] ?? null,
                'mediaId' => $item->external_id,
            ])
            ->all();

        $rows = collect($items)
            ->concat(ReelsHistoryReader::all())
            ->sortByDesc(fn (array $row) => $row['scheduledAt'] ?? '')
            ->values()
            ->all();

        return response()->json(['results' => $rows]);
    }

    /**
     * Hapus hasil dari tabel. Konten database ditandai dismissed (tidak dihapus permanen, supaya tidak dibuat ulang otomatis).
     * Konten yang sedang di-generate atau dipublish tidak bisa dihapus dulu.
     */
    public function deleteResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.source' => ['required', 'in:db,reels'],
            'items.*.id' => ['required', 'string', 'max:64'],
        ]);

        $deleted = 0;
        $skipped = [];
        $reelsIds = [];

        foreach ($data['items'] as $item) {
            if ($item['source'] === 'reels') {
                $reelsIds[] = $item['id'];
                continue;
            }

            $content = OfficeContentItem::find($item['id']);
            if (! $content) {
                continue;
            }
            if (in_array($content->status, ['queued_for_generation', 'generating', 'scheduled_for_publish', 'publishing'], true)) {
                $skipped[] = $content->title ?: $content->id;
                continue;
            }

            $content->update(['status' => 'dismissed']);
            $deleted++;
        }

        ReelsHistoryReader::hide($reelsIds);
        $deleted += count(array_filter($reelsIds, 'is_string'));

        return response()->json(['deleted' => $deleted, 'skipped' => $skipped]);
    }

    /**
     * "Hapus semua" di Hasil Generate: semua konten database dan riwayat Reels disembunyikan.
     * Konten yang sedang diproses tidak ikut, dan jumlahnya dilaporkan.
     */
    public function clearResults(): JsonResponse
    {
        $inFlight = ['queued_for_generation', 'generating', 'scheduled_for_publish', 'publishing'];
        $skipped = OfficeContentItem::whereIn('status', $inFlight)->count();
        $deleted = OfficeContentItem::where('status', '!=', 'dismissed')->whereNotIn('status', $inFlight)->update(['status' => 'dismissed']);

        $reelsIds = collect(ReelsHistoryReader::all())->pluck('id')->filter()->values()->all();
        ReelsHistoryReader::hide($reelsIds);

        return response()->json(['deleted' => $deleted + count($reelsIds), 'skipped' => $skipped]);
    }

    private function validatedSlot(Request $request, bool $required): array
    {
        $rule = $required ? 'required' : 'sometimes';

        return $request->validate([
            'label' => [$rule, 'string', 'max:80'],
            'day' => [$rule, 'integer', 'between:1,7'],
            'time' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'lead_minutes' => ['sometimes', 'integer', 'between:0,1440'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
