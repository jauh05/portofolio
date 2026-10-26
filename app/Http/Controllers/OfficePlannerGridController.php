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
