<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeContentRevision extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(OfficeContentItem::class, 'content_item_id');
    }
}
