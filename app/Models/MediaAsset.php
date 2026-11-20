<?php

namespace App\Models;

use App\Services\CloudinaryImageStorage;
use App\Support\ContentVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'subject_user_id',
        'usage_area',
        'visibility',
        'title',
        'alt_text',
        'original_name',
        'disk',
        'path',
        'optimized_path',
        'thumbnails',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'width',
        'height',
        'uploaded_by',
        'publication_approved_at',
        'publication_approved_by',
    ];

    protected $casts = [
        'thumbnails' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'publication_approved_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id')->withTrashed();
    }

    public function publicationApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publication_approved_by')->withTrashed();
    }

    public function getUrlAttribute(): string
    {
        if (
            ($this->visibility ?: ContentVisibility::PUBLIC) !== ContentVisibility::PUBLIC
            || ! $this->publication_approved_at
            || $this->disk === 'local'
        ) {
            return route('media-assets.show', $this);
        }

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

        if (! $this->optimized_path) {
            return null;
        }

        return app(CloudinaryImageStorage::class)->url($this->optimized_path, $this->disk ?: 'public');
    }

    public function getPreviewUrlAttribute(): string
    {
        return $this->optimized_url ?: $this->url;
    }
}
