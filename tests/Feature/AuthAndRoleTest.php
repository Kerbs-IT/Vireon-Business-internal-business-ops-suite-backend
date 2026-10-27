<?php

namespace Tests\Feature;

use App\Models\Leave_requests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->employee()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['email', 'token']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->employee()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422); // validation error from your AuthController
    }

    public function test_employee_cannot_approve_leave_request(): void
    {
        $employee = User::factory()->employee()->create();

        $leaveRequest = Leave_requests::factory()->create([
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->putJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(403);
    }

    public function test_manager_can_approve_leave_request(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $leaveRequest = Leave_requests::factory()->create([
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->putJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(200);
    }

    public function test_employee_cannot_create_department(): void
    {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/departments', [
                'name' => 'New Department',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_department(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/departments', [
                'name' => 'New Department',
            ]);

        $response->assertStatus(201); // or 200, depending what your stub returns right now
    }
}
