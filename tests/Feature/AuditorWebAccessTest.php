<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditorWebAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function webRoutes(): array
    {
        return [
            'auditor dashboard'=>['dashboard'],
            'auditor profile'=>['profile.edit'],
            'auditor document checklist'=>['documents.checklist'],
        ];
    }

    #[DataProvider('webRoutes')]
    public function test_api_only_security_auditor_cannot_open_web_pages(string $route): void
    {
        $auditor=User::factory()->securityAuditor()->create();
        $this->actingAs($auditor)->get(route($route))->assertForbidden();
    }
}
