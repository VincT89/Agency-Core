<?php

namespace Tests\Feature\Layout;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

class PageHeaderXSSEscapingTest extends TestCase
{
    public function test_page_header_title_is_safe_from_xss_in_props()
    {
        // Se passiamo roba pericolosa via prop (cosa sconsigliata ora, ma per legacy)
        $dangerousString = '<script>alert("XSS")</script>';

        $html = Blade::render('<x-page-header :title="$title" />', [
            'title' => $dangerousString
        ]);

        // Non deve contenere il tag non escapato
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $html);
        
        // Deve contenere la versione escapata (o almeno i bracket escaped)
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $html);
    }

    public function test_page_header_allows_safe_html_in_slot()
    {
        $safeHtml = '<strong>Safe</strong> Title';

        $html = Blade::render('<x-page-header><x-slot:title>{!! $title !!}</x-slot:title></x-page-header>', [
            'title' => $safeHtml
        ]);

        // Deve contenere il tag esattamente così com'è, dato che è nello slot esplicito
        $this->assertStringContainsString('<strong>Safe</strong> Title', $html);
    }
}
