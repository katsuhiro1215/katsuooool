<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteStarterSubmission extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'industry',
        'prefecture',
        'target_age_range',
        'target_gender',
        'purpose',
    ];
}
