<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviourReportMessage extends Model
{
    use HasFactory;

    protected $fillable = ['behaviour_report_id', 'author_id', 'body', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function report(): BelongsTo { return $this->belongsTo(BehaviourReport::class, 'behaviour_report_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
