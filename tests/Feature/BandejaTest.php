<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\ContactMessageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Solo datos inventados: ningún dato real entra en las pruebas. */
class BandejaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'clave-de-prueba-no-real';

    private function activar(): void
    {
        config(['inbox.enabled' => true, 'inbox.key' => self::CLAVE, 'inbox.retention_days' => null]);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'nombre' => 'Persona de Prueba', 'empresa' => 'Empresa Ficticia SAC', 'email' => 'Prueba@Ejemplo.TEST',
            'telefono' => '+51 900 000 000', 'servicio' => 'Web', 'mensaje' => 'Mensaje de prueba con diez caracteres o más.',
            'lang' => 'es', 'pagina' => '/contacto/', 'consentimiento' => true, 'consentimiento_version' => 'v-prueba',
        ];
    }

    private function enviar(array $datos, ?string $clave = self::CLAVE)
    {
        return $this->postJson('/api/contacto', $datos, $clave ? ['X-Inbox-Key' => $clave] : []);
    }

    private function como(User $u): void
    {
        $this->actingAs($u);
        \Filament\Facades\Filament::setCurrentPanel('admin');
    }

    public function test_apagada_por_defecto_no_guarda_nada(): void
    {
        $this->enviar($this->datos())->assertStatus(503);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_sin_clave_o_con_clave_incorrecta_se_rechaza(): void
    {
        $this->activar();
        $this->enviar($this->datos(), null)->assertStatus(401);
        $this->enviar($this->datos(), 'otra-clave')->assertStatus(401);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_guarda_un_mensaje_valido_con_consentimiento_y_sin_ip(): void
    {
        $this->activar();

        $this->enviar($this->datos())->assertStatus(201)->assertJson(['ok' => true]);

        $m = ContactMessage::first();
        $this->assertSame('prueba@ejemplo.test', $m->email);
        $this->assertSame('nuevo', $m->estado);
        $this->assertTrue($m->consentimiento);
        $this->assertSame('v-prueba', $m->consentimiento_version);
        $this->assertNotNull($m->consentimiento_en);
        $this->assertNull($m->conservar_hasta); // sin plazo definido por el abogado
        $this->assertArrayNotHasKey('ip', $m->getAttributes());
    }

    public function test_sin_consentimiento_no_se_guarda(): void
    {
        $this->activar();

        $this->enviar($this->datos(['consentimiento' => false]))->assertStatus(422);
        $d = $this->datos();
        unset($d['consentimiento_version']);
        $this->enviar($d)->assertStatus(422);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_campo_trampa_finge_exito_y_no_guarda(): void
    {
        $this->activar();

        $this->enviar($this->datos(['website' => 'http://spam.test']))->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_valida_correo_mensaje_y_telefono(): void
    {
        $this->activar();

        $this->enviar($this->datos(['email' => 'no-es-correo']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->enviar($this->datos(['mensaje' => 'corto']))->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->enviar($this->datos(['telefono' => 'abc<script>']))->assertStatus(422)->assertJsonValidationErrors('telefono');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_con_plazo_definido_fija_conservar_hasta_y_el_comando_borra_los_vencidos(): void
    {
        $this->activar();
        config(['inbox.retention_days' => 30]);
        $this->enviar($this->datos())->assertStatus(201);
        $this->assertSame(now()->addDays(30)->toDateString(), ContactMessage::first()->conservar_hasta->toDateString());

        ContactMessage::query()->update(['conservar_hasta' => now()->subDay()->toDateString()]);
        $this->artisan('crm:purgar-mensajes')->assertSuccessful();

        $this->assertSame(0, ContactMessage::count());
        $this->assertSame('eliminado por plazo', ContactMessageLog::latest('id')->first()->accion);
    }

    public function test_sin_plazo_el_comando_no_borra_nada(): void
    {
        ContactMessage::create($this->datosModelo());
        ContactMessage::query()->update(['conservar_hasta' => now()->subYear()->toDateString()]);
        config(['inbox.retention_days' => null]);

        $this->artisan('crm:purgar-mensajes')->assertSuccessful();

        $this->assertSame(1, ContactMessage::count());
    }

    private function datosModelo(): array
    {
        return ['nombre' => 'Persona de Prueba', 'email' => 'p@ejemplo.test', 'mensaje' => 'Mensaje de prueba largo.', 'consentimiento' => true];
    }

    // ---- Panel ----

    public function test_solo_el_administrador_entra_a_los_mensajes(): void
    {
        $m = ContactMessage::create($this->datosModelo());

        foreach ([User::factory()->editor()->create(), User::factory()->revisor()->create(), User::factory()->create(['role' => 'legal'])] as $u) {
            $this->como($u);
            Livewire::test(ListContactMessages::class)->assertForbidden();
        }

        $this->como(User::factory()->admin()->create());
        Livewire::test(ListContactMessages::class)->assertOk()->assertSee('Nuevos');
    }

    public function test_abrir_un_mensaje_lo_marca_leido_y_queda_registrado(): void
    {
        $m = ContactMessage::create($this->datosModelo());
        $admin = User::factory()->admin()->create(['name' => 'Admin Prueba']);
        $this->como($admin);

        Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()])->assertOk()->assertSee('Mensaje de prueba largo.');

        $this->assertSame('leído', $m->fresh()->estado);
        $acciones = ContactMessageLog::where('contact_message_id', $m->id)->pluck('accion')->all();
        $this->assertContains('vio', $acciones);
        $this->assertContains('estado: nuevo → leído', $acciones);
    }

    public function test_el_texto_del_mensaje_se_muestra_escapado(): void
    {
        $m = ContactMessage::create(['mensaje' => '<script>alert(1)</script> hola que tal'] + $this->datosModelo());
        $this->como(User::factory()->admin()->create());

        Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()])
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSee('&lt;script&gt;', escape: false);
    }

    public function test_el_estado_no_se_asigna_en_masa(): void
    {
        $m = ContactMessage::create(['estado' => 'respondido'] + $this->datosModelo());

        $this->assertSame('nuevo', $m->fresh()->estado);
    }

    public function test_eliminar_borra_el_mensaje_y_deja_registro_sin_datos_personales(): void
    {
        $m = ContactMessage::create($this->datosModelo());
        $id = $m->id;
        $this->como(User::factory()->admin()->create());

        Livewire::test(ViewContactMessage::class, ['record' => $m->getRouteKey()])
            ->callAction('eliminar', ['motivo' => 'Solicitud de borrado del titular'])
            ->assertNotified();

        $this->assertSame(0, ContactMessage::count());
        $log = ContactMessageLog::where('contact_message_id', $id)->where('accion', 'eliminado')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('ejemplo.test', json_encode($log->toArray()));
    }
}
