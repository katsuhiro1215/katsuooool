<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property array<int, string> $colors
 * @property int|null $angle
 * @property int $copy_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['type', 'title', 'colors', 'angle', 'copy_count'])]
class ColorExample extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'copy_count' => 0,
    ];

    /**
     * @var array<int, string>
     */
    public const TYPES = ['palette', 'gradient', 'scheme'];

    /**
     * @param  Builder<ColorExample>  $query
     * @return Builder<ColorExample>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderByDesc('copy_count')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colors' => 'array',
        ];
    }
}
