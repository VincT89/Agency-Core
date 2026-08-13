<?php

namespace Tests\Feature\Layout;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccessibleSharedComponentsTest extends TestCase
{
    public function test_password_component_is_masked_and_preserves_password_manager_attributes(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-password-input
                id="account-password"
                name="password"
                autocomplete="current-password"
                required
            />
        BLADE);

        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('aria-controls="account-password"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('data-password-icon-hidden', $html);
        $this->assertStringContainsString('data-password-icon-visible hidden', $html);
        $this->assertStringNotContainsString('type="text"', $html);
    }

    public function test_raw_password_inputs_are_not_left_in_project_views(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getRelativePathname() === 'components'.DIRECTORY_SEPARATOR.'password-input.blade.php') {
                continue;
            }

            $contents = $file->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/<input\b[^>]*\btype\s*=\s*["\']password["\'][^>]*>/i',
                $contents,
                "Campo password non centralizzato in {$file->getRelativePathname()}"
            );
        }
    }

    public function test_password_component_calls_do_not_force_invalid_classes(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/<x-password-input\b[^>]*\bclass\s*=\s*["\']@error/is',
                $contents,
                "Direttiva di errore non valutata in {$file->getRelativePathname()}"
            );
        }

        $response = $this->get(route('login'));
        $response->assertOk();

        preg_match('/<input\b[^>]*\bid="login-password"[^>]*>/i', $response->getContent(), $matches);

        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('class="form-in"', $matches[0]);
        $this->assertStringNotContainsString('is-invalid', $matches[0]);
        $this->assertStringNotContainsString('@error', $matches[0]);
    }

    public function test_password_toggle_swaps_svg_hidden_attributes(): void
    {
        $script = File::get(resource_path('js/password-fields.js'));

        $this->assertStringContainsString("hiddenIcon.toggleAttribute('hidden', willShow)", $script);
        $this->assertStringContainsString("visibleIcon.toggleAttribute('hidden', !willShow)", $script);
        $this->assertStringNotContainsString('hiddenIcon.hidden =', $script);
        $this->assertStringNotContainsString('visibleIcon.hidden =', $script);
    }

    public function test_page_header_exposes_a_level_one_heading_and_spacing_hooks(): void
    {
        $html = Blade::render('<x-page-header eyebrow="Area"><x-slot:title><strong>Titolo</strong></x-slot:title><x-slot:actions><span class="badge">Attivo</span></x-slot:actions></x-page-header>');

        $this->assertStringContainsString('class="page-title" role="heading" aria-level="1"', $html);
        $this->assertStringContainsString('class="ph-right"', $html);
    }

    public function test_form_group_has_an_associated_label_and_required_text(): void
    {
        view()->share('errors', new ViewErrorBag());

        $html = Blade::render('<x-form-group label="Email" name="email" required><input id="field-email" name="email"></x-form-group>');

        $this->assertStringContainsString('for="field-email"', $html);
        $this->assertStringContainsString('(obbligatorio)', $html);
        $this->assertStringContainsString('data-form-group', $html);
    }

    public function test_shared_modal_has_dialog_name_and_focus_target(): void
    {
        $html = Blade::render('<x-modal id="example-dialog" title="Conferma">Contenuto</x-modal>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="example-dialog-title"', $html);
        $this->assertStringContainsString('data-dialog-initial-focus', $html);
    }
}
