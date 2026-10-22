# Testes — pirâmide do Contab Admin

Pest 4, PostgreSQL de testes (`contab_testes`), nunca SQLite. Rodar sempre dentro do container:
`docker compose exec app ./vendor/bin/pest` (ou `--filter=...` para um teste).

## As três camadas

| Camada | Pasta | O que testa | Banco? | Velocidade |
|---|---|---|---|---|
| **Unitário** | `tests/Unit/<Modulo>/` | classes de `app/Domain`, Value Objects, Enums com comportamento | não | milissegundos |
| **Arquitetura** | `tests/Arch/` | dependências entre camadas, convenções (`arch()`) | não | segundos |
| **Feature** | `tests/Feature/` | Actions, autorização, telas Filament, comandos, jobs | sim (`RefreshDatabase`) | segundos |

**Regra:** toda regra de negócio pura ganha teste **unitário** (rápido, cobre muitos casos);
o teste de feature confere a integração e a autorização, sem repetir todos os casos.

> Hoje o projeto tem 1 arquivo unitário (`tests/Unit/DominioTest.php`) e vários de feature.
> Classes como `PreenchimentoAutomatico`, `VerificadorAdmissao`, `CalculadoraRotinaMensal`,
> `SelecionadorEmpresasParaCobranca` e `GrupoDocumentoFuncionario` são testadas só via feature.
> Código novo de domínio já nasce com teste unitário, em arquivo próprio
> (`tests/Unit/<Modulo>/<Classe>Test.php`); os antigos migram quando forem alterados.

## Unitário — como escrever

```php
// tests/Unit/Desligamento/CalculadoraAvisoPrevioTest.php
use App\Domain\Desligamento\CalculadoraAvisoPrevio;
use App\Enums\MotivoDesligamento;
use Carbon\CarbonImmutable;

it('soma 3 dias por ano completo na dispensa sem justa causa, até 90 (RN-542)', function (string $admissao, int $dias) {
    $calc = new CalculadoraAvisoPrevio;

    expect($calc->dias(MotivoDesligamento::SemJustaCausa, CarbonImmutable::parse($admissao), CarbonImmutable::parse('2026-10-10')))
        ->toBe($dias);
})->with([
    'menos de 1 ano' => ['2026-01-10', 30],
    '4 anos completos' => ['2022-03-01', 42],
    'teto de 90 dias' => ['1990-01-01', 90],
]);
```

- Sem banco, sem HTTP, sem `actingAs`. Se precisar de Model, use `new Model([...])` (sem salvar).
- **Datasets** (`->with()`) para tabelas de casos: limites, valores inválidos, viradas de mês.
- Datas: `Carbon::setTestNow()` / `CarbonImmutable` fixo; nunca dependa do dia de hoje.

## Feature — o que todo teste de Action cobre

1. **Caminho feliz** com o efeito gravado (`expect($registro->fresh()->situacao)->toBe(...)`).
2. **Validação**: cada `RegraDeNegocioException` relevante (`->toThrow(RegraDeNegocioException::class, 'trecho')`).
3. **Autorização** (AGENTS.md 6.2): administrador, colaborador com permissão, colaborador sem
   permissão, usuário de **outro escritório** e, quando pertence a empresa, usuário da própria
   empresa e de **outra empresa do mesmo escritório**.
4. **Imutabilidade/histórico** quando a RN exige (ex.: RN-551 não sobrescreve).
5. **Idempotência** para comandos agendados e jobs (rodar duas vezes não duplica).

Dados: o `DesenvolvimentoSeeder` já cria escritórios, empresas, usuários e admissões em todas
as situações — use-o (`$this->seed(DesenvolvimentoSeeder::class)`) em vez de montar tudo à mão.

## Telas Filament

- Página: `$this->actingAs($u)->get($url)->assertOk()->assertSee(...)`.
- Formulário/ação: `Livewire::test(Pagina::class, [...])->callAction(...)` / `->call('save')`.
- Lembre das armadilhas do AGENTS.md seção 11 (painel corrente, tenant ativo, relation managers *lazy*).

## Integrações

- Nunca chamar serviço externo em teste. Troque o contrato:
  `app()->instance(LeitorDadosDeDocumento::class, $falso)` ou `Mockery::mock(...)`.
- `Mail::fake()`, `Notification::fake()`, `Queue::fake()`, `Storage::fake('local')`.

## Convenções

- Descrição em português, começando por verbo, citando a RN: `it('não deixa solicitar com pendências (RN-586)')`.
- Um comportamento por teste; nome descreve o comportamento, não o método.
- Sem `sleep`, sem ordem entre testes, sem depender de ID fixo.
- Teste que falha por motivo legítimo **não é desligado**: corrija a causa (AGENTS.md 8).
