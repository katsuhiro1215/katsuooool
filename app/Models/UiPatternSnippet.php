<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $category
 * @property string $title
 * @property string $html
 * @property string $css
 * @property bool $is_premium
 * @property int $copy_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['category', 'title', 'html', 'css', 'is_premium', 'copy_count'])]
class UiPatternSnippet extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_premium' => false,
        'copy_count' => 0,
    ];

    /**
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'button',
        'card',
        'pagination',
        'modal',
        'toast',
        'loading',
        'skeleton',
        'form-input',
        'form-textarea',
        'form-check',
        'form-select',
        'form-group',
        'header',
        'footer',
    ];

    /**
     * @param  Builder<UiPatternSnippet>  $query
     * @return Builder<UiPatternSnippet>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderByDesc('copy_count')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
        ];
    }
}
