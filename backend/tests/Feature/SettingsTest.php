<?php

namespace Tests\Feature;

use App\Filament\Resources\YleExamResource;
use App\Filament\Resources\YleSubmissionResource;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->jwtAsAdmin();
    }

    public function test_yle_resources_hidden_by_default(): void
    {
        // YLE mặc định TẮT -> shouldRegisterNavigation = false, canAccess = false.
        $this->assertFalse(YleExamResource::shouldRegisterNavigation());
        $this->assertFalse(YleExamResource::canAccess());
        $this->assertFalse(YleSubmissionResource::shouldRegisterNavigation());
        $this->assertFalse(YleSubmissionResource::canAccess());
    }

    public function test_yle_resources_appear_when_toggle_enabled(): void
    {
        Settings::set('navigation.yle_exams', true);
        Settings::set('navigation.yle_submissions', true);

        $this->assertTrue(YleExamResource::shouldRegisterNavigation());
        $this->assertTrue(YleExamResource::canAccess());
        $this->assertTrue(YleSubmissionResource::shouldRegisterNavigation());
    }

    public function test_settings_page_accessible_by_admin(): void
    {
        // Filament panel dùng guard 'web' + AuthenticateSession; đăng nhập qua
        // guard tường minh để middleware stack chấp nhận session.
        $response = $this->actingAs($this->admin, 'web')
            ->get('/admin/settings');

        $response->assertSuccessful();
    }

    public function test_settings_helper_get_bool_returns_default_when_not_set(): void
    {
        Settings::flush();

        $this->assertTrue(Settings::getBool('navigation.students', true));
        $this->assertFalse(Settings::getBool('navigation.yle_exams', false));
    }
}
