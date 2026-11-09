<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PublicUxMarkupTest extends TestCase
{
    public function test_desktop_school_name_can_wrap_without_losing_its_full_accessible_value(): void
    {
        $header = file_get_contents(__DIR__.'/../../resources/views/partials/header.blade.php');

        $this->assertStringContainsString('title="{{ $schoolName }}"', $header);
        $this->assertStringContainsString('lg:line-clamp-2', $header);
        $this->assertStringContainsString('lg:whitespace-normal', $header);
    }

    public function test_mobile_install_control_uses_a_compact_icon_with_an_accessible_label(): void
    {
        $partial = file_get_contents(__DIR__.'/../../resources/views/partials/pwa-register.blade.php');

        $this->assertStringContainsString('@media (max-width: 639px)', $partial);
        $this->assertStringContainsString('.pwa-install-label', $partial);
        $this->assertStringContainsString("installButton.setAttribute('aria-label', 'Install app')", $partial);
        $this->assertStringContainsString("installIcon.className = 'fas fa-download'", $partial);
        $this->assertStringNotContainsString("installButton.textContent = 'Install App'", $partial);
    }
}
