<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolResource extends Model
{
    use InSchool, SoftDeletes;

    protected $fillable = ['school_id', 'type', 'name', 'code', 'category', 'author_or_brand', 'location', 'condition', 'quantity', 'available_quantity', 'unit_cost', 'acquired_on', 'notes', 'created_by'];

    protected $casts = ['acquired_on' => 'date', 'unit_cost' => 'decimal:2'];

    public function loans(): HasMany
    {
        return $this->hasMany(SchoolResourceLoan::class);
    }
}
