<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submit_returns_json_for_ajax(): void
    {
        $response = $this->post('/ariza', [
            'shop_name' => 'AJAX Do\'kon',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('applications', ['shop_name' => "AJAX Do'kon"]);
    }

    public function test_application_ajax_validation_returns_json_errors(): void
    {
        $response = $this->post('/ariza', [
            'shop_name' => '',
            'phone' => 'abc',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['shop_name', 'phone', 'email']);
    }

    public function test_admin_login_returns_json_for_ajax(): void
    {
        User::factory()->create([
            'email' => 'admin_x@ustbosh.uz',
            'password' => 'pass_x1',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $response = $this->post('/admin/login', [
            'login' => 'admin_x@ustbosh.uz',
            'password' => 'pass_x1',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['redirect']);
    }

    public function test_duplicate_email_returns_duplicate_flag(): void
    {
        // Birinchi ariza
        $this->post('/ariza', [
            'shop_name' => 'Birinchi',
            'contact_name' => 'Ali',
            'email' => 'dup@example.com',
            'phone' => '+998901234567',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        // Ikkinchi ariza — xuddi shu email
        $response = $this->post('/ariza', [
            'shop_name' => 'Ikkinchi',
            'contact_name' => 'Ali',
            'email' => 'dup@example.com',
            'phone' => '+998901234567',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'duplicate' => true,
        ]);

        // DB da faqat bitta ariza bo'lishi kerak
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_duplicate_phone_ignores_formatting(): void
    {
        $this->post('/ariza', [
            'shop_name' => 'Birinchi',
            'contact_name' => 'Ali',
            'email' => 'first@example.com',
            'phone' => '+998-123-45-67',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response = $this->post('/ariza', [
            'shop_name' => 'Ikkinchi',
            'contact_name' => 'Vali',
            'email' => 'second@example.com',
            'phone' => '+9981234567',
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);

        $response->assertOk()->assertJson([
            'success' => true,
            'duplicate' => true,
        ]);

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_admin_approve_returns_json_for_ajax(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $application = Application::create([
            'shop_name' => 'Test',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
        ]);

        $this->post('/admin/login', [
            'login' => 'admin_test@ustbosh.uz',
            'password' => 'secret123',
        ]);

        $response = $this->post(
            route('admin.applications.approve', $application),
            [],
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => Application::STATUS_APPROVED,
            'application_id' => $application->id,
        ]);
        $response->assertJsonMissing(['credentials']);
    }
}
