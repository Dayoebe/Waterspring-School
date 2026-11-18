<?php

namespace Tests\Unit;

use App\Services\CloudinaryImageStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CloudinaryImageStorageTest extends TestCase
{
    public function test_it_uploads_an_image_to_the_requested_cloudinary_folder(): void
    {
        config(['services.cloudinary.url' => 'cloudinary://12345:secret@demo']);
        Http::fake([
            'https://api.cloudinary.com/v1_1/demo/image/upload' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/waterspring/profile-photos/avatar.png',
            ]),
        ]);
        $file = new UploadedFile(
            base_path('public/images/watersprings/logo.png'),
            'avatar.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        );

        $url = app(CloudinaryImageStorage::class)->upload($file, 'waterspring/profile-photos/school-7');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/v1/waterspring/profile-photos/avatar.png',
            $url
        );
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.cloudinary.com/v1_1/demo/image/upload'
            && $request->method() === 'POST'
            && ($request->data()['folder'] ?? null) === 'waterspring/profile-photos/school-7'
            && isset($request->data()['signature'])
        );
    }

    public function test_it_throws_when_cloudinary_credentials_are_missing(): void
    {
        config(['services.cloudinary.url' => '']);
        $file = new UploadedFile(
            base_path('public/images/watersprings/logo.png'),
            'avatar.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or invalid CLOUDINARY_URL');

        app(CloudinaryImageStorage::class)->upload($file, 'waterspring/profile-photos');
    }
}
