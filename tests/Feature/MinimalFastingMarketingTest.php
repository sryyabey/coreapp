<?php

namespace Tests\Feature;

use Tests\TestCase;

class MinimalFastingMarketingTest extends TestCase
{
    public function test_landing_page_is_public_and_links_to_actual_features_and_privacy(): void
    {
        $this->get('/minimal-fasting')
            ->assertOk()
            ->assertSee('<html lang="tr">', false)
            ->assertSee('Kendi ritminde.')
            ->assertSee('Hesap gerektirmez')
            ->assertSee('id="features"', false)
            ->assertSee('Örnek görünüm')
            ->assertSee('Kapalı test aşamasında')
            ->assertSee('uygulama içi satın alma henüz aktif değil')
            ->assertSee(route('minimal-fasting.privacy'), false)
            ->assertSee('minimal-fasting-statistics.png')
            ->assertDontSee('play.google.com/store')
            ->assertDontSee('apps.apple.com')
            ->assertDontSee('$4.99');
    }

    public function test_translations_keep_language_in_landing_and_privacy_links(): void
    {
        foreach (['en' => 'Simple tracking.', 'de' => 'Einfach erfasst.'] as $language => $headline) {
            $this->get('/minimal-fasting?lang='.$language)
                ->assertOk()
                ->assertSee('<html lang="'.$language.'">', false)
                ->assertSee($headline)
                ->assertSee(route('minimal-fasting', ['lang' => $language]), false)
                ->assertSee(route('minimal-fasting.privacy', ['lang' => $language]), false)
                ->assertSee('mailto:info@sryya.dev', false);

            $this->get('/minimal-fasting/privacy?lang='.$language)
                ->assertOk()
                ->assertSee(route('minimal-fasting', ['lang' => $language]), false);
        }
    }

    public function test_malformed_languages_fall_back_without_rendering_input(): void
    {
        foreach (['lang=fr', 'lang[]=en', 'lang=%3Cscript%3E'] as $query) {
            $this->get('/minimal-fasting?'.$query)
                ->assertOk()
                ->assertSee('<html lang="tr">', false)
                ->assertDontSee('<script', false);
        }
    }
}
