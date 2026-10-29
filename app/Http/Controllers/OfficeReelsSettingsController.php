<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class OfficeReelsSettingsController extends Controller
{
    private const DURATIONS = [20, 25, 30, 35, 40];
    private const SPEEDS = [1.0, 1.1, 1.2];

    public function show(): JsonResponse
    {
        return response()->json(['settings' => self::current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'videos_per_week' => ['required', 'integer', 'between:1,7'],
            'durations' => ['required', 'array', 'min:1', 'max:5', 'distinct'],
            'durations.*' => ['integer', 'in:20,25,30,35,40'],
            'speed_choices' => ['required', 'array', 'min:1', 'max:3', 'distinct'],
            'speed_choices.*' => ['numeric', function (string $attribute, mixed $value, \Closure $fail) {
                if (! in_array(round((float) $value, 2), self::SPEEDS, true)) {
                    $fail('Kecepatan hanya boleh 1.0, 1.1, atau 1.2.');
                }
            }],
            'skip_head_s' => ['required', 'integer', 'between:0,15'],
            'skip_tail_s' => ['required', 'integer', 'between:0,15'],
            'music_moods' => ['required', 'array', 'min:1', 'max:6', 'distinct'],
            'music_moods.*' => ['string', 'regex:/^[a-z_]{2,20}$/'],
            'music_volume' => ['required', 'numeric', 'between:0,1'],
            'brand_hashtags' => ['required', 'array', 'max:5'],
            'brand_hashtags.*' => ['string', 'regex:/^#[a-z0-9_]{2,30}$/'],
            'services' => ['required', 'array', 'min:1', 'max:10'],
            'services.*' => ['string', 'min:3', 'max:80'],
        ]);

        $settings = [
            'videos_per_week' => (int) $validated['videos_per_week'],
            'durations' => array_map('intval', array_values($validated['durations'])),
            'speed_choices' => array_map(fn ($speed) => round((float) $speed, 2), array_values($validated['speed_choices'])),
            'skip_head_s' => (int) $validated['skip_head_s'],
            'skip_tail_s' => (int) $validated['skip_tail_s'],
            'music_moods' => array_values($validated['music_moods']),
            'music_volume' => round((float) $validated['music_volume'], 2),
            'brand_hashtags' => array_values($validated['brand_hashtags']),
            'services' => array_values($validated['services']),
        ];

        self::write($settings);

        return response()->json(['settings' => $settings]);
    }

    public static function defaults(): array
    {
        return [
            'videos_per_week' => 4,
            'durations' => [20, 25, 30, 35, 40],
            'speed_choices' => [1.0, 1.1, 1.2],
            'skip_head_s' => 5,
            'skip_tail_s' => 5,
            'music_moods' => ['jj', 'pop_indonesia', 'chill'],
            'music_volume' => 0.25,
            'brand_hashtags' => ['#jaukiacademy'],
            'services' => [
                'keluh kesah ngerjain tugas kuliah',
                'keluh kesah hitung SPSS dan skripsi',
                'keluh kesah coding dan debugging',
                'motivasi di dunia perkuliahan',
                'curhat soal dosen dan revisi skripsi',
            ],
        ];
    }

    public static function current(): array
    {
        $path = config('reels.settings_path');
        if (! File::exists($path)) {
            return self::defaults();
        }

        $stored = json_decode(File::get($path), true);

        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    private static function write(array $settings): void
    {
        $path = config('reels.settings_path');
        File::ensureDirectoryExists(dirname($path));

        // Tulis ke file sementara lalu pindahkan, supaya bot tidak pernah membaca file yang setengah jadi.
        $tmp = $path.'.tmp';
        File::put($tmp, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        File::move($tmp, $path);
    }
}
