<?php

use App\Models\ContatoComercial;
use App\Notifications\NovoContatoComercialNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-001 a RN-004 — página pública */
it('mostra a página pública com as seções de venda', function () {
    $this->get('/')->assertOk()
        ->assertSee('Pare de correr atrás de documento')
        ->assertSee('Portal da empresa incluso')
        ->assertSee('Sob consulta')
        ->assertSee('Agende sua demonstração')
        ->assertSee(route('login'));
});

it('registra o pedido de demonstração e avisa o comercial (RN-004)', function () {
    Notification::fake();

    $this->post('/demonstracao', [
        'nome' => 'Joana Contadora', 'escritorio' => 'Contabilidade Joana', 'email' => 'joana@exemplo.test',
        'telefone' => '(11) 99999-0000', 'quantidade_empresas' => '31_100', 'mensagem' => 'Quero acabar com a cobrança manual.',
    ])->assertRedirect(route('landing').'#contato')->assertSessionHas('contato_enviado');

    $contato = ContatoComercial::first();
    expect($contato->escritorio)->toBe('Contabilidade Joana')->and($contato->quantidade_empresas)->toBe('31 a 100');

    Notification::assertSentTo(new AnonymousNotifiable, NovoContatoComercialNotification::class,
        fn ($n, $canais, $notifiable) => $notifiable->routes['mail'] === config('services.comercial.email'));
});

it('valida os campos obrigatórios e ignora robôs', function () {
    $this->post('/demonstracao', ['nome' => ''])->assertSessionHasErrors(['nome', 'escritorio', 'email']);

    $this->post('/demonstracao', ['nome' => 'Robô', 'escritorio' => 'X', 'email' => 'r@x.test', 'site' => 'spam'])->assertRedirect();
    expect(ContatoComercial::count())->toBe(0);
});
