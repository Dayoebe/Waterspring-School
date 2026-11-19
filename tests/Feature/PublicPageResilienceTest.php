<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use Illuminate\Database\QueryException;
use RuntimeException;
use Tests\TestCase;

class PublicPageResilienceTest extends TestCase
{
    public function test_homepage_uses_an_empty_optional_content_state_when_the_database_is_unavailable(): void
    {
        $controller = new class extends PageController
        {
            protected function publicClubs()
            {
                throw new QueryException(
                    'mysql',
                    'select optional public content',
                    [],
                    new RuntimeException('Database unavailable')
                );
            }
        };

        $view = $controller->home();

        $this->assertSame('livewire.site.home', $view->name());
        $this->assertCount(0, $view->getData()['clubs']);
    }
}
