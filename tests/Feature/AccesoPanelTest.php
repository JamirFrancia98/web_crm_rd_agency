<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visitante_va_al_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_el_login_se_muestra(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_un_usuario_inactivo_no_entra_al_panel(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_el_admin_entra_a_usuarios(): void
    {
        // Sin 2 pasos configurado el panel lo exige: redirige a la configuración, no a un error.
        $user = User::factory()->admin()->create(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->actingAs($user)->get('/admin/users')->assertOk();
    }

    public function test_editor_y_revisor_no_entran_a_usuarios(): void
    {
        foreach ([UserRole::Editor, UserRole::Revisor] as $rol) {
            $user = User::factory()->create(['role' => $rol->value, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
            $this->actingAs($user)->get('/admin/users')->assertForbidden();
        }
    }

    public function test_el_rol_y_el_estado_no_se_asignan_en_masa(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@example.test', 'password' => 'una-clave-larga-1', 'role' => 'admin', 'is_active' => false]);

        // Los valores enviados quedan ignorados y se queda el de menos permisos.
        $this->assertSame(UserRole::Revisor, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_el_secreto_de_dos_pasos_se_guarda_cifrado(): void
    {
        $user = User::factory()->create();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $crudo = \DB::table('users')->where('id', $user->id)->value('app_authentication_secret');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $crudo);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
    }
}
