<?php

namespace Tests\Feature;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('UstBosh', false);
    }

    public function test_application_can_be_submitted(): void
    {
        $response = $this->post('/ariza', [
            'shop_name' => 'Test Do\'kon',
            'contact_name' => 'Ali Valiyev',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'message' => 'Salom',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('applications', [
            'shop_name' => 'Test Do\'kon',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'email' => 'ali@example.com',
        ]);
    }

    public function test_application_validation_fails_with_bad_phone(): void
    {
        $response = $this->post('/ariza', [
            'shop_name' => 'Test',
            'contact_name' => 'Ali',
            'phone' => 'abc',
        ]);

        $response->assertSessionHasErrors(['phone']);
    }

    public function test_locale_switch_works(): void
    {
        $response = $this->get('/til/ru');

        $response->assertRedirect();
        $this->followRedirects($this->get('/'))
            ->assertSee('Возможности', false);
    }

    public function test_locale_switch_rejects_unknown_language(): void
    {
        $this->get('/til/fr')->assertStatus(404);
    }

    public function test_application_locale_persists_from_session(): void
    {
        $this->withSession(['locale' => 'ru'])->post('/ariza', [
            'shop_name' => 'Test Do\'kon',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
        ])->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'email' => 'ali@example.com',
            'locale' => 'ru',
        ]);
    }

    public function test_rejected_application_can_be_resubmitted(): void
    {
        Application::create([
            'shop_name' => 'Eski Do\'kon',
            'contact_name' => 'Ali',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
            'phone_normalized' => '998901234567',
            'contact_key' => 'ali@example.com|998901234567',
            'status' => Application::STATUS_REJECTED,
        ]);

        $response = $this->post('/ariza', [
            'shop_name' => 'Yangi Do\'kon',
            'contact_name' => 'Ali Valiyev',
            'email' => 'ali@example.com',
            'phone' => '+998901234567',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('applications', 1);

        $application = Application::first();
        $this->assertEquals(Application::STATUS_PENDING, $application->status);
        $this->assertEquals('Yangi Do\'kon', $application->shop_name);
        $this->assertNull($application->rejection_reason);
    }
}
