<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\SocialPosts\Pages\EditSocialPost;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Models\Article;
use App\Models\Document;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Docs\DocumentImporter;
use App\Services\Social\SocialImporter;
use App\Services\Social\SocialWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContenidoCrmTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/crm-test-'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/piezas', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp.'/{,*/,*/*/}*', GLOB_BRACE) ?: [] as $f) {
            is_file($f) && unlink($f);
        }
        foreach (['/piezas', '/docs/blog', '/docs', '/Cosas de A51', '/contrataciones', '/blog', ''] as $d) {
            @rmdir($this->tmp.$d);
        }
        parent::tearDown();
    }

    private function como(User $u): void
    {
        $this->actingAs($u);
        \Filament\Facades\Filament::setCurrentPanel('admin');
    }

    private function pieza(string $estado = 'en revisión', string $body = 'Texto de la pieza.'): SocialPost
    {
        $p = new SocialPost(['ref' => 'LI-'.random_int(10, 99), 'titulo' => 'Pieza de prueba', 'body_md' => $body]);
        $p->forceFill(['estado' => $estado])->save();

        return $p;
    }

    // ---- Importación de redes ----

    public function test_importa_piezas_con_estado_y_aprobacion_del_archivo_y_la_pendiente_del_calendario(): void
    {
        file_put_contents($this->tmp.'/piezas/01-x.md', <<<'MD'
---
id: LI-01
estado: aprobada                 # borrador | aprobada
fecha_propuesta: 2026-11-11      # miércoles
formato: carrusel nativo (PDF) de 5 láminas
pilar: Presentación (plan 6.2)
fuente_interna: docs/services/SERVICES.md
enlace: ninguno                  # sin enlace
aprobacion_jamir: 2026-11-06 (solo texto; falta diseño)
---

# LI-01 · Presentación de Revolution Digital

## Texto de la publicación

Hola.
MD);
        file_put_contents($this->tmp.'/CALENDARIO.md', <<<'MD'
| **4** | 2026-12-02 · **LI-07** Core Web Vitals (carrusel de 8) | 2026-12-03 · **LI-08** Pieza de revisión: qué funcionó (depende de las métricas) |
MD);

        $r = SocialImporter::importar($this->tmp);

        $this->assertSame(['LI-01' => 'creada', 'LI-07' => 'creada', 'LI-08' => 'creada'], $r);
        $p = SocialPost::where('ref', 'LI-01')->first();
        $this->assertSame('aprobada', $p->estado);
        $this->assertSame('Presentación de Revolution Digital', $p->titulo);
        $this->assertNull($p->enlace);
        $this->assertSame('2026-11-06', $p->aprobado_en->toDateString());
        $this->assertStringContainsString('Hola.', $p->body_md);
        $this->assertSame('por redactar', SocialPost::where('ref', 'LI-08')->first()->estado);
        $this->assertSame('Pieza de revisión: qué funcionó', SocialPost::where('ref', 'LI-08')->first()->titulo);
        $this->assertSame(1, $p->logs()->count());

        // Segunda importación: no pisa nada.
        $this->assertSame(['LI-01' => 'omitida', 'LI-07' => 'omitida', 'LI-08' => 'omitida'], SocialImporter::importar($this->tmp));
    }

    // ---- Flujo de piezas ----

    public function test_solo_revisor_o_admin_aprueban_y_queda_quien_y_cuando(): void
    {
        $p = $this->pieza();
        $this->assertFalse(SocialWorkflow::puede($p, 'aprobar', User::factory()->editor()->create()));

        $rev = User::factory()->revisor()->create(['name' => 'Rev Isor']);
        SocialWorkflow::aplicar($p, 'aprobar', $rev);

        $p->refresh();
        $this->assertSame('aprobada', $p->estado);
        $this->assertSame('Rev Isor', $p->aprobado_por);
        $this->assertNotNull($p->aprobado_en);
        $this->assertSame(1, $p->logs()->count());
    }

    public function test_el_estado_de_una_pieza_no_se_asigna_en_masa(): void
    {
        $p = new SocialPost(['ref' => 'LI-90', 'titulo' => 'x', 'estado' => 'publicada', 'aprobado_por' => 'Alguien']);
        $p->save();

        $this->assertSame('por redactar', $p->fresh()->estado);
        $this->assertNull($p->fresh()->aprobado_por);
    }

    public function test_una_pieza_aprobada_no_se_edita_hasta_retirar_la_aprobacion(): void
    {
        $p = $this->pieza();
        $rev = User::factory()->revisor()->create();
        SocialWorkflow::aplicar($p, 'aprobar', $rev);

        $p = $p->fresh();
        $p->body_md = 'Texto cambiado a escondidas';
        try {
            $p->save();
            $this->fail('Debió bloquearse');
        } catch (DomainException) {
        }

        try {
            SocialWorkflow::aplicar($p->fresh(), 'retirar_aprobacion', $rev, ' ');
            $this->fail('Debió pedir motivo');
        } catch (DomainException) {
        }
        SocialWorkflow::aplicar($p->fresh(), 'retirar_aprobacion', $rev, 'Cambiar el cierre');
        $b = $p->fresh();
        $this->assertSame('en revisión', $b->estado);
        $this->assertNull($b->aprobado_por);
        $b->body_md = 'Texto corregido';
        $b->save();
        $this->assertSame('Texto corregido', $b->fresh()->body_md);
    }

    public function test_marcar_publicada_solo_el_admin_y_solo_registra(): void
    {
        $p = $this->pieza('aprobada');
        $this->assertFalse(SocialWorkflow::puede($p, 'publicar', User::factory()->revisor()->create()));

        SocialWorkflow::aplicar($p, 'publicar', User::factory()->admin()->create());
        $this->assertSame('publicada', $p->fresh()->estado);
    }

    public function test_no_se_aprueba_una_pieza_sin_texto(): void
    {
        $p = $this->pieza(body: '');

        $this->expectException(DomainException::class);
        SocialWorkflow::aplicar($p, 'aprobar', User::factory()->admin()->create());
    }

    // ---- Panel ----

    public function test_el_panel_de_redes_carga_y_el_editor_no_ve_aprobar(): void
    {
        $p = $this->pieza();

        $this->como(User::factory()->editor()->create());
        Livewire::test(ListSocialPosts::class)->assertOk()->assertSee('Pieza de prueba');
        Livewire::test(EditSocialPost::class, ['record' => $p->getRouteKey()])->assertOk()->assertActionHidden('aprobar');

        $this->como(User::factory()->revisor()->create());
        Livewire::test(EditSocialPost::class, ['record' => $p->getRouteKey()])->assertActionVisible('aprobar')->callAction('aprobar')->assertNotified();
        $this->assertSame('aprobada', $p->fresh()->estado);
    }

    // ---- Documentos ----

    public function test_los_documentos_solo_entran_de_la_lista_cerrada(): void
    {
        foreach (['docs/blog', 'Cosas de A51', 'contrataciones', 'blog'] as $d) {
            mkdir($this->tmp.'/'.$d, 0777, true);
        }
        file_put_contents($this->tmp.'/docs/blog/GUIA.md', "# Guía de estilo\n\nSobrio.");
        file_put_contents($this->tmp.'/Cosas de A51/secreto.md', "# Datos de jugadores\n");
        file_put_contents($this->tmp.'/contrataciones/ruc.md', "# Constancia\n");
        file_put_contents($this->tmp.'/blog/COLA.md', "# Cola\n");
        file_put_contents($this->tmp.'/blog/otro.md', "# No listado\n");

        $r = DocumentImporter::importar($this->tmp);

        $this->assertEqualsCanonicalizing(['docs/blog/GUIA.md', 'blog/COLA.md'], array_keys($r));
        $this->assertSame(0, Document::where('ruta', 'like', '%A51%')->count());
        $this->assertSame('Guía de estilo', Document::where('ruta', 'docs/blog/GUIA.md')->first()->titulo);
        $this->assertSame('blog', Document::where('ruta', 'docs/blog/GUIA.md')->first()->categoria);

        // Sin cambios no reescribe; con cambios actualiza.
        $this->assertSame('igual', DocumentImporter::importar($this->tmp)['blog/COLA.md']);
        file_put_contents($this->tmp.'/blog/COLA.md', "# Cola nueva\n");
        $this->assertSame('actualizado', DocumentImporter::importar($this->tmp)['blog/COLA.md']);
    }

    public function test_rutas_sensibles_se_rechazan(): void
    {
        foreach (['Cosas de A51/x.md', 'img Webs Realizadas/P/FICHA.md', 'contrataciones/ruc.md', 'operations/costes.md', '.env'] as $ruta) {
            $this->assertTrue(DocumentImporter::prohibida($ruta), $ruta);
        }
        $this->assertFalse(DocumentImporter::prohibida('docs/blog/GUIA_ESTILO.md'));
    }

    public function test_la_biblioteca_se_lee_en_el_panel_y_no_se_edita(): void
    {
        $d = Document::create(['ruta' => 'docs/x.md', 'titulo' => 'Doc X', 'categoria' => 'web', 'contenido_md' => "# Doc X\n\nContenido.", 'hash' => str_repeat('a', 64), 'sincronizado_en' => now()]);

        $this->como(User::factory()->revisor()->create());
        Livewire::test(ListDocuments::class)->assertOk()->assertSee('Doc X');
        Livewire::test(ViewDocument::class, ['record' => $d->getRouteKey()])->assertOk()->assertSee('Contenido.');
    }

    // ---- Vista previa al compartir ----

    public function test_la_pestana_seo_muestra_la_vista_previa_para_redes_con_los_campos_de_redes(): void
    {
        $a = Article::factory()->conFuentes()->estado('borrador')->create([
            'og_title' => 'Título para redes distinto', 'og_description' => 'Descripción para redes distinta.', 'imagen_src' => '/img/blog/portada.webp',
        ]);
        $this->como(User::factory()->editor()->create());

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertOk()
            ->assertSee('Título para redes distinto')
            ->assertSee('Descripción para redes distinta.')
            ->assertSee('revolutiondigital.agency/img/blog/portada.webp', escape: false);
    }

    // ---- Imagen de portada e idioma ----

    public function test_la_portada_subida_calcula_ruta_medidas_y_avisos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $falsa = \Illuminate\Http\UploadedFile::fake()->image('x.png', 1000, 1000);
        \Illuminate\Support\Facades\Storage::disk('public')->put('blog-portadas/mi-articulo.png', file_get_contents($falsa->getRealPath()));

        $a = Article::factory()->estado('borrador')->create(['slug' => 'mi-articulo']);
        $a->imagen_archivo = 'blog-portadas/mi-articulo.png';
        $a->save();
        $a->refresh();

        $this->assertSame('/img/blog/mi-articulo.png', $a->imagen_src);
        $this->assertSame([1000, 1000], [$a->imagen_ancho, $a->imagen_alto]);
        $avisos = implode(' | ', \App\Services\Blog\ArticleGate::warnings($a));
        $this->assertStringContainsString('no es 16:9', $avisos);
        $this->assertStringContainsString('estrecha', $avisos);
        $this->assertStringContainsString('WebP', $avisos);

        // Quitar la imagen limpia ruta y medidas.
        $a->imagen_archivo = null;
        $a->save();
        $this->assertNull($a->fresh()->imagen_src);
        $this->assertNull($a->fresh()->imagen_ancho);
    }

    public function test_una_portada_16_9_no_da_avisos_de_medidas(): void
    {
        $this->assertSame([], \App\Support\Portada::avisos(1920, 1080, 120 * 1024, 'blog-portadas/x.webp'));
        $this->assertSame('1920×1080 px · 16:9 · 120 KB · WEBP', \App\Support\Portada::resumen(1920, 1080, 120 * 1024, 'blog-portadas/x.webp'));
    }

    public function test_el_idioma_de_un_articulo_existente_no_se_puede_cambiar_y_se_ve_la_ficha_de_imagen(): void
    {
        $a = Article::factory()->conFuentes()->estado('borrador')->create(['idioma' => 'en']);
        $this->como(User::factory()->editor()->create());

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertOk()
            ->assertFormFieldIsDisabled('idioma')
            ->assertSee('Versiones en otros idiomas')
            ->assertSee('1920×1080');
    }

    public function test_el_revisor_legal_lee_pero_no_edita_y_ve_la_accion_legal(): void
    {
        $a = Article::factory()->conFuentes()->estado('en revisión')->create(['riesgo_legal' => 'datos-personales', 'revision_legal' => 'pendiente']);
        $legal = User::factory()->create(['role' => 'legal']);
        $this->como($legal);

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('revision_legal')
            ->assertActionHidden('aprobar')
            ->callAction('revision_legal', ['valor' => 'aprobada', 'nota' => 'Revisado por la abogada, ok.'])
            ->assertNotified();

        $this->assertSame('aprobada', $a->fresh()->revision_legal);
    }
}
