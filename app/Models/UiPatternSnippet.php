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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['category', 'title', 'html', 'css'])]
class UiPatternSnippet extends Model
{
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
        return $query->where('category', $category)->orderBy('id');
    }
}
