<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShiftCalTermsTest extends TestCase
{
    public function test_terms_are_public_and_include_privacy_and_subscription_guidance(): void
    {
        $this->get('/shiftcal/terms')
            ->assertOk()
            ->assertSee('<html lang="tr">', false)
            ->assertSee('Kullanım Koşulları')
            ->assertSee('mağaza aboneliğini otomatik olarak iptal etmez')
            ->assertSee(route('shiftcal.privacy'), false)
            ->assertSee('https://www.apple.com/legal/internet-services/itunes/dev/stdeula/', false)
            ->assertSee('Ayarlar &gt; Hesabımı sil', false);
    }

    public function test_english_terms_preserve_language_in_related_links(): void
    {
        $this->get('/shiftcal/terms?lang=en')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Terms of Service')
            ->assertSee('does not automatically cancel a store subscription')
            ->assertSee(route('shiftcal.privacy', ['lang' => 'en']), false)
            ->assertSee(route('shiftcal.support', ['lang' => 'en']), false);
    }

    public function test_invalid_and_array_languages_fall_back_to_turkish(): void
    {
        foreach (['lang=de', 'lang[]=en'] as $query) {
            $this->get('/shiftcal/terms?'.$query)
                ->assertOk()
                ->assertSee('<html lang="tr">', false);
        }
    }

    public function test_existing_pages_link_to_terms(): void
    {
        foreach (['/shiftcal', '/shiftcal/privacy', '/shiftcal/support'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee(route('shiftcal.terms', ['lang' => 'en']), false);
        }
    }
}
