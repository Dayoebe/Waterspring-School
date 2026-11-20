<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPortfolioVersion extends Model
{
    protected $fillable = ['student_portfolio_item_id', 'media_asset_id', 'version_number', 'change_summary', 'created_by'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StudentPortfolioItem::class, 'student_portfolio_item_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
