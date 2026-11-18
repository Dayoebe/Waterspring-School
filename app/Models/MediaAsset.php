<?php

namespace App\Models;

use App\Services\CloudinaryImageStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'usage_area',
        'title',
        'alt_text',
        'disk',
        'path',
        'optimized_path',
        'thumbnails',
        'mime_type',
        'file_size',
        'width',
        'height',
        'uploaded_by',
    ];

    protected $casts = [
        'thumbnails' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return app(CloudinaryImageStorage::class)->url($this->path, $this->disk ?: 'public') ?? '';
    }

    public function getOptimizedUrlAttribute(): ?string
    {
        if ($this->disk === 'cloudinary' && is_string($this->path)) {
            return app(CloudinaryImageStorage::class)->transformedUrl(
                $this->path,
                'f_auto,q_auto,w_1600'
            );
        }

        if (!$this->optimized_path) {
            return null;
        }

        return app(CloudinaryImageStorage::class)->url($this->optimized_path, $this->disk ?: 'public');
    }

    public function getPreviewUrlAttribute(): string
    {
        return $this->optimized_url ?: $this->url;
    }
}
