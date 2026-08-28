<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $copyable_type
 * @property int $copyable_id
 * @property string|null $ip_hash
 */
#[Fillable(['copyable_type', 'copyable_id', 'ip_hash'])]
class CopyEvent extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function copyable(): MorphTo
    {
        return $this->morphTo();
    }
}
