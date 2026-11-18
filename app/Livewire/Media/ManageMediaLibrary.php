<?php

namespace App\Livewire\Media;

use App\Models\MediaAsset;
use App\Services\CloudinaryImageStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ManageMediaLibrary extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $usageFilter = 'all';

    public int $perPage = 12;

    public string $usageArea = 'general';

    public string $title = '';

    public string $altText = '';

    public $mediaFile = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'usageFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage media library'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingUsageFilter(): void
    {
        $this->resetPage();
    }

    public function uploadAsset(): void
    {
        $this->validate([
            'usageArea' => ['required', 'in:general,home,about,gallery,seo'],
            'title' => ['nullable', 'string', 'max:255'],
            'altText' => ['nullable', 'string', 'max:255'],
            'mediaFile' => ['required', 'file', 'max:15360', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,application/pdf'],
        ]);

        $schoolId = (int) auth()->user()?->school_id;
        if ($schoolId <= 0) {
            $this->addError('mediaFile', 'Select a school context before uploading media.');
            return;
        }

        $mimeType = (string) ($this->mediaFile->getMimeType() ?: 'application/octet-stream');
        $fileSize = (int) ($this->mediaFile->getSize() ?: 0);
        $optimizedPath = null;
        $thumbnails = [];
        $width = null;
        $height = null;
        $storage = app(CloudinaryImageStorage::class);

        if (str_starts_with($mimeType, 'image/')) {
            $disk = 'cloudinary';
            $storedPath = $storage->upload(
                $this->mediaFile,
                $storage->folder('media-library/' . $schoolId . '/' . now()->format('Y/m'))
            );
            $imageSize = @getimagesize($this->mediaFile->getRealPath());
            if (is_array($imageSize) && count($imageSize) >= 2) {
                $width = (int) $imageSize[0];
                $height = (int) $imageSize[1];
            }
        } else {
            $disk = 'public';
            $storedPath = $this->mediaFile->store(
                'media-library/' . $schoolId . '/' . now()->format('Y/m'),
                $disk
            );
        }

        MediaAsset::query()->create([
            'school_id' => $schoolId,
            'usage_area' => $this->usageArea,
            'title' => trim($this->title) ?: null,
            'alt_text' => trim($this->altText) ?: null,
            'disk' => $disk,
            'path' => $storedPath,
            'optimized_path' => $optimizedPath,
            'thumbnails' => $thumbnails !== [] ? $thumbnails : null,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => auth()->id(),
        ]);

        $this->reset(['title', 'altText', 'mediaFile']);
        $this->usageArea = 'general';

        session()->flash('success', 'Media asset uploaded successfully.');
    }

    public function deleteAsset(int $assetId): void
    {
        $asset = MediaAsset::query()
            ->where('school_id', auth()->user()?->school_id)
            ->findOrFail($assetId);

        if ($asset->disk !== 'cloudinary') {
            Storage::disk($asset->disk ?: 'public')->delete($asset->path);

            if ($asset->optimized_path) {
                Storage::disk($asset->disk ?: 'public')->delete($asset->optimized_path);
            }

            if (is_array($asset->thumbnails)) {
                foreach ($asset->thumbnails as $thumbPath) {
                    if (is_string($thumbPath) && $thumbPath !== '') {
                        Storage::disk($asset->disk ?: 'public')->delete($thumbPath);
                    }
                }
            }
        }

        $asset->delete();

        session()->flash('success', 'Media asset deleted.');
    }

    public function render()
    {
        $assets = MediaAsset::query()
            ->where('school_id', auth()->user()?->school_id)
            ->when($this->usageFilter !== 'all', function ($query): void {
                $query->where('usage_area', $this->usageFilter);
            })
            ->when($this->search !== '', function ($query): void {
                $search = '%' . trim($this->search) . '%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', $search)
                        ->orWhere('alt_text', 'like', $search)
                        ->orWhere('path', 'like', $search);
                });
            })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.media.manage-media-library', [
            'assets' => $assets,
        ])
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('media-library.index'), 'text' => 'Media Library', 'active' => true],
                ],
            ])
            ->title('Media Library');
    }
}
