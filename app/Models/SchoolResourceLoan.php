<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolResourceLoan extends Model
{
    protected $fillable = ['school_resource_id', 'borrower_id', 'quantity', 'issued_at', 'due_at', 'returned_at', 'condition_on_return', 'notes', 'issued_by', 'received_by'];

    protected $casts = ['issued_at' => 'datetime', 'due_at' => 'datetime', 'returned_at' => 'datetime'];

    public function resource(): BelongsTo { return $this->belongsTo(SchoolResource::class, 'school_resource_id'); }
    public function borrower(): BelongsTo { return $this->belongsTo(User::class, 'borrower_id'); }
}
