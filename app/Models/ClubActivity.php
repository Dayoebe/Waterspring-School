<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubActivity extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'status', 'created_by'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
