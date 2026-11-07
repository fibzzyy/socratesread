<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegisterPasswordVisibilityTest extends TestCase
{
    public function test_register_form_has_password_visibility_toggles(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('id="password"', false)
            ->assertSee('id="password_confirmation"', false)
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee("field.input.type = field.input.type === 'password' ? 'text' : 'password'", false);
    }
}
