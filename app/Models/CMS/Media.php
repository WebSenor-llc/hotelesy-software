<?php

namespace App\Models\CMS;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'cms_media';
    protected $guarded = ['id'];
    protected $casts = [
        'size_bytes' => 'integer',
        'focal_point' => 'array',
        'width' => 'integer',
        'height' => 'integer',
    ];
}
