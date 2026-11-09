<?php

namespace Tests\Feature;

use Tests\TestCase;

class MinimalFastingTermsTest extends TestCase
{
    public function test_terms_are_public_html_and_specific_to_minimal_fasting(): void
    {
        config(['support.minimal_fasting_email' => 'fasting@example.com']);

        $this->get('/minimal-fasting/terms')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('<html lang="en">', false)
            ->assertSee('Minimal Fasting Terms of Service')
            ->assertSee('personal tracking tool')
            ->assertSee('one-time App Store purchase')
            ->assertSee('Android currently has no in-app purchase option.')
            ->assertSee(route('minimal-fasting.privacy', ['lang' => 'en']), false)
            ->assertSee('mailto:fasting@example.com', false);
    }

    public function test_marketing_and_privacy_pages_link_to_the_terms(): void
    {
        foreach (['/minimal-fasting', '/minimal-fasting/privacy'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('minimal-fasting.terms').'"', false);
        }
    }
}
