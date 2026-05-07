<?php

namespace App\Models\CMS;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'cms_pages';
    protected $guarded = ['id'];
    protected $casts = [
        'blocks' => 'array',
        'seo_meta' => 'array',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
