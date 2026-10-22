<?php

/**
 * Regras de arquitetura conferidas a cada execução dos testes — AGENTS.md seções 3 e 8 e a
 * skill qualidade-codigo. Uma exceção nova em ->ignoring() só entra com justificativa e vira
 * pendência no AGENTS.md seção 12.
 */

use Illuminate\Database\Eloquent\Model;

arch('sem funções de depuração esquecidas')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('o domínio não conhece a interface nem o usuário logado (AGENTS.md 3.1)')
    ->expect('App\Domain')
    ->not->toUse(['Filament', 'Livewire', 'Illuminate\Http', 'Illuminate\Support\Facades\Auth', 'App\Filament', 'App\Http']);

arch('Actions não dependem da tela nem da requisição (AGENTS.md 3.1)')
    ->expect('App\Actions')
    ->not->toUse(['Filament', 'Livewire', 'Illuminate\Http\Request', 'App\Filament', 'App\Http']);

arch('contratos de integração são interfaces (AGENTS.md 3.1)')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('integrações externas só são ligadas no container, nunca usadas direto (AGENTS.md 3.1)')
    ->expect('App\Integrations')
    ->toOnlyBeUsedIn('App\Providers');

arch('Enums são enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('models estendem Model')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('configuração só é lida por config(), nunca env() fora de config/')
    ->expect('env')
    ->not->toBeUsedIn('App');

// Telas não fazem transação nem consulta crua: isso é trabalho da Action (AGENTS.md 3.1).
// Exceções existentes, a corrigir (AGENTS.md seção 12):
arch('telas do Filament não usam DB:: diretamente')
    ->expect('App\Filament')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring([
        'App\Filament\Escritorio\Pages\ConfiguracoesEscritorio',
        'App\Filament\Escritorio\Resources\Equipe\Pages\EditUsuario',
        'App\Filament\Escritorio\Resources\UsuariosEmpresa\Pages\EditUsuarioEmpresa',
    ]);
