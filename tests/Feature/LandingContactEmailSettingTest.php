<?php

namespace Tests\Feature;

use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingContactEmailSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_contact_email_is_shown_in_landing_footer(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_LANDING_CONTACT_EMAIL => 'team@example.com',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('mailto:team@example.com', false);
    }

    public function test_contact_email_is_hidden_when_setting_is_empty(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_LANDING_CONTACT_EMAIL => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-label="البريد الإلكتروني"', false)
            ->assertDontSee('mailto:contact@khatma.app', false);
    }

    public function test_custom_x_url_is_shown_in_landing_footer(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_LANDING_X_URL => 'https://x.com/example_account',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="https://x.com/example_account"', false)
            ->assertSee('@example_account');
    }

    public function test_x_url_is_hidden_when_setting_is_empty(): void
    {
        AppSettings::setMany([
            AppSettings::KEY_LANDING_X_URL => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-label="حساب X"', false)
            ->assertDontSee('@khatma_app');
    }
}
