<?php

namespace App\Http\Controllers;

use App\Models\AdmissionDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionDocumentController extends Controller
{
    public function __invoke(AdmissionDocument $document): StreamedResponse
    {
        abort_unless((int) $document->school_id === (int) auth()->user()?->school_id, 404);
        abort_unless(auth()->user()?->can('read admission registration'), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
