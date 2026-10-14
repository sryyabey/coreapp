<?php

namespace Tests\Feature;

use Tests\TestCase;

class MinimalFastingPrivacyTest extends TestCase
{
    public function test_policy_is_public_and_describes_actual_local_data_handling(): void
    {
        $this->get('/minimal-fasting/privacy')
            ->assertOk()
            ->assertSee('<html lang="tr">', false)
            ->assertSee('Gizlilik Politikası')
            ->assertSee('dev.sryya.mfasting')
            ->assertSee('geliştirici sunucularına gönderilmez')
            ->assertSee('Yedek dosyaları uygulama tarafından ayrıca şifrelenmez')
            ->assertSee('uygulama içi satın alma entegrasyonu bulunmaz')
            ->assertSee('uygulamayı tamamen kaldırana kadar')
            ->assertSee('info@sryya.dev')
            ->assertSee('Süreyya Karabay')
            ->assertDontSee('Hesap silme talebi gönder')
            ->assertDontSee('Firebase')
            ->assertDontSee('<script', false);
    }

    public function test_english_and_german_pages_keep_their_language_and_contact(): void
    {
        foreach (['en' => 'Privacy Policy', 'de' => 'Datenschutzerklärung'] as $language => $title) {
            $this->get('/minimal-fasting/privacy?lang='.$language)
                ->assertOk()
                ->assertSee('<html lang="'.$language.'">', false)
                ->assertSee($title)
                ->assertSee(route('minimal-fasting.privacy', ['lang' => $language]), false)
                ->assertSee('dev.sryya.mfasting')
                ->assertSee('mailto:info@sryya.dev', false)
                ->assertSee('id="retention"', false)
                ->assertSee('id="contact"', false);
        }
    }

    public function test_invalid_and_array_language_queries_safely_fall_back_to_turkish(): void
    {
        foreach (['lang=fr', 'lang[]=en', 'lang=%3Cscript%3E'] as $query) {
            $this->get('/minimal-fasting/privacy?'.$query)
                ->assertOk()
                ->assertSee('<html lang="tr">', false)
                ->assertDontSee('<script', false);
        }
    }

    public function test_contact_address_uses_the_app_specific_configuration(): void
    {
        config(['support.minimal_fasting_email' => 'fasting@example.com']);

        $this->get('/minimal-fasting/privacy?lang=en')
            ->assertOk()
            ->assertSee('mailto:fasting@example.com', false)
            ->assertDontSee('mailto:info@sryya.dev', false);
    }
}
