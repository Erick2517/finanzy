<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class CorreoRecuperacionTest extends TestCase
{
    public function test_el_correo_de_recuperacion_de_contrasena_esta_en_espanol(): void
    {
        $usuario = User::factory()->make(['email' => 'ana@correo.com']);

        $correo = (new ResetPassword('token-de-prueba'))->toMail($usuario);
        $html = (string) $correo->render();

        $this->assertSame('Restablece tu contraseña', $correo->subject);
        $this->assertStringContainsString('¡Hola!', $html);
        $this->assertStringContainsString('Recibes este correo porque se solicitó restablecer la contraseña de tu cuenta.', $html);
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertStringContainsString('Este enlace para restablecer la contraseña vence en 60 minutos.', $html);
        $this->assertStringContainsString('Si no solicitaste restablecer la contraseña, no necesitas hacer nada.', $html);
        $this->assertStringContainsString('Saludos,', $html);
        $this->assertStringContainsString('Si tienes problemas para pulsar el botón «Restablecer contraseña»', $html);
        $this->assertStringNotContainsString('Reset Password', $html);
    }
}
