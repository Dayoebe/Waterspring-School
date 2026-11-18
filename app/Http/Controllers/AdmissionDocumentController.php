<?php

namespace App\Http\Controllers;

use App\Models\AdmissionDocument;
use App\Services\CloudinaryImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionDocumentController extends Controller
{
    public function __invoke(AdmissionDocument $document): StreamedResponse|RedirectResponse
    {
        abort_unless((int) $document->school_id === (int) auth()->user()?->school_id, 404);
        abort_unless(auth()->user()?->can('read admission registration'), 403);
        if ($document->disk === 'cloudinary' && app(CloudinaryImageStorage::class)->isCloudinaryUrl($document->path)) {
            return redirect()->away($document->path);
        }
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
