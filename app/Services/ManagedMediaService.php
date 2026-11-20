<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\ContentVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagedMediaService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    private const MAX_BYTES = 25 * 1024 * 1024;

    public function storePrivate(
        UploadedFile $file,
        User $uploader,
        string $usageArea,
        string $visibility = ContentVisibility::PRIVATE,
        ?User $subject = null,
        ?string $title = null,
        ?string $altText = null,
        ?int $maxBytes = null,
    ): MediaAsset {
        if (! in_array($visibility, ContentVisibility::values(), true)) {
            throw ValidationException::withMessages(['media' => 'The selected media visibility is invalid.']);
        }
        if (! $uploader->school_id || ($subject && (int) $subject->school_id !== (int) $uploader->school_id)) {
            throw ValidationException::withMessages(['media' => 'Media subjects must belong to the uploader\'s school.']);
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if (! $extension || (int) $file->getSize() <= 0 || (int) $file->getSize() > ($maxBytes ?: self::MAX_BYTES)) {
            throw ValidationException::withMessages(['media' => 'Upload a supported image, PDF, document, presentation or video within the configured file limit.']);
        }

        $directory = 'community-media/'.$uploader->school_id.'/'.now()->format('Y/m');
        $filename = Str::uuid().'.'.$extension;
        $path = Storage::disk('local')->putFileAs($directory, $file, $filename);
        if (! is_string($path)) {
            throw ValidationException::withMessages(['media' => 'The media file could not be stored.']);
        }

        [$width, $height] = $this->dimensions($file, $mimeType);

        try {
            return DB::transaction(function () use ($uploader, $subject, $usageArea, $visibility, $title, $altText, $file, $path, $mimeType, $width, $height): MediaAsset {
                $asset = MediaAsset::query()->create([
                    'school_id' => $uploader->school_id,
                    'subject_user_id' => $subject?->id,
                    'usage_area' => trim($usageArea) ?: 'general',
                    'visibility' => $visibility,
                    'title' => filled($title) ? trim($title) : null,
                    'alt_text' => filled($altText) ? trim($altText) : null,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'file_size' => (int) $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    'width' => $width,
                    'height' => $height,
                    'uploaded_by' => $uploader->id,
                ]);

                app(AuditTrail::class)->record(
                    (int) $uploader->school_id,
                    'media',
                    'media.uploaded',
                    'A protected community-media asset was uploaded.',
                    $asset,
                    null,
                    ['usage_area' => $asset->usage_area, 'visibility' => $asset->visibility],
                    actor: $uploader,
                    subjectLabel: $asset->original_name,
                );

                return $asset;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function dimensions(UploadedFile $file, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/')) {
            return [null, null];
        }

        $dimensions = @getimagesize($file->getRealPath());

        return is_array($dimensions) ? [(int) $dimensions[0], (int) $dimensions[1]] : [null, null];
    }
}
