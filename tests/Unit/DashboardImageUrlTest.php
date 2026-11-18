<?php

namespace Tests\Unit;

use App\Models\Assessment\Question;
use App\Models\ExamPaper;
use App\Models\MediaAsset;
use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardImageUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        Storage::forgetDisk('public');

        parent::tearDown();
    }

    public function test_dashboard_profile_photos_and_school_logos_use_the_public_storage_disk(): void
    {
        config(['filesystems.disks.public.url' => 'https://media.example.test/uploads']);
        config(['filesystems.disks.local.url' => 'https://local.example.test']);

        $user = new User(['profile_photo_path' => 'profile-photos/person.jpg']);
        $school = new School(['logo_path' => 'schools/logo.png']);

        $this->assertSame(
            'https://media.example.test/uploads/profile-photos/person.jpg',
            $user->profile_photo_url
        );
        $this->assertSame(
            'https://media.example.test/uploads/schools/logo.png',
            $school->logo_url
        );
    }

    public function test_dashboard_profile_photos_and_school_logos_keep_cloudinary_urls(): void
    {
        $photoUrl = 'https://res.cloudinary.com/demo/image/upload/profile-photos/person.jpg';
        $logoUrl = 'https://res.cloudinary.com/demo/image/upload/school-logos/logo.png';
        $user = new User(['profile_photo_path' => $photoUrl]);
        $school = new School(['logo_path' => $logoUrl]);

        $this->assertSame($photoUrl, $user->profile_photo_url);
        $this->assertSame($logoUrl, $school->logo_url);
    }

    public function test_cloudinary_urls_are_used_for_other_dashboard_image_references(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/gallery/image.png';
        $question = new Question([
            'question_media_disk' => 'cloudinary',
            'question_media_path' => $url,
        ]);
        $examPaper = new ExamPaper(['attachment_path' => $url]);
        $notice = new Notice(['attachment' => $url]);
        $mediaAsset = new MediaAsset(['disk' => 'cloudinary', 'path' => $url]);

        $this->assertSame($url, $question->question_media_url);
        $this->assertSame($url, $examPaper->attachment_url);
        $this->assertSame($url, $notice->attachment_url);
        $this->assertSame($url, $mediaAsset->url);
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,w_1600/v1/gallery/image.png',
            $mediaAsset->optimized_url
        );
    }
}
