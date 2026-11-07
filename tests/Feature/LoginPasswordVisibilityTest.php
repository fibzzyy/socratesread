<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPasswordVisibilityTest extends TestCase
{
    public function test_login_form_has_password_visibility_toggle(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('id="password"', false)
            ->assertSee('type="button"', false)
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee('aria-label', false)
            ->assertSee('password.type = password.type === \'password\' ? \'text\' : \'password\'', false);
    }
}
