<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\CompanyProfiles\Pages\EditCompanyProfile;
use App\Models\Article;
use App\Models\CompanyProfile;
use App\Models\User;
use Database\Seeders\CompanyProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelArticulosTest extends TestCase
{
    use RefreshDatabase;

    private function como(User $u): void
    {
        $this->actingAs($u);
        \Filament\Facades\Filament::setCurrentPanel('admin');
    }

    public function test_la_lista_carga_con_sus_pestañas(): void
    {
        $this->como(User::factory()->revisor()->create());
        Article::factory()->conFuentes()->estado('en revisión')->create();

        Livewire::test(ListArticles::class)->assertOk()->assertSee('Por revisar');
    }

    public function test_el_editor_no_ve_aprobar_y_el_revisor_si(): void
    {
        $a = Article::factory()->conFuentes()->estado('en revisión')->create();

        $this->como(User::factory()->editor()->create());
        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->assertOk()->assertActionHidden('aprobar')->assertActionHidden('publicar');

        $this->como(User::factory()->revisor()->create());
        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->assertOk()->assertActionVisible('aprobar')->assertActionVisible('devolver_a_borrador');
    }

    public function test_el_revisor_aprueba_desde_el_panel(): void
    {
        $a = Article::factory()->conFuentes()->estado('en revisión')->create();
        $this->como(User::factory()->revisor()->create());

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->callAction('aprobar')->assertNotified();

        $this->assertSame('aprobado', $a->fresh()->estado);
    }

    public function test_aprobar_con_riesgo_legal_pendiente_avisa_y_no_cambia(): void
    {
        $a = Article::factory()->conFuentes()->estado('en revisión')->create(['riesgo_legal' => 'igaming', 'revision_legal' => 'pendiente']);
        $this->como(User::factory()->admin()->create());

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->callAction('aprobar')->assertNotified('No se pudo completar');

        $this->assertSame('en revisión', $a->fresh()->estado);
    }

    public function test_un_artículo_aprobado_muestra_exportar_y_no_deja_guardar(): void
    {
        $a = Article::factory()->conFuentes()->estado('aprobado')->create(['fecha_aprobacion' => '2026-11-06']);
        $this->como(User::factory()->admin()->create());

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('exportar')
            ->assertActionVisible('retirar_aprobacion')
            ->assertActionHidden('aprobar');
    }

    public function test_crear_un_articulo_lo_deja_en_borrador_con_historial(): void
    {
        $this->como(User::factory()->editor()->create());

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'titulo' => 'Un artículo nuevo de prueba del CRM',
                'descripcion' => 'Descripción de prueba con la longitud suficiente para pasar la validación del formulario de artículos del CRM.',
                'slug' => 'articulo-nuevo-de-prueba',
                'idioma' => 'es',
                'categoria' => 'seo',
                'tipo' => 'guia',
                'body_md' => "Texto.\n\n## Resumen\n\n- a",
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Article::where('slug', 'articulo-nuevo-de-prueba')->firstOrFail();
        $this->assertSame('borrador', $a->estado);
        $this->assertSame(1, $a->statusLogs()->count());
    }

    public function test_slug_repetido_en_el_mismo_idioma_se_rechaza(): void
    {
        Article::factory()->create(['slug' => 'repetido', 'idioma' => 'es']);
        $this->como(User::factory()->editor()->create());

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'titulo' => 'Otro artículo con el mismo slug',
                'descripcion' => 'Descripción de prueba con la longitud suficiente para pasar la validación del formulario de artículos del CRM.',
                'slug' => 'repetido', 'idioma' => 'es', 'categoria' => 'seo', 'tipo' => 'guia', 'body_md' => 'Texto.',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_datos_de_empresa_solo_para_admin(): void
    {
        $this->seed(CompanyProfileSeeder::class);
        $c = CompanyProfile::first();
        $this->assertSame('20612802174', $c->ruc);
        $this->assertNull($c->instagram_url); // no se inventan direcciones de redes

        $secreto = ['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'];
        $this->actingAs(User::factory()->editor()->create($secreto))->get('/admin/company-profiles')->assertForbidden();

        $this->como(User::factory()->admin()->create($secreto));
        Livewire::test(EditCompanyProfile::class)->assertOk()
            ->fillForm(['instagram_url' => 'https://www.instagram.com/ejemplo'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('https://www.instagram.com/ejemplo', $c->fresh()->instagram_url);
    }

    public function test_ruc_con_formato_incorrecto_se_rechaza(): void
    {
        $this->seed(CompanyProfileSeeder::class);
        $this->como(User::factory()->admin()->create());

        Livewire::test(EditCompanyProfile::class)->fillForm(['ruc' => '123'])->call('save')->assertHasFormErrors(['ruc']);
    }
}
