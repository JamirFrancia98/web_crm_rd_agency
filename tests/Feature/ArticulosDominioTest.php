<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleStatusLog;
use App\Models\User;
use App\Services\Blog\ArticleGate;
use App\Services\Blog\ArticleImporter;
use App\Services\Blog\ArticleMarkdown;
use App\Services\Blog\ArticleWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticulosDominioTest extends TestCase
{
    use RefreshDatabase;

    private const PAQUETE = '/Users/jamir/Documents/Revolution Digital/blog/exportacion/blog_posts.json';

    private function listo(string $estado = 'en revisión', array $attrs = []): Article
    {
        return Article::factory()->conFuentes()->estado($estado)->create($attrs);
    }

    public function test_el_estado_no_se_asigna_en_masa(): void
    {
        $datos = Article::factory()->make()->getAttributes();
        $a = new Article($datos + ['estado' => 'publicado']); // lo que haría un formulario manipulado
        $a->save();

        $this->assertSame('borrador', $a->fresh()->estado);
    }

    public function test_editor_no_puede_aprobar_pero_revisor_si(): void
    {
        $a = $this->listo();

        $this->assertFalse(ArticleWorkflow::puede($a, 'aprobar', User::factory()->editor()->create()));
        $this->assertTrue(ArticleWorkflow::puede($a, 'aprobar', User::factory()->revisor()->create()));

        $this->expectException(DomainException::class);
        ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->editor()->create());
    }

    public function test_aprobar_pone_fecha_y_deja_historial(): void
    {
        $a = $this->listo();
        $revisor = User::factory()->revisor()->create(['name' => 'Rev Isor']);

        ArticleWorkflow::aplicar($a, 'aprobar', $revisor);

        $a->refresh();
        $this->assertSame('aprobado', $a->estado);
        $this->assertSame(now()->toDateString(), $a->fecha_aprobacion->toDateString());
        $this->assertSame('Rev Isor', $a->revisor);
        $log = ArticleStatusLog::where('article_id', $a->id)->latest('id')->first();
        $this->assertSame('en revisión', $log->estado_anterior);
        $this->assertSame('aprobado', $log->estado_nuevo);
        $this->assertStringContainsString($revisor->email, $log->usuario);
    }

    public function test_no_se_aprueba_con_riesgo_legal_sin_revision(): void
    {
        $a = $this->listo(attrs: ['riesgo_legal' => 'datos-personales', 'revision_legal' => 'pendiente']);

        try {
            ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->admin()->create());
            $this->fail('Debió bloquearse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('revisión legal', $e->getMessage());
        }
        $this->assertSame('en revisión', $a->fresh()->estado);
    }

    public function test_no_se_aprueba_sin_dos_fuentes_ni_aporte_propio(): void
    {
        $a = Article::factory()->estado('en revisión')->create(['aporte_propio' => 'PENDIENTE']);
        $p = ArticleGate::problems($a->load('sources'), paraAprobar: true);

        $this->assertNotEmpty(array_filter($p, fn ($m) => str_contains($m, '2 fuentes')));
        $this->assertNotEmpty(array_filter($p, fn ($m) => str_contains($m, 'aporte propio')));
    }

    public function test_un_artículo_aprobado_no_se_edita_sin_devolverlo_a_revision(): void
    {
        $a = $this->listo();
        ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->revisor()->create());

        $a = $a->fresh();
        $a->titulo = 'Otro título distinto del aprobado';
        try {
            $a->save();
            $this->fail('Debió bloquearse la edición');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no se puede editar', $e->getMessage());
        }

        ArticleWorkflow::aplicar($a->fresh(), 'retirar_aprobacion', User::factory()->revisor()->create(), 'Corregir título');
        $b = $a->fresh();
        $this->assertSame('en revisión', $b->estado);
        $this->assertNull($b->fecha_aprobacion);
        $b->titulo = 'Otro título distinto del aprobado';
        $b->save();
        $this->assertSame('Otro título distinto del aprobado', $b->fresh()->titulo);
    }

    public function test_devolver_y_descartar_exigen_motivo(): void
    {
        $a = $this->listo();
        $admin = User::factory()->admin()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');
        ArticleWorkflow::aplicar($a, 'descartar', $admin, '   ');
    }

    public function test_solo_el_admin_marca_publicado_y_con_fecha(): void
    {
        $a = $this->listo();
        ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->revisor()->create());
        $a = $a->fresh();

        $this->assertFalse(ArticleWorkflow::puede($a, 'publicar', User::factory()->revisor()->create()));

        $admin = User::factory()->admin()->create();
        try {
            ArticleWorkflow::aplicar($a, 'publicar', $admin);
            $this->fail('Debió pedir la fecha');
        } catch (DomainException $e) {
            $this->assertStringContainsString('fecha de publicación', $e->getMessage());
        }

        // Sin portada no se publica: es la miniatura al compartir.
        try {
            ArticleWorkflow::aplicar($a->fresh(), 'publicar', $admin, null, '2026-11-20');
            $this->fail('Debió exigir la portada');
        } catch (DomainException $e) {
            $this->assertStringContainsString('portada', $e->getMessage());
        }
        $this->assertSame('aprobado', $a->fresh()->estado);

        $this->conPortada($a);
        ArticleWorkflow::aplicar($a->fresh(), 'publicar', $admin, null, '2026-11-20');
        $b = $a->fresh();
        $this->assertSame('publicado', $b->estado);
        $this->assertSame('2026-11-20', $b->fecha_publicacion->toDateString());
        $this->assertSame('index,follow', $b->robots);
    }

    /** Pone la portada sin pasar por la edición normal (el artículo ya está aprobado y bloqueado). */
    private function conPortada(Article $a): void
    {
        Article::whereKey($a->id)->update(['imagen_src' => '/img/blog/portada.webp', 'imagen_alt' => 'Portada', 'imagen_licencia' => 'Propia']);
    }

    public function test_despublicar_permite_editar_y_volver_a_publicar(): void
    {
        $admin = User::factory()->admin()->create();
        $revisor = User::factory()->revisor()->create();
        $a = $this->listo(attrs: ['imagen_src' => '/img/blog/portada.webp', 'imagen_alt' => 'Portada', 'imagen_licencia' => 'Propia']);
        ArticleWorkflow::aplicar($a, 'aprobar', $revisor);
        ArticleWorkflow::aplicar($a->fresh(), 'publicar', $admin, null, '2026-11-20');

        // Publicado: no se edita.
        $p = $a->fresh();
        $p->titulo = 'Título corregido después de publicar';
        try {
            $p->save();
            $this->fail('Debió bloquearse');
        } catch (DomainException) {
        }

        // Solo admin y revisor despublican, y con motivo.
        $this->assertFalse(ArticleWorkflow::puede($a->fresh(), 'despublicar', User::factory()->editor()->create()));
        try {
            ArticleWorkflow::aplicar($a->fresh(), 'despublicar', $revisor, '  ');
            $this->fail('Debió pedir motivo');
        } catch (DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        ArticleWorkflow::aplicar($a->fresh(), 'despublicar', $revisor, 'Corregir una cifra');
        $b = $a->fresh();
        $this->assertSame('en revisión', $b->estado);
        $this->assertSame('noindex,nofollow', $b->robots);
        $this->assertNull($b->fecha_aprobacion);

        $b->titulo = 'Título corregido después de publicar';
        $b->save();
        $this->assertSame('Título corregido después de publicar', $b->fresh()->titulo);

        // Hay que volver a aprobar y a publicar.
        ArticleWorkflow::aplicar($b->fresh(), 'aprobar', $revisor);
        ArticleWorkflow::aplicar($b->fresh(), 'publicar', $admin, null, '2026-11-25');
        $c = $a->fresh();
        $this->assertSame('publicado', $c->estado);
        $this->assertSame('index,follow', $c->robots);
        $this->assertSame('Título corregido después de publicar', $c->titulo);
        $this->assertGreaterThanOrEqual(5, ArticleStatusLog::where('article_id', $a->id)->count());
    }

    public function test_quien_escribe_el_articulo_no_lo_aprueba(): void
    {
        $autor = User::factory()->revisor()->create();
        $a = $this->listo(attrs: ['created_by' => $autor->id]);

        try {
            ArticleWorkflow::aplicar($a, 'aprobar', $autor);
            $this->fail('Debió bloquearse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no lo aprueba', $e->getMessage());
        }

        ArticleWorkflow::aplicar($a->fresh(), 'aprobar', User::factory()->revisor()->create());
        $this->assertSame('aprobado', $a->fresh()->estado);
    }

    public function test_revision_legal_solo_legal_o_admin_con_nota_e_historial(): void
    {
        $a = $this->listo(attrs: ['riesgo_legal' => 'datos-personales', 'revision_legal' => 'pendiente']);
        $legal = User::factory()->create(['role' => 'legal']);

        foreach ([User::factory()->editor()->create(), User::factory()->revisor()->create()] as $otro) {
            try {
                ArticleWorkflow::revisionLegal($a, $otro, 'aprobada', 'ok');
                $this->fail('Solo legal o admin');
            } catch (DomainException) {
            }
        }
        $this->assertSame('pendiente', $a->fresh()->revision_legal);

        // La revisión legal no se cambia por el camino normal de edición.
        $x = $a->fresh();
        $x->revision_legal = 'aprobada';
        try {
            $x->save();
            $this->fail('Debió bloquearse');
        } catch (DomainException) {
        }

        try {
            ArticleWorkflow::revisionLegal($a, $legal, 'aprobada', '  ');
            $this->fail('Debió pedir nota');
        } catch (DomainException) {
        }

        ArticleWorkflow::revisionLegal($a, $legal, 'aprobada', 'Abogado X revisó el texto el 2026-11-06');
        $this->assertSame('aprobada', $a->fresh()->revision_legal);
        $log = ArticleStatusLog::where('article_id', $a->id)->latest('id')->first();
        $this->assertSame('legal: aprobada', $log->estado_nuevo);
        $this->assertStringContainsString($legal->email, $log->usuario);

        // El rol legal no aprueba ni publica artículos.
        $this->assertFalse(ArticleWorkflow::puede($a->fresh(), 'aprobar', $legal));
        // Ya aprobable, y el flag no queda activado.
        $this->assertFalse(Article::$cambioLegalAutorizado);
        ArticleWorkflow::aplicar($a->fresh(), 'aprobar', User::factory()->revisor()->create());

        // Aprobado: ya no se toca la revisión legal.
        $this->assertFalse(ArticleWorkflow::puedeRevisionLegal($a->fresh(), $legal));
    }

    public function test_un_usuario_inactivo_no_puede_mover_estados(): void
    {
        $a = $this->listo();

        $this->assertFalse(ArticleWorkflow::puede($a, 'aprobar', User::factory()->admin()->inactive()->create()));
    }

    public function test_exportar_solo_aprobados_y_sin_nota_interna(): void
    {
        $a = $this->listo(attrs: ['nota_revisor' => 'NOTA SECRETA INTERNA']);
        try {
            ArticleMarkdown::export($a);
            $this->fail('No debe exportar en revisión');
        } catch (DomainException) {
        }

        ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->revisor()->create());
        $out = ArticleMarkdown::export($a->fresh());

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-articulo-\d+\.es\.md$/', $out['filename']);
        $this->assertStringStartsWith("---\ntitulo: ", $out['content']);
        foreach (['slug:', 'idioma: es', 'estado: "aprobado"', 'fecha_aprobacion: '.now()->toDateString(), 'riesgo_legal: ninguno', 'fuentes:', 'imagen:', 'palabra_clave: "PENDIENTE"'] as $k) {
            $this->assertStringContainsString($k, $out['content']);
        }
        $this->assertStringNotContainsString('NOTA SECRETA', $out['content']);
        $this->assertStringContainsString("\n---\n\nTexto de prueba.", $out['content']);
    }

    public function test_comillas_y_saltos_en_el_frontmatter_quedan_escapados(): void
    {
        $a = $this->listo(attrs: ['aporte_propio' => "Línea 1\nLínea \"2\": con dos puntos"]);
        ArticleWorkflow::aplicar($a, 'aprobar', User::factory()->revisor()->create());

        $c = ArticleMarkdown::export($a->fresh())['content'];

        $this->assertStringContainsString('aporte_propio: "Línea 1\nLínea \"2\": con dos puntos"', $c);
    }

    public function test_importar_el_paquete_real(): void
    {
        if (! is_file(self::PAQUETE)) {
            $this->markTestSkipped('El paquete de exportación no está en esta máquina.');
        }
        $datos = json_decode(file_get_contents(self::PAQUETE), true);

        $r = array_map(fn ($p) => ArticleImporter::importar($p), $datos['posts']);
        $this->assertSame(count($datos['posts']), count(array_filter($r, fn ($x) => $x === 'creado')));
        $this->assertSame(count($datos['posts']), Article::count());

        // Estados tal cual venían, y las reglas siguen aplicando: los de riesgo legal pendiente no son aprobables.
        foreach ($datos['posts'] as $p) {
            $a = Article::where('post_ref', $p['id_tema'].'-'.$p['contenido']['idioma'])->first();
            $this->assertSame($p['estado'], $a->estado, $p['id_tema']);
            $this->assertGreaterThanOrEqual(2, $a->sources()->count(), $p['id_tema']);
            $this->assertNotEmpty($a->keywords()->where('rol', 'principal')->first(), $p['id_tema']);
        }
        $this->assertNotEmpty(ArticleGate::problems(Article::where('id_tema', 'T11')->first()->load('sources'), true));

        // Segunda importación: no pisa nada.
        $r2 = array_map(fn ($p) => ArticleImporter::importar($p), $datos['posts']);
        $this->assertSame([], array_values(array_filter($r2, fn ($x) => $x !== 'omitido')));
        $this->assertSame(count($datos['posts']), Article::count());
    }
}
