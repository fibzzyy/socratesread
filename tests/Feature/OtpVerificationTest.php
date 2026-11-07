<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_valid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'verif@example.test',
            'role' => 'student',
            'email_verified_at' => null,
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        $this->withSession(['pending_user_id' => $user->id])
            ->post(route('verification.otp.submit'), [
                'otp' => '123456',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->otp_code);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_verify_with_wrong_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'verif2@example.test',
            'role' => 'student',
            'email_verified_at' => null,
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        $this->withSession(['pending_user_id' => $user->id])
            ->post(route('verification.otp.submit'), [
                'otp' => '654321',
            ])
            ->assertSessionHasErrors('otp');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_user_can_resend_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'resend@example.test',
            'role' => 'student',
            'email_verified_at' => null,
            'otp_code' => '111111',
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        $this->withSession(['pending_user_id' => $user->id])
            ->post(route('verification.otp.resend'))
            ->assertSessionHas('status');

        $this->assertNotEquals('111111', $user->fresh()->otp_code);
    }

    public function test_unverified_user_can_reregister_without_unique_email_error(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'unverified@example.test',
            'role' => 'student',
            'email_verified_at' => null,
            'otp_code' => '111111',
        ]);

        $this->post(route('register'), [
            'name' => 'Nama Baru',
            'email' => 'unverified@example.test',
            'role' => 'student',
            'school_origin' => 'SMA Baru',
            'nisn' => '0099887766',
            'entry_grade' => 10,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'unverified@example.test',
            'name' => 'Nama Baru',
            'school_origin' => 'SMA Baru',
        ]);
    }
}
