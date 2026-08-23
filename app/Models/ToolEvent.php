<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $tool_slug
 * @property string $event_type
 * @property int|null $file_count
 * @property int|null $input_bytes
 * @property int|null $output_bytes
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'tool_slug', 'event_type', 'file_count', 'input_bytes', 'output_bytes', 'ip_hash', 'user_agent'])]
class ToolEvent extends Model
{
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
