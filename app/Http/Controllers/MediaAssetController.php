<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Services\CloudinaryImageStorage;
use App\Services\MediaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAssetController extends Controller
{
    public function __invoke(Request $request, MediaAsset $mediaAsset, MediaAccessService $access): StreamedResponse|RedirectResponse
    {
        abort_unless($access->canView($request->user(), $mediaAsset), 404);

        if ($mediaAsset->disk === 'cloudinary' && app(CloudinaryImageStorage::class)->isCloudinaryUrl($mediaAsset->path)) {
            return redirect()->away($mediaAsset->path);
        }

        $disk = Storage::disk($mediaAsset->disk ?: 'local');
        abort_unless($disk->exists($mediaAsset->path), 404);

        return $disk->response(
            $mediaAsset->path,
            $mediaAsset->original_name ?: basename($mediaAsset->path),
            ['Content-Type' => $mediaAsset->mime_type ?: 'application/octet-stream'],
        );
    }
}
