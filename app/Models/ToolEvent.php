<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tool_slug
 * @property string $event_type
 * @property int|null $file_count
 * @property int|null $input_bytes
 * @property int|null $output_bytes
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['tool_slug', 'event_type', 'file_count', 'input_bytes', 'output_bytes', 'ip_hash', 'user_agent'])]
class ToolEvent extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
