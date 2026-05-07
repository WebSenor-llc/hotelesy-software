<?php

namespace App\Models\Housekeeping;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostFound extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'housekeeping_lost_found';
    protected $guarded = ['id'];
    protected $casts = [
        'found_date' => 'date',
        'returned_date' => 'date',
    ];

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function foundBy(): BelongsTo { return $this->belongsTo(User::class, 'found_by'); }
}
