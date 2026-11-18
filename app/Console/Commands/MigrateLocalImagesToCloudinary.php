<?php

namespace App\Console\Commands;

use App\Models\AdmissionDocument;
use App\Models\Assessment\Question;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ExamPaper;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\Notice;
use App\Models\School;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CloudinaryImageStorage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class MigrateLocalImagesToCloudinary extends Command
{
    protected $signature = 'cloudinary:migrate-local-images
                            {--execute : Upload local images and update their database references}';

    protected $description = 'Find local image uploads and optionally migrate them to Cloudinary';

    private int $candidateCount = 0;

    private bool $executing = false;

    /** @var array<string, string> */
    private array $uploadedUrls = [];

    public function handle(CloudinaryImageStorage $storage): int
    {
        $this->executing = (bool) $this->option('execute');

        $this->info($this->executing
            ? 'Migrating local image uploads to Cloudinary; local copies will be kept.'
            : 'Dry run: scanning for local image uploads. No files or database rows will be changed.');

        $this->migrateRecords(
            User::query()->whereNotNull('profile_photo_path'),
            'profile_photo_path',
            'public',
            fn (User $user): string => $storage->folder('profile-photos', $user->school_id)
        );
        $this->migrateRecords(
            School::query()->whereNotNull('logo_path'),
            'logo_path',
            'public',
            fn (School $school): string => $storage->folder('school-logos', (int) $school->id)
        );
        $this->migrateSettings();
        $this->migrateRecords(
            GalleryItem::query()->whereNotNull('media_url'),
            'media_url',
            'public',
            fn (GalleryItem $item): string => $storage->folder('gallery', $item->school_id)
        );
        $this->migrateRecords(
            MediaAsset::query()->whereNotNull('path')->where(function (Builder $query): void {
                $query->whereNull('disk')->orWhere('disk', '!=', 'cloudinary');
            }),
            'path',
            'public',
            fn (MediaAsset $asset): string => $storage->folder(
                'media-library/' . (int) $asset->school_id . '/' . ($asset->created_at?->format('Y/m') ?? now()->format('Y/m'))
            ),
            'disk',
            true
        );
        $this->migrateRecords(
            Question::query()->whereNotNull('question_media_path')->where(function (Builder $query): void {
                $query->whereNull('question_media_disk')->orWhere('question_media_disk', '!=', 'cloudinary');
            }),
            'question_media_path',
            'public',
            fn (): string => $storage->folder('cbt-questions'),
            'question_media_disk'
        );
        $this->migrateRecords(
            ExamPaper::query()->whereNotNull('attachment_path'),
            'attachment_path',
            'public',
            fn (ExamPaper $paper): string => $storage->folder(
                'exam-papers',
                $paper->exam?->semester?->school_id
            )
        );
        $this->migrateRecords(
            Notice::query()->whereNotNull('attachment'),
            'attachment',
            'public',
            fn (Notice $notice): string => $storage->folder('notices', $notice->school_id)
        );
        $this->migrateRecords(
            Assignment::query()->whereNotNull('attachment_path'),
            'attachment_path',
            'local',
            fn (Assignment $assignment): string => $storage->folder('assignments', $assignment->school_id)
        );
        $this->migrateRecords(
            AssignmentSubmission::query()->whereNotNull('attachment_path'),
            'attachment_path',
            'local',
            fn (AssignmentSubmission $submission): string => $storage->folder(
                'assignment-submissions',
                $submission->assignment?->school_id
            )
        );
        $this->migrateRecords(
            AdmissionDocument::query()->whereNotNull('path')->where('disk', '!=', 'cloudinary'),
            'path',
            'local',
            fn (AdmissionDocument $document): string => $storage->folder(
                'admissions/' . (int) $document->admission_registration_id . '/documents',
                (int) $document->school_id
            ),
            'disk'
        );

        $this->newLine();
        $this->info(($this->executing ? 'Migrated' : 'Found') . ' ' . $this->candidateCount . ' local image(s).');
        if (!$this->executing) {
            $this->comment('Run this command with --execute to upload the images and update their references.');
        }

        return self::SUCCESS;
    }

    private function migrateRecords(
        Builder $query,
        string $pathField,
        string $disk,
        callable $folderFor,
        ?string $diskField = null,
        bool $clearDerivedMedia = false
    ): void {
        $query->chunkById(100, function ($records) use ($pathField, $disk, $folderFor, $diskField, $clearDerivedMedia): void {
            foreach ($records as $record) {
                $reference = $record->getAttribute($pathField);
                $localPath = $this->localImagePath(is_string($reference) ? $reference : null, $disk);
                if (!$localPath) {
                    continue;
                }

                $folder = $folderFor($record);
                $this->candidateCount++;
                $this->line(($this->executing ? 'Migrating ' : 'Would migrate ')
                    . class_basename($record) . '#' . $record->getKey() . ' into ' . $folder);

                if (!$this->executing) {
                    continue;
                }

                $record->setAttribute(
                    $pathField,
                    $this->uploadLocalImage($localPath, $disk, $folder)
                );
                if ($diskField !== null) {
                    $record->setAttribute($diskField, 'cloudinary');
                }
                if ($clearDerivedMedia && $record instanceof MediaAsset) {
                    $record->optimized_path = null;
                    $record->thumbnails = null;
                }

                $record->save();
            }
        });
    }

    private function migrateSettings(): void
    {
        SiteSetting::query()->chunkById(100, function ($settings): void {
            foreach ($settings as $setting) {
                $updated = false;
                foreach (['settings', 'draft_settings'] as $column) {
                    $payload = $setting->getAttribute($column);
                    if (!is_array($payload) || !is_array($payload['theme'] ?? null)) {
                        continue;
                    }

                    foreach (['logo_url' => 'logos', 'favicon_url' => 'favicons'] as $key => $area) {
                        $reference = $payload['theme'][$key] ?? null;
                        if (!is_string($reference)) {
                            continue;
                        }

                        $localPath = $this->localImagePath($reference, 'public');
                        if (!$localPath) {
                            continue;
                        }

                        $scopeFolder = $setting->school_id
                            ? 'school-' . $setting->school_id
                            : 'general';
                        $folder = app(CloudinaryImageStorage::class)
                            ->folder('site-settings/' . $scopeFolder . '/' . $area);
                        $this->candidateCount++;
                        $this->line(($this->executing ? 'Migrating ' : 'Would migrate ')
                            . 'SiteSetting#' . $setting->getKey() . ' ' . $key . ' into ' . $folder);

                        if ($this->executing) {
                            $payload['theme'][$key] = $this->uploadLocalImage($localPath, 'public', $folder);
                            $setting->setAttribute($column, $payload);
                            $updated = true;
                        }
                    }
                }

                if ($updated) {
                    $setting->save();
                }
            }
        });
    }

    private function localImagePath(?string $reference, string $disk): ?string
    {
        if (!$reference || app(CloudinaryImageStorage::class)->isCloudinaryUrl($reference)) {
            return null;
        }

        $path = $reference;
        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            $path = (string) parse_url($reference, PHP_URL_PATH);
        }
        if ($path === '') {
            return null;
        }

        $path = rawurldecode($path);
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (str_starts_with($path, '/')) {
            return null;
        }

        $storageDisk = Storage::disk($disk);
        if (!$storageDisk->exists($path)) {
            return null;
        }

        $absolutePath = $storageDisk->path($path);

        return app(CloudinaryImageStorage::class)->isImageFile($absolutePath)
            ? $absolutePath
            : null;
    }

    private function uploadLocalImage(string $path, string $disk, string $folder): string
    {
        $cacheKey = $disk . ':' . $path . ':' . $folder;
        if (isset($this->uploadedUrls[$cacheKey])) {
            return $this->uploadedUrls[$cacheKey];
        }

        return $this->uploadedUrls[$cacheKey] = app(CloudinaryImageStorage::class)->uploadFile(
            $path,
            basename($path),
            $folder
        );
    }
}
