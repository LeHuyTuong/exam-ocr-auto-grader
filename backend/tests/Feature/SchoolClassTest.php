<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
        $this->teacher = $this->jwtAsTeacher();
    }

    public function test_teacher_can_create_class_and_is_auto_attached(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->teacher))
            ->postJson('/api/classes', [
                'code' => 'TA-202',
                'name' => 'Tiếng Anh 202',
                'level' => 'secondary',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('class.code', 'TA-202')
            ->assertJsonPath('class.level', 'secondary');

        $this->assertDatabaseHas('school_classes', ['code' => 'TA-202']);

        // Newly created class must show up in the creating teacher's own list —
        // otherwise they can't immediately use the class they just made.
        $mine = $this->withHeaders($this->jwtAs($this->teacher))
            ->getJson('/api/classes/mine');

        $mine->assertStatus(200);
        $codes = collect($mine->json('classes'))->pluck('code');
        $this->assertContains('TA-202', $codes);
    }

    public function test_create_class_defaults_level_to_primary(): void
    {
        $response = $this->withHeaders($this->jwtAs($this->teacher))
            ->postJson('/api/classes', [
                'code' => 'TA-303',
                'name' => 'Tiếng Anh 303',
            ]);

        $response->assertStatus(201)->assertJsonPath('class.level', 'primary');
    }

    public function test_create_class_rejects_duplicate_code(): void
    {
        SchoolClass::factory()->create(['code' => 'TA-101']);

        $response = $this->withHeaders($this->jwtAs($this->teacher))
            ->postJson('/api/classes', [
                'code' => 'TA-101',
                'name' => 'Trùng mã lớp',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_creating_class_is_not_auto_attached_as_teacher(): void
    {
        $admin = $this->jwtAsAdmin();

        $response = $this->withHeaders($this->jwtAs($admin))
            ->postJson('/api/classes', [
                'code' => 'TA-404',
                'name' => 'Lớp admin tạo',
            ]);

        $response->assertStatus(201);
        $class = SchoolClass::where('code', 'TA-404')->firstOrFail();
        $this->assertFalse($class->teachers()->where('user_id', $admin->id)->exists());
    }

    public function test_unauthenticated_user_cannot_create_class(): void
    {
        $response = $this->postJson('/api/classes', [
            'code' => 'TA-505',
            'name' => 'Không đăng nhập',
        ]);

        $response->assertStatus(401);
    }
}