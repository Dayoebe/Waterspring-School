<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaServiceWorkerPrivacyTest extends TestCase
{
    private string $serviceWorker;

    private string $publicHtaccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceWorker = file_get_contents(__DIR__.'/../../public/service-worker.js');
        $this->publicHtaccess = file_get_contents(__DIR__.'/../../public/.htaccess');
    }

    public function test_only_explicit_public_documents_are_eligible_for_page_caching(): void
    {
        $this->assertStringContainsString('const PUBLIC_DOCUMENT_PATHS = new Set([', $this->serviceWorker);

        foreach (['/', '/about', '/academics', '/contact', '/admission', '/gallery', '/team'] as $publicPath) {
            $this->assertStringContainsString("'{$publicPath}'", $this->serviceWorker);
        }

        foreach (['/login', '/register', '/dashboard', '/results', '/fees', '/support', '/cbt', '/livewire'] as $privatePath) {
            $this->assertStringNotContainsString("'{$privatePath}'", $this->serviceWorker);
        }

        $this->assertStringContainsString('if (!isPublicDocument(requestUrl)) {', $this->serviceWorker);
        $this->assertStringNotContainsString('pageCache.put(request,', $this->serviceWorker);
    }

    public function test_public_page_cache_is_anonymous_and_install_path_aware(): void
    {
        $this->assertStringContainsString('self.registration.scope', $this->serviceWorker);
        $this->assertStringContainsString("credentials: 'omit'", $this->serviceWorker);
        $this->assertStringContainsString('refreshPublicDocumentCache(cacheKey)', $this->serviceWorker);
        $this->assertStringContainsString('pageCache.put(cacheKey, response.clone())', $this->serviceWorker);
    }

    public function test_static_caching_uses_approved_directories_instead_of_any_file_extension(): void
    {
        foreach (['/build/', '/images/', '/img/', '/icons/'] as $assetPath) {
            $this->assertStringContainsString("'{$assetPath}'", $this->serviceWorker);
        }

        $this->assertStringNotContainsString('STATIC_ASSET_PATTERN', $this->serviceWorker);
        $this->assertStringContainsString("const publicAssetRequest = new Request(request, { credentials: 'omit' });", $this->serviceWorker);
        $this->assertStringContainsString('key.startsWith(CACHE_PREFIX)', $this->serviceWorker);
    }

    public function test_apache_cannot_reuse_a_stale_service_worker_script(): void
    {
        $this->assertStringContainsString('<Files "service-worker.js">', $this->publicHtaccess);
        $this->assertStringContainsString(
            'Cache-Control "no-store, no-cache, must-revalidate, max-age=0"',
            $this->publicHtaccess
        );
        $this->assertStringNotContainsString('Service-Worker-Allowed', $this->publicHtaccess);
    }
}
